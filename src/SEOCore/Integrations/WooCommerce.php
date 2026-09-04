<?php

namespace Mihdan\IndexNow\SEOCore\Integrations;

use Mihdan\IndexNow\SEOCore\SiteInfoSettings\SiteInfoSettings;

/**
 * WooCommerce integration.
 *
 * Loosely modelled on Slim SEO Schema's WooCommerce integration
 * (slim-seo-schema/src/Integrations/WooCommerce.php), adapted to CrawlWP's
 * simpler, non-builder schema pipeline:
 *
 *  - Emits Product structured data (price, currency, availability, SKU,
 *    GTIN, seller, rating and reviews) on single product pages via the
 *    {@see crawlwp_schema_data} filter, replacing the generic
 *    Article/WebPage node.
 *  - Prices respect the store's "Display prices during cart/checkout"
 *    tax setting (`woocommerce_tax_display_shop`), match WooCommerce's own
 *    grouped-product price calculation, and always carry a
 *    `priceValidUntil` (matching WooCommerce's own default of "end of next
 *    year" unless there is an active sale end date).
 *  - Points breadcrumbs at the "product_cat" taxonomy instead of "category"
 *    on product pages, archives and the shop page.
 *  - Removes WooCommerce's own JSON-LD output so only CrawlWP's Product
 *    schema is printed (avoids duplicate/conflicting structured data).
 *
 * The Product schema replacement (both the removal of WooCommerce's native
 * output and CrawlWP's own Product node) can be turned off entirely via the
 * {@see crawlwp_woocommerce_schema_enabled} filter, letting store owners who
 * prefer WooCommerce's built-in structured data (tax-aware pricing, @id
 * graph linking, order-confirmation email schema, etc.) keep it untouched.
 * The breadcrumb taxonomy change is unaffected by this filter.
 */
class WooCommerce
{
	public function __construct()
	{
		add_action('init', [$this, 'init']);
	}

	public function init(): void
	{
		if (! function_exists('WC')) {
			return;
		}

		add_action('wp_footer', [$this, 'remove_woocommerce_schema'], 0);
		add_filter('crawlwp_breadcrumbs_args', [$this, 'change_breadcrumbs_taxonomy']);
		add_filter('crawlwp_schema_data', [$this, 'add_product_schema'], 10, 2);
	}

	/**
	 * Whether CrawlWP's own WooCommerce Product schema should replace
	 * WooCommerce's native structured data. Defaults to enabled; return
	 * `false` from the filter to keep WooCommerce's own Product/Order
	 * JSON-LD completely untouched.
	 *
	 * @return bool
	 */
	private function is_schema_enabled(): bool
	{
		/**
		 * Filters whether CrawlWP replaces WooCommerce's native Product
		 * structured data with its own.
		 *
		 * @param bool $enabled Whether the feature is enabled. Default true.
		 */
		return (bool) apply_filters('crawlwp_woocommerce_schema_enabled', true);
	}

	/**
	 * WooCommerce prints its own Product/Order JSON-LD on `wp_footer`. Remove
	 * it so the page doesn't end up with two competing Product schema blocks.
	 */
	public function remove_woocommerce_schema(): void
	{
		if (! $this->is_schema_enabled()) {
			return;
		}

		if (function_exists('WC') && isset(WC()->structured_data)) {
			remove_action('wp_footer', [WC()->structured_data, 'output_structured_data'], 10);
		}
	}

	/**
	 * Use the product category taxonomy for breadcrumbs on product pages,
	 * product taxonomy archives, and the shop page.
	 *
	 * @param array $args Breadcrumb args, see {@see \Mihdan\IndexNow\SEOCore\Breadcrumbs\Breadcrumbs}.
	 * @return array
	 */
	public function change_breadcrumbs_taxonomy(array $args): array
	{
		if (is_singular('product') || is_product_taxonomy() || is_shop()) {
			$args['taxonomy'] = 'product_cat';
		}

		return $args;
	}

	/**
	 * Replace the generic Article/WebPage schema with a Product node on
	 * single product pages.
	 *
	 * @param array         $schema The schema array built by FrontendOutput.
	 * @param \WP_Post|null $post   The queried post, or null for non-singular requests.
	 * @return array
	 */
	public function add_product_schema($schema, $post)
	{
		if (! $this->is_schema_enabled()) {
			return $schema;
		}

		if (! $post instanceof \WP_Post || $post->post_type !== 'product') {
			return $schema;
		}

		$product = wc_get_product($post);

		if (! $product) {
			return $schema;
		}

		/* Article-only properties don't apply to a Product node. */
		unset($schema['headline'], $schema['articleSection'], $schema['author'], $schema['datePublished'], $schema['dateModified']);

		$schema['@type']        = 'Product';
		$schema['name']         = $product->get_name();

		$description = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());

