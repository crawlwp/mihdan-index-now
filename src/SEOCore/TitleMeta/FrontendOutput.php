<?php

namespace Mihdan\IndexNow\SEOCore\TitleMeta;

use Mihdan\IndexNow\SEOCore\Breadcrumbs\Breadcrumbs;
use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;
use Mihdan\IndexNow\SEOCore\SiteInfoSettings\SiteInfoSettings;
use Mihdan\IndexNow\SEOCore\SocialSettings\SocialSettings;
use Mihdan\IndexNow\SEOCore\SocialSettings\UserProfile;

/**
 * Emits the document title and meta tags for every front end request.
 *
 * Values are resolved with the following precedence:
 *
 *  1. Per-post values saved from the SEO metabox (singular requests only).
 *  2. The global default stored for the matching entity screen.
 *  3. The default template registered in {@see Entities}.
 */
class FrontendOutput
{
	/**
	 * Memoised resolution for the current request.
	 *
	 * @var array|null|false Null until resolved, false when nothing applies.
	 */
	private $resolved = null;

	/**
	 * Shared Breadcrumbs instance — built once per request.
	 *
	 * @var Breadcrumbs|null
	 */
	private $breadcrumbs = null;

	public function __construct()
	{
		add_filter('pre_get_document_title', [$this, 'filter_document_title'], 15);
		add_filter('wp_robots', [$this, 'filter_robots'], 20);
		add_action('wp_head', [$this, 'output'], 1);
		add_action('wp_head', [$this, 'output_site_graph'], 2);
		add_action('template_redirect', [$this, 'unhook_core_canonical']);
	}

	/**
	 * We print our own canonical link, so core must not print a second one.
	 */
	public function unhook_core_canonical(): void
	{
		$data = $this->resolve();

		if ($data !== false && $data['canonical'] !== '') {
			remove_action('wp_head', 'rel_canonical');
		}
	}

	/**
	 * Feed our directives into the single robots tag rendered by core.
	 *
	 * @param array $robots Directives keyed by name.
	 */
	public function filter_robots($robots)
	{
		$data = $this->resolve();

		if ($data === false || empty($data['robots'])) {
			return $robots;
		}

		$directives = [];

		foreach ($data['robots'] as $directive) {
			if (strpos($directive, ':') !== false) {
				[$name, $value] = explode(':', $directive, 2);
				$directives[$name] = $value;

				continue;
			}

			$directives[$directive] = true;
		}

		return $directives;
	}

	/**
	 * Feed WordPress our title so themes supporting `title-tag` render it.
	 */
	public function filter_document_title($title)
	{
		$data = $this->resolve();

		if ($data === false || $data['title'] === '') {
			return $title;
		}

		return $data['title'];
	}

	/**
	 * Output every meta tag we own.
	 */
	public function output(): void
	{
		$data = $this->resolve();

		if ($data === false) {
			return;
		}

		echo "\n" . '<!-- CrawlWP SEO -->' . "\n";

		/*
		 * Only print the title ourselves when nothing in core will do it, which
		 * is the case for classic themes that do not declare `title-tag`.
		 */
		if ($data['title'] !== '' && ! $this->core_renders_title()) {
			echo '<title>' . esc_html($data['title']) . '</title>' . "\n";
		}

		if ($data['description'] !== '') {
			echo '<meta name="description" content="' . esc_attr($data['description']) . '" />' . "\n";
		}

		/* Core renders the robots tag from our `wp_robots` filter. */
		if (! empty($data['robots']) && ! has_action('wp_head', 'wp_robots')) {
			echo '<meta name="robots" content="' . esc_attr(implode(', ', $data['robots'])) . '" />' . "\n";
		}

		if ($data['canonical'] !== '') {
			echo '<link rel="canonical" href="' . esc_url($data['canonical']) . '" />' . "\n";
		}

		if (SocialSettings::get('og_tags', 'on') !== 'off') {
			$this->output_open_graph($data);
		}

		if (SocialSettings::get('twitter_tags', 'on') !== 'off') {
			$this->output_twitter_card($data);
		}

		$this->output_schema($data);

		echo '<!-- /CrawlWP SEO -->' . "\n";
	}

