<?php

namespace Mihdan\IndexNow\SEOCore\Integrations;

/**
 * WooCommerce integration.
 *
 * Loosely modelled on Slim SEO Schema's WooCommerce integration
 * (slim-seo-schema/src/Integrations/WooCommerce.php), adapted to CrawlWP's
 * simpler, non-builder schema pipeline:
 *
 *  - Emits Product structured data (price, currency, availability, SKU,
 *    rating) on single product pages via the {@see crawlwp_schema_data}
 *    filter, replacing the generic Article/WebPage node.
 *  - Points breadcrumbs at the "product_cat" taxonomy instead of "category"
 *    on product pages, archives and the shop page.
 *  - Removes WooCommerce's own JSON-LD output so only CrawlWP's Product
 *    schema is printed (avoids duplicate/conflicting structured data).
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
	 * WooCommerce prints its own Product/Order JSON-LD on `wp_footer`. Remove
	 * it so the page doesn't end up with two competing Product schema blocks.
	 */
	public function remove_woocommerce_schema(): void
	{
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
		$schema['description']  = wp_strip_all_tags($product->get_short_description() ?: $product->get_description());

		$sku = $product->get_sku();

		if ($sku !== '') {
			$schema['sku'] = $sku;
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

		return $schema;
	}

	/**
	 * Build the "offers" node — a single Offer for simple/other product
	 * types, or an AggregateOffer (low/high price across variations) for
	 * variable products.
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

		if ($product->is_type('variable')) {
			$low_price  = wc_get_price_including_tax($product, ['price' => $product->get_variation_price('min', false)]);
			$high_price = wc_get_price_including_tax($product, ['price' => $product->get_variation_price('max', false)]);

			return [
				'@type'         => 'AggregateOffer',
				'lowPrice'      => $low_price,
				'highPrice'     => $high_price,
				'priceCurrency' => $currency,
				'offerCount'    => count($product->get_children()),
				'url'           => $url,
				'availability'  => $availability,
			];
		}

		$price = wc_get_price_including_tax($product, ['price' => $product->get_price()]);

		$offer = [
			'@type'         => 'Offer',
			'price'         => $price,
			'priceCurrency' => $currency,
			'url'           => $url,
			'availability'  => $availability,
		];

		if ($product->is_on_sale() && $product->get_date_on_sale_to()) {
			$offer['priceValidUntil'] = gmdate('Y-m-d', $product->get_date_on_sale_to()->getTimestamp());
		}

		return $offer;
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