		if ($description !== '') {
			$schema['description'] = $description;
		}

		$sku = $product->get_sku();

		if ($sku !== '') {
			$schema['sku'] = $sku;
		}

		$gtin = $this->get_gtin($product);

		if ($gtin !== '') {
			$schema['gtin'] = $gtin;
		}

		/* Only take the product's own image when no OG image is already configured. */
		if (empty($schema['image'])) {
			$image_id = $product->get_image_id();

			if ($image_id) {
				$image_url = wp_get_attachment_image_url($image_id, 'full');

				if ($image_url) {
					$schema['image'] = $image_url;
				}
			}
		}

		$schema['offers'] = $this->get_offers($product, $post);

		$rating       = (float) $product->get_average_rating();
		$review_count = (int) $product->get_review_count();

		if ($rating > 0 && $review_count > 0) {
			$schema['aggregateRating'] = [
				'@type'       => 'AggregateRating',
				'ratingValue' => $rating,
				'reviewCount' => $review_count,
			];
		}

		$reviews = $this->get_reviews($product);

		if ($reviews) {
			$schema['review'] = $reviews;
		}

		return $schema;
	}

	/**
	 * Build the "offers" node — a single Offer for simple/grouped/external
	 * products, or an AggregateOffer (low/high price across variations) for
	 * variable products. Prices respect the store's tax display setting.
	 *
	 * @param \WC_Product $product
	 * @param \WP_Post    $post
	 * @return array
	 */
	private function get_offers($product, $post): array
	{
		$currency     = get_woocommerce_currency();
		$availability = $this->get_availability($product);
		$url          = get_permalink($post);
		$price_fn     = $this->get_price_function();

		if ($product->is_type('grouped')) {
			$price = $this->get_grouped_product_price($product, $price_fn);

			$offer = [
				'@type'           => 'Offer',
				'price'           => $price,
				'priceCurrency'   => $currency,
				'url'             => $url,
				'availability'    => $availability,
				'priceValidUntil' => $this->get_price_valid_until($product),
			];

			$this->add_seller($offer);

			return $offer;
		}

		if ($product->is_type('variable')) {
			$low_price  = $price_fn($product, ['price' => $product->get_variation_price('min', false)]);
			$high_price = $price_fn($product, ['price' => $product->get_variation_price('max', false)]);

			$purchasable_children = array_filter($product->get_children(), static function ($child_id) {
				$child = wc_get_product($child_id);

				return $child && $child->is_purchasable();
			});

			$offer = [
				'@type'           => 'AggregateOffer',
				'lowPrice'        => $low_price,
				'highPrice'       => $high_price,
				'priceCurrency'   => $currency,
				'offerCount'      => count($purchasable_children),
				'url'             => $url,
				'availability'    => $availability,
				'priceValidUntil' => $this->get_price_valid_until($product),
			];

			$this->add_seller($offer);

			return $offer;
		}

		/* External/Affiliate products are redeemed on the merchant's own site, not on ours. */
		$offer_url = $product->is_type('external') ? $product->get_product_url() : $url;

		$price = $price_fn($product, ['price' => $product->get_price()]);

		$offer = [
			'@type'           => 'Offer',
			'price'           => $price,
			'priceCurrency'   => $currency,
			'url'             => $offer_url ?: $url,
			'availability'    => $availability,
			'priceValidUntil' => $this->get_price_valid_until($product),
		];

		$this->add_seller($offer);

		return $offer;
	}

	/**
	 * Which WooCommerce price helper to use, based on the store's
	 * "Display prices during cart and checkout" setting
	 * (`woocommerce_tax_display_shop`: 'incl' or 'excl').
	 *
	 * @return callable
	 */
	private function get_price_function(): callable
	{
		if (get_option('woocommerce_tax_display_shop') === 'incl') {
			return 'wc_get_price_including_tax';
		}

		return 'wc_get_price_excluding_tax';
	}

	/**
	 * Grouped products have no price of their own — WooCommerce's
	 * `get_price()` returns an empty string for them. Mirror what
	 * WooCommerce's own structured data does: take the lowest
	 * regular/sale price across the group's visible children.
	 *
	 * @param \WC_Product $product
	 * @param callable    $price_fn
	 * @return string
	 */
	private function get_grouped_product_price($product, callable $price_fn): string
	{
		$children = array_filter(
			array_map('wc_get_product', $product->get_children()),
			static function ($child) {
				return $child instanceof \WC_Product && $child->is_visible();
			}
		);

		$prices = [];

		foreach ($children as $child) {
			if ($child->get_price() !== '') {
				$prices[] = (float) $price_fn($child, ['price' => $child->get_price()]);
			}
		}

		return wc_format_decimal($prices ? min($prices) : 0, wc_get_price_decimals());
	}

	/**
	 * A price is only ever valid until an active sale ends. Otherwise,
	 * mirror WooCommerce's own default of assuming the price is valid
	 * through the end of next year — Google flags a Product `Offer` with
	 * no `priceValidUntil` at all as an issue in Merchant Center.
	 *
	 * @param \WC_Product $product
	 * @return string
	 */
	private function get_price_valid_until($product): string
	{
		if ($product->is_on_sale() && $product->get_date_on_sale_to()) {
			return gmdate('Y-m-d', $product->get_date_on_sale_to()->getTimestamp());
		}

		return gmdate('Y-12-31', time() + YEAR_IN_SECONDS);
	}

	/**
	 * Point the offer's seller at the site-wide Organization/Person node
	 * already emitted by FrontendOutput::output_site_graph(), rather than
	 * duplicating that data on every single product page.
	 *
	 * @param array $offer
	 */
	private function add_seller(array &$offer): void
	{
		$is_org = (string) SiteInfoSettings::get('site_type', 'organization') !== 'person';

		$offer['seller'] = [
			'@id' => home_url('/') . '#' . ($is_org ? 'organization' : 'person'),
		];
	}

	/**
	 * A valid GTIN (8, 12, 13 or 14 digits) from the product's "Global
	 * Unique ID" field, if set and available on this WooCommerce version.
	 *
	 * @param \WC_Product $product
	 * @return string
	 */
	private function get_gtin($product): string
	{
		if (! method_exists($product, 'get_global_unique_id')) {
			return '';
		}

		$gtin = preg_replace('/[^0-9]/', '', (string) $product->get_global_unique_id());

		if ($gtin !== null && in_array(strlen($gtin), [8, 12, 13, 14], true)) {
			return $gtin;
		}

		return '';
	}

	/**
	 * Up to 5 most recent rated reviews, matching the shape (and query) of
	 * WooCommerce's own `WC_Structured_Data::generate_product_data()`.
	 *
	 * @param \WC_Product $product
	 * @return array
	 */
	private function get_reviews($product): array
	{
		if (! $product->get_rating_count() || ! wc_review_ratings_enabled()) {
			return [];
		}

		$comments = get_comments([
			'number'      => 5,
			'post_id'     => $product->get_id(),
			'status'      => 'approve',
			'post_status' => 'publish',
			'post_type'   => 'product',
			'parent'      => 0,
			'meta_query'  => [
				[
					'key'     => 'rating',
					'type'    => 'NUMERIC',
					'compare' => '>',
					'value'   => 0,
				],
			],
		]);

		if (! $comments) {
			return [];
		}

		$reviews = [];

		foreach ($comments as $comment) {
			$reviews[] = [
				'@type'         => 'Review',
				'reviewRating'  => [
					'@type'       => 'Rating',
					'ratingValue' => get_comment_meta($comment->comment_ID, 'rating', true),
					'bestRating'  => '5',
					'worstRating' => '1',
				],
				'author' => [
					'@type' => 'Person',
					'name'  => get_comment_author($comment),
				],
				'reviewBody'    => get_comment_text($comment),
				'datePublished' => get_comment_date('c', $comment),
			];
		}

		return $reviews;
	}

	/**
	 * Map a WooCommerce stock status to a schema.org ItemAvailability URL.
	 *
	 * @param \WC_Product $product
	 * @return string
	 */
	private function get_availability($product): string
	{
		$statuses = [
			'instock'     => 'InStock',
			'outofstock'  => 'OutOfStock',
			'onbackorder' => 'BackOrder',
		];

		$status = strtolower($product->get_stock_status());
		$status = $statuses[$status] ?? 'InStock';

		return 'https://schema.org/' . $status;
	}
}