	/**
	 * Whether WordPress itself will render a title tag for this request.
	 *
	 * Block themes render it unconditionally through
	 * `_block_template_render_title_tag`, while classic themes only do so when
	 * they declare `title-tag` support. Either way the markup is built from
	 * `wp_get_document_title()`, which our `pre_get_document_title` filter owns.
	 */
	private function core_renders_title(): bool
	{
		if (has_action('wp_head', '_block_template_render_title_tag')) {
			return true;
		}

		return current_theme_supports('title-tag') && has_action('wp_head', '_wp_render_title_tag');
	}

	/**
	 * Resolve the current request into a ready-to-print payload.
	 *
	 * @return array|false
	 */
	private function resolve()
	{
		if ($this->resolved !== null) {
			return $this->resolved;
		}

		$this->resolved = false;

		if (is_admin() || is_feed() || is_trackback() || is_robots()) {
			return $this->resolved;
		}

		$target = $this->match_entity();

		if ($target === null) {
			return $this->resolved;
		}

		[$entity_key, $prefix, $context] = $target;

		$post = $context['post'] ?? null;
		$post = $post instanceof \WP_Post ? $post : null;

		/* Singular requests may carry per-post overrides from the metabox. */
		$overrides = $post !== null ? $this->post_overrides($post->ID) : [];

		$title = Variables::replace(
			$overrides['title'] ?? $this->template($entity_key, $prefix . 'title'),
			$context
		);

		$description = Variables::replace(
			$overrides['description'] ?? $this->template($entity_key, $prefix . 'description'),
			$context
		);

		/* Unified social override: one title/description shared by OG and X/Twitter. */
		$social_title = Variables::replace(
			$overrides['og_title'] ?? (string) Options::get($entity_key, 'social_title', ''),
			$context
		);

		$social_description = Variables::replace(
			$overrides['og_description'] ?? (string) Options::get($entity_key, 'social_description', ''),
			$context
		);

		/* When "Remove site title from social titles" is on and no custom social title is set,
		 * strip the separator + site name from the generated title for social use. */
		$og_title_base = $social_title !== '' ? $social_title : $this->maybe_strip_site_name($title);
		$x_title_base  = $og_title_base;

		$this->resolved = [
			'entity'         => $entity_key,
			'prefix'         => $prefix,
			'context'        => $context,
			'post'           => $post,
			'title'          => $title,
			'description'    => $description,
			'robots'         => $this->robots($entity_key, $prefix, $post),
			'canonical'      => $this->canonical($post, $overrides),
			'og_title'       => $og_title_base,
			'og_description' => $social_description !== '' ? $social_description : $description,
			'og_image'       => $this->image($entity_key, $prefix, 'og_image', $post),
			'x_title'        => $x_title_base,
			'x_description'  => $social_description !== '' ? $social_description : $description,
			'x_image'        => $this->image($entity_key, $prefix, 'x_image', $post),
			'og_type'        => $post !== null && ! is_front_page() ? 'article' : 'website',
		];

		return $this->resolved;
	}

	/**
	 * Map the current query onto an entity, a field prefix and a variable context.
	 *
	 * @return array{0: string, 1: string, 2: array}|null
	 */
	private function match_entity(): ?array
	{
		if (is_404()) {
			return ['not_found', '', []];
		}

		if (is_search()) {
			return ['search', '', []];
		}

		if (is_front_page()) {
			$context = [];

			if (is_page()) {
				$page = get_queried_object();

				if ($page instanceof \WP_Post) {
					$context['post']      = $page;
					$context['post_type'] = get_post_type_object($page->post_type);
				}
			}

			return ['home', '', $context];
		}

		/* Blog posts index when a static front page is in use. */
		if (is_home()) {
			return [
				Entities::post_type_key('post'),
				'archive_',
				['post_type' => get_post_type_object('post')],
			];
		}

		if (is_singular()) {
			$post = get_queried_object();

			if (! $post instanceof \WP_Post) {
				return null;
			}

			$entity_key = Entities::post_type_key($post->post_type);

			if (Entities::get($entity_key) === null) {
				return null;
			}

			return [
				$entity_key,
				'',
				[
					'post'      => $post,
					'post_type' => get_post_type_object($post->post_type),
				],
			];
		}

		if (is_post_type_archive()) {
			$post_type = $this->queried_post_type();

			if ($post_type === null) {
				return null;
			}

			$entity_key = Entities::post_type_key($post_type->name);

			if (Entities::get($entity_key) === null) {
				return null;
			}

			return [$entity_key, 'archive_', ['post_type' => $post_type]];
		}

		if (is_category() || is_tag() || is_tax()) {
			$term = get_queried_object();

			if (! $term instanceof \WP_Term) {
				return null;
			}

			$entity_key = Entities::taxonomy_key($term->taxonomy);

			if (Entities::get($entity_key) === null) {
				return null;
			}

			return [$entity_key, '', ['term' => $term]];
		}

		if (is_author()) {
			$user = get_queried_object();

			return ['author', '', $user instanceof \WP_User ? ['user' => $user] : []];
		}

		if (is_date()) {
			return ['date', '', []];
		}

		return null;
	}

	/**
	 * Stored template for a field, falling back to the registered default.
	 */
	private function template(string $entity_key, string $field): string
	{
		return (string) Options::get(
			$entity_key,
			$field,
			Entities::default_value($entity_key, $field)
		);
	}

	/**
	 * Non-empty per-post metabox values, keyed like the global fields.
	 */
	private function post_overrides(int $post_id): array
	{
		$map = [
			'title'          => MetaFields::SEO_TITLE,
			'description'    => MetaFields::SEO_DESCRIPTION,
			'og_title'       => MetaFields::OG_TITLE,
			'og_description' => MetaFields::OG_DESCRIPTION,
			'x_title'        => MetaFields::X_TITLE,
			'x_description'  => MetaFields::X_DESCRIPTION,
			'canonical_url'  => MetaFields::CANONICAL_URL,
		];

		$overrides = [];

		foreach ($map as $field => $meta_key) {
			$value = MetaFields::get($post_id, $meta_key);

			if ($value !== '' && $value !== null) {
				$overrides[$field] = (string) $value;
			}
		}

		return $overrides;
	}

	/**
	 * Build the robots directive list from the entity defaults and, when
	 * available, the per-post metabox values.
	 */
	private function robots(string $entity_key, string $prefix, ?\WP_Post $post): array
	{
		$directives = [];

		$noindex  = $this->is_noindexed($entity_key, $prefix);
		$nofollow = Options::is_on($entity_key, 'nofollow');

		if ($post !== null) {
			$post_index  = MetaFields::get($post->ID, MetaFields::ROBOTS_INDEX);
			$post_follow = MetaFields::get($post->ID, MetaFields::ROBOTS_FOLLOW);

			if ($post_index !== '') {
				$noindex = $post_index === 'noindex';
			}

			if ($post_follow !== '') {
				$nofollow = $post_follow === 'nofollow';
			}
		}

		$directives[] = $noindex ? 'noindex' : 'index';
		$directives[] = $nofollow ? 'nofollow' : 'follow';

		if (Options::is_on($entity_key, 'noarchive')) {
			$directives[] = 'noarchive';
		}

		if ($post !== null) {
			$advanced = MetaFields::get($post->ID, MetaFields::ROBOTS_ADVANCED, []);

			if (is_array($advanced)) {
				foreach ($advanced as $directive) {
					if (in_array($directive, ['noarchive', 'nosnippet', 'noimageindex', 'notranslate'], true)) {
						$directives[] = $directive;
					}
				}
			}
		}

		$directives = array_values(array_unique($directives));

		/**
		 * Filter the robots directives for the current request.
		 *
		 * @param string[] $directives  The directive list.
		 * @param string   $entity_key  The matched entity key.
		 */
		return (array) apply_filters('crawlwp_robots_directives', $directives, $entity_key);
	}

	/**
	 * Whether an entity is hidden from search results.
	 *
	 * An explicitly saved value always wins over the registered default, which
	 * matters for the search and 404 screens that ship with noindex enabled.
	 */
	public static function is_noindexed(string $entity_key, string $prefix = ''): bool
	{
		$stored = Options::all($entity_key);
		$field  = $prefix . 'noindex';

		if (isset($stored[$field]) && $stored[$field] !== '') {
			return $stored[$field] === 'on';
		}

		return Entities::default_value($entity_key, 'noindex', 'off') === 'on';
	}

	/**
	 * Canonical URL for the current request.
	 */
	private function canonical(?\WP_Post $post, array $overrides): string
	{
		if (! empty($overrides['canonical_url'])) {
			return $overrides['canonical_url'];
		}

		if (is_front_page()) {
			return home_url('/');
		}

		if ($post !== null) {
			return (string) get_permalink($post);
		}

		if (is_category() || is_tag() || is_tax()) {
			$term = get_queried_object();

			if ($term instanceof \WP_Term) {
				$link = get_term_link($term);

				return is_wp_error($link) ? '' : $link;
			}
		}

		if (is_author()) {
			$user = get_queried_object();

			return $user instanceof \WP_User ? (string) get_author_posts_url($user->ID) : '';
		}

		if (is_post_type_archive()) {
			$post_type = $this->queried_post_type();

			if ($post_type !== null) {
				$link = get_post_type_archive_link($post_type->name);

				return $link ?: '';
			}
		}

		if (is_home()) {
			$page_for_posts = (int) get_option('page_for_posts');

			return $page_for_posts ? (string) get_permalink($page_for_posts) : home_url('/');
		}

		return '';
	}

	/**
	 * Resolve a social image, honouring the per-post value, then the global
	 * default, then the featured image.
	 */
	private function image(string $entity_key, string $prefix, string $field, ?\WP_Post $post): string
	{
		if ($post !== null) {
			$meta_key = $field === 'og_image' ? MetaFields::OG_IMAGE : MetaFields::X_IMAGE;
			$image_id = (int) MetaFields::get($post->ID, $meta_key, 0);

			if ($image_id > 0) {
				$url = wp_get_attachment_image_url($image_id, 'full');

				if ($url) {
					return $url;
				}
			}
		}

		/* Global defaults are stored as URLs by the WPOSA image field. */
		$global = (string) Options::get($entity_key, $prefix . $field, '');

		if ($global !== '') {
			return $global;
		}

		if ($post !== null) {
			return (string) (get_the_post_thumbnail_url($post->ID, 'full') ?: '');
		}

		return '';
	}

	/**
	 * When "Remove site title from generated social titles" is enabled and the
	 * provided title ends with " {sep} {site name}", strip that suffix.
	 * If the setting is off, return the title unchanged.
	 *
	 * @param string $title The fully resolved page title.
	 */
	private function maybe_strip_site_name(string $title): string
	{
		if (SocialSettings::get('social_title_rem_additions', 'off') !== 'on') {
			return $title;
		}

		$site_name = get_bloginfo('name');

		if ($site_name === '') {
			return $title;
		}

		/* Strip any trailing " {anything} {site name}" suffix (sep + site name). */
		$suffix = preg_quote($site_name, '/');
		$cleaned = preg_replace('/\s*.+?\s*' . $suffix . '\s*$/', '', $title);

		return ($cleaned !== null && $cleaned !== '') ? trim($cleaned) : $title;
	}

	private function output_open_graph(array $data): void
	{
		echo '<meta property="og:type" content="' . esc_attr($data['og_type']) . '" />' . "\n";

		if ($data['og_title'] !== '') {
			echo '<meta property="og:title" content="' . esc_attr($data['og_title']) . '" />' . "\n";
		}

		if ($data['og_description'] !== '') {
			echo '<meta property="og:description" content="' . esc_attr($data['og_description']) . '" />' . "\n";
		}

		if ($data['canonical'] !== '') {
			echo '<meta property="og:url" content="' . esc_url($data['canonical']) . '" />' . "\n";
		}

		echo '<meta property="og:site_name" content="' . esc_attr(get_bloginfo('name')) . '" />' . "\n";

		/* Use entity image first; fall back to global social image fallback set in Social Networks settings. */
		$og_image = $data['og_image'];

		if ($og_image === '') {
			$fallback_id = (int) SocialSettings::get('social_image_fallback', 0);

			if ($fallback_id > 0) {
				$og_image = (string) (wp_get_attachment_image_url($fallback_id, 'full') ?: '');
			}
		}

		if ($og_image !== '') {
			echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
		}

		/* article:author for posts — per-author Facebook URL overrides the global fallback. */
		if ($data['post'] instanceof \WP_Post) {
			$author_id      = (int) $data['post']->post_author;
			$facebook_author = $author_id > 0 ? UserProfile::get($author_id, UserProfile::META_FACEBOOK) : '';

			if ($facebook_author === '') {
				$facebook_author = (string) SocialSettings::get('facebook_author', '');
			}

			if ($facebook_author !== '') {
				echo '<meta property="article:author" content="' . esc_url($facebook_author) . '" />' . "\n";
			}
		}

		/* article:published_time / article:modified_time for posts. */
		if ($data['og_type'] === 'article' && $data['post'] instanceof \WP_Post) {
			if (SocialSettings::get('post_publish_time', 'on') !== 'off') {
				$pub = get_the_date('c', $data['post']);

				if ($pub) {
					echo '<meta property="article:published_time" content="' . esc_attr($pub) . '" />' . "\n";
				}
			}

			if (SocialSettings::get('post_modify_time', 'on') !== 'off') {
				$mod = get_the_modified_date('c', $data['post']);

				if ($mod) {
					echo '<meta property="article:modified_time" content="' . esc_attr($mod) . '" />' . "\n";
				}
			}
		}
	}

	private function output_twitter_card(array $data): void
	{
		/* Global default card type from Social Networks settings; per-post override takes precedence. */
		$card_type = SocialSettings::get('twitter_card', 'summary_large_image') === 'summary' ? 'summary' : 'summary_large_image';

		if ($data['post'] instanceof \WP_Post) {
			$stored = MetaFields::get($data['post']->ID, MetaFields::X_CARD_TYPE, '');

			if ($stored === 'summary') {
				$card_type = 'summary';
			} elseif ($stored === 'summary_large_image') {
				$card_type = 'summary_large_image';
			}
		}

		echo '<meta name="twitter:card" content="' . esc_attr($card_type) . '" />' . "\n";

		/* Global site @username: read from Site Information profile_x field. */
		$twitter_site_url = (string) SiteInfoSettings::get('profile_x', '');
		/* Extract @handle from a full URL (https://x.com/handle) or use as-is if already a handle. */
		$twitter_site = '';

		if ($twitter_site_url !== '') {
			if (str_starts_with($twitter_site_url, '@')) {
				$twitter_site = $twitter_site_url;
			} else {
				$parsed = parse_url($twitter_site_url, PHP_URL_PATH);
				$handle = $parsed ? ltrim(trim($parsed, '/'), '@') : '';

				if ($handle !== '') {
					$twitter_site = '@' . $handle;
				}
			}
		}

		if ($twitter_site !== '') {
			echo '<meta name="twitter:site" content="' . esc_attr($twitter_site) . '" />' . "\n";
		}

		if ($data['x_title'] !== '') {
			echo '<meta name="twitter:title" content="' . esc_attr($data['x_title']) . '" />' . "\n";
		}

		if ($data['x_description'] !== '') {
			echo '<meta name="twitter:description" content="' . esc_attr($data['x_description']) . '" />' . "\n";
		}

		/* Use entity image; fall back to OG image and then global fallback. */
		$x_image = $data['x_image'] !== '' ? $data['x_image'] : $data['og_image'];

		if ($x_image === '') {
			$fallback_id = (int) SocialSettings::get('social_image_fallback', 0);

			if ($fallback_id > 0) {
				$x_image = (string) (wp_get_attachment_image_url($fallback_id, 'full') ?: '');
			}
		}

		if ($x_image !== '') {
			echo '<meta name="twitter:image" content="' . esc_url($x_image) . '" />' . "\n";
		}

		if ($data['post'] instanceof \WP_Post) {
			$creator = MetaFields::get($data['post']->ID, MetaFields::X_CREATOR);

			/* Fall back to per-author user profile setting, then global Social Networks setting. */
			if (empty($creator)) {
				$author_id = (int) $data['post']->post_author;
				$creator   = $author_id > 0 ? UserProfile::get($author_id, UserProfile::META_TWITTER) : '';
			}

			if (empty($creator)) {
				$creator = (string) SocialSettings::get('twitter_creator', '');
			}

			if (! empty($creator)) {
				echo '<meta name="twitter:creator" content="' . esc_attr($creator) . '" />' . "\n";
			}
		}
	}

	/**
	 * JSON-LD for singular requests.
	 *
	 * Resolves schema type from the two-select model (page_type + article_type).
	 * When page_type is a WebPage subtype and article_type is set (not 'none'),
	 * the effective @type is the article type — matching Yoast/Rank Math behaviour.
	 * Legacy single-select values stored in SCHEMA_TYPE are used as the article type
	 * when the new fields are absent, so existing data is not lost.
	 */
	private function output_schema(array $data): void
	{
		$post = $data['post'];

		if (! $post instanceof \WP_Post) {
			return;
		}

		/* --- resolve page type --- */
		$page_type = (string) MetaFields::get($post->ID, MetaFields::SCHEMA_PAGE_TYPE, '');

		if ($page_type === '') {
			/* Fall back to global setting, then entity default. */
			$global_page = (string) Options::get($data['entity'], 'schema_page_type', '');
			$page_type   = $global_page !== '' ? $global_page : 'WebPage';
		}

		if ($page_type === 'none') {
			return; // "None" selected for page type — suppress all structured data.
		}

		/* --- resolve article type --- */
		$article_type = (string) MetaFields::get($post->ID, MetaFields::SCHEMA_ARTICLE_TYPE, '');

		if ($article_type === '') {
			/* Check legacy single-select value. */
			$legacy = (string) MetaFields::get($post->ID, MetaFields::SCHEMA_TYPE, '');

			if ($legacy !== '' && $legacy !== 'none') {
				$article_type = $legacy;
			} else {
				$global_article = (string) Options::get($data['entity'], 'schema_article_type', '');
				$article_type   = $global_article !== '' ? $global_article : 'Article';
			}
		}

		/* --- determine the effective @type ---
		 * When article_type is not 'none', it overrides the page type,
		 * so e.g. "WebPage + Article" becomes @type Article.
		 * This mirrors Yoast SEO behaviour. */
		$schema_type = ($article_type !== '' && $article_type !== 'none') ? $article_type : $page_type;

		if ($schema_type === '' || $schema_type === 'none') {
			return;
		}

		$author      = get_userdata((int) $post->post_author);
		$author_name = $author ? $author->display_name : '';

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => $schema_type,
			'headline' => (string) MetaFields::get($post->ID, MetaFields::SCHEMA_HEADLINE, '') ?: $data['title'],
			'url'      => $data['canonical'],
		];

		$section = MetaFields::get($post->ID, MetaFields::SCHEMA_SECTION);

		if (! empty($section)) {
			$schema['articleSection'] = $section;
		}

		if ($author_name !== '') {
			$author_schema = [
				'@type' => 'Person',
				'name'  => $author_name,
			];

			/* Add sameAs from the author's additional profile URLs. */
			$same_as = UserProfile::get_additional_profiles((int) $post->post_author);

			if (! empty($same_as)) {
				$author_schema['sameAs'] = $same_as;
			}

			$schema['author'] = $author_schema;
		}

		if ($data['og_image'] !== '') {
			$schema['image'] = $data['og_image'];
		}

		$schema['datePublished'] = get_the_date('c', $post);
		$schema['dateModified']  = get_the_modified_date('c', $post);

		/**
		 * Filter the JSON-LD graph before it is printed.
		 *
		 * @param array    $schema The schema array.
		 * @param \WP_Post $post   The queried post.
		 */
		$schema = apply_filters('crawlwp_schema_data', $schema, $post);

		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		echo "\n" . '</script>' . "\n";
	}

	/**
	 * Output the site-wide WebSite + Organization/Person JSON-LD @graph.
	 *
	 * Emitted on every public front-end page. The graph matches the structure
	 * produced by Yoast SEO:
	 *
	 *  - WebSite node  (@id #website)  — site name, url, description, language,
	 *                                    optional SearchAction potentialAction.
	 *  - Organization  (@id #organization)  — or Person (@id #person) based on
	 *    the "Site type" setting — with name, url, logo ImageObject, and sameAs.
	 */
	public function output_site_graph(): void
	{
		if (is_admin() || is_feed() || is_trackback() || is_robots()) {
			return;
		}

		$home_url = home_url('/');
		$lang     = get_bloginfo('language');

		/* --- identity node --- */
		$site_type = (string) SiteInfoSettings::get('site_type', 'organization');
		$is_org    = $site_type !== 'person';

		$entity_type = $is_org ? 'Organization' : 'Person';
		$entity_id   = $home_url . '#' . ($is_org ? 'organization' : 'person');

		$name = (string) SiteInfoSettings::get('site_name', '');
		if ($name === '') {
			$name = get_bloginfo('name');
		}

		$description = (string) SiteInfoSettings::get('site_description', '');
		if ($description === '') {
			$description = get_bloginfo('description');
		}

		/* --- logo --- */
		$logo_id  = (int) SiteInfoSettings::get('logo', 0);
		$logo_url = $logo_id > 0 ? (string) wp_get_attachment_image_url($logo_id, 'full') : '';

		/* --- sameAs --- */
		$same_as = SiteInfoSettings::get_same_as();

		/* --- build entity node --- */
		$entity_node = [
			'@type' => $entity_type,
			'@id'   => $entity_id,
			'name'  => $name,
			'url'   => $home_url,
		];

		if ($logo_url !== '') {
			$logo_meta = wp_get_attachment_metadata($logo_id);
			$logo_node = [
				'@type'      => 'ImageObject',
				'inLanguage' => $lang,
				'@id'        => $home_url . '#/schema/logo/image/',
				'url'        => $logo_url,
				'contentUrl' => $logo_url,
			];

			if (is_array($logo_meta) && isset($logo_meta['width'], $logo_meta['height'])) {
				$logo_node['width']  = (int) $logo_meta['width'];
				$logo_node['height'] = (int) $logo_meta['height'];
			}

			if ($name !== '') {
				$logo_node['caption'] = $name;
			}

			$entity_node['logo']  = $logo_node;
			$entity_node['image'] = ['@id' => $home_url . '#/schema/logo/image/'];
		}

		if (! empty($same_as)) {
			$entity_node['sameAs'] = $same_as;
		}

		/* --- website node --- */
		$website_node = [
			'@type'       => 'WebSite',
			'@id'         => $home_url . '#website',
			'url'         => $home_url,
			'name'        => $name,
			'publisher'   => ['@id' => $entity_id],
			'inLanguage'  => $lang,
		];

		if ($description !== '') {
			$website_node['description'] = $description;
		}

		/* SearchAction (Sitelinks Search Box). */
		if (SiteInfoSettings::get('search_action', 'on') !== 'off') {
			$website_node['potentialAction'] = [
				[
					'@type'       => 'SearchAction',
 				'target'      => [
						'@type'       => 'EntryPoint',
						'urlTemplate' => str_replace(
							urlencode( '{search_term_string}' ),
							'{search_term_string}',
							get_search_link( '{search_term_string}' )
						),
					],
					'query-input' => [
						'@type'         => 'PropertyValueSpecification',
						'valueRequired' => true,
						'valueName'     => 'search_term_string',
					],
				],
			];
		}

		/* --- BreadcrumbList node --- */
		$bc_node = $this->get_breadcrumbs()->get_schema_node();

		/* --- assemble and print --- */
		$graph_nodes = [$website_node, $entity_node];
		if ($bc_node !== null) {
			$graph_nodes[] = $bc_node;
		}

		$graph = [
			'@context' => 'https://schema.org',
			'@graph'   => $graph_nodes,
		];

		/**
		 * Filter the site graph before it is printed.
		 *
		 * @param array $graph The @graph array (WebSite + Organization/Person nodes).
		 */
		$graph = apply_filters('crawlwp_site_graph', $graph);

		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
		echo "\n" . '</script>' . "\n";
	}

	/**
	 * Returns the shared (memoised) Breadcrumbs instance for this request.
	 */
	private function get_breadcrumbs(): Breadcrumbs
	{
		if ($this->breadcrumbs === null) {
			$this->breadcrumbs = new Breadcrumbs();
		}

		return $this->breadcrumbs;
	}

	/**
	 * The post type object behind a post type archive request.
	 */
	private function queried_post_type(): ?\WP_Post_Type
	{
		$queried = get_query_var('post_type');

		if (is_array($queried)) {
			$queried = reset($queried);
		}

		if (! $queried) {
			return null;
		}

		$post_type = get_post_type_object((string) $queried);

		return $post_type instanceof \WP_Post_Type ? $post_type : null;
	}
}
