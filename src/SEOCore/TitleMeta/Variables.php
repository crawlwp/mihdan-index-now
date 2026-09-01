<?php

namespace Mihdan\IndexNow\SEOCore\TitleMeta;

/**
 * Resolves template variables used in the global title & meta defaults.
 *
 * Two syntaxes are supported:
 *
 *  - Namespaced (canonical): {{ site.title }}, {{ post.auto_description }}
 *  - Legacy (metabox v1):    {{title}}, {{sitename}}, {{sep}}
 *
 * The legacy tokens are mapped onto their namespaced counterparts so values
 * saved by earlier versions of the SEO metabox keep resolving.
 */
class Variables
{
	/**
	 * Matches {{ name }}, {{name}} and {{ some.name }}.
	 */
	private const TOKEN_REGEX = '/\{\{\s*([a-z0-9_]+(?:\.[a-z0-9_]+)?)\s*\}\}/i';

	/**
	 * Matches the %%name%% macro syntax used by other SEO plugins.
	 */
	private const MACRO_REGEX = '/%%([a-z0-9_]+)%%/i';

	/**
	 * Legacy and third-party token => canonical token.
	 */
	private const LEGACY_MAP = [
		'title'             => 'post.title',
		'sitename'          => 'site.title',
		'sitedesc'          => 'site.description',
		'excerpt'           => 'post.auto_description',
		'excerpt_only'      => 'post.excerpt',
		'category'          => 'post.category',
		'primary_category'  => 'post.category',
		'author'            => 'post.author',
		'currentyear'       => 'current.year',
		'currentmonth'      => 'current.month',
		'currentdate'       => 'current.date',
		'tag'               => 'post.tag',
		'date'              => 'post.date',
		'modified'          => 'post.modified',
		'id'                => 'post.id',
		'searchphrase'      => 'search.query',
		'term_title'        => 'term.title',
		'term_description'  => 'term.description',
		'archive_title'     => 'date.archive_title',
		'pt_plural'         => 'post_type.plural_name',
		'pt_single'         => 'post_type.name',
		'user_description'  => 'author.description',
	];

	/**
	 * Resolution context for the current request.
	 *
	 * @var array
	 */
	private array $context;

	public function __construct(array $context = [])
	{
		$this->context = $context;
	}

	/**
	 * Convenience wrapper: resolve a template against a context.
	 */
	public static function replace(string $template, array $context = []): string
	{
		if ($template === '') {
			return '';
		}

		if (strpos($template, '{{') === false && strpos($template, '%%') === false) {
			return self::cleanup($template);
		}

		return (new self($context))->resolve($template);
	}

	/**
	 * Replace every recognised token in the template.
	 */
	public function resolve(string $template): string
	{
		/* Normalise %%macro%% values imported from other SEO plugins. */
		$template = (string) preg_replace(self::MACRO_REGEX, '{{$1}}', $template);

		$output = preg_replace_callback(
			self::TOKEN_REGEX,
			function ($matches) {
				return $this->resolve_token(strtolower($matches[1]));
			},
			$template
		);

		return self::cleanup((string) $output);
	}

	/**
	 * The site-wide title separator.
	 */
	public static function separator(): string
	{
		$separators = self::separator_choices();
		$stored     = Options::get('home', 'separator', '-');
		$separator  = $separators[$stored] ?? $stored;

		if ($separator === '') {
			$separator = '-';
		}

		/**
		 * Filter the title separator used by the {{ sep }} variable.
		 *
		 * @param string $separator The separator character.
		 */
		return (string) apply_filters('crawlwp_title_separator', $separator);
	}

	/**
	 * Selectable separator characters.
	 */
	public static function separator_choices(): array
	{
		return [
			'-'  => '-',
			'–'  => '–',
			'—'  => '—',
			'|'  => '|',
			'•'  => '•',
			'·'  => '·',
			'/'  => '/',
			'~'  => '~',
			'«'  => '«',
			'»'  => '»',
			'<'  => '<',
			'>'  => '>',
		];
	}

	/**
	 * Resolve a single token to its replacement value.
	 */
	private function resolve_token(string $token): string
	{
		if (isset(self::LEGACY_MAP[$token])) {
			$token = self::LEGACY_MAP[$token];
		}

		switch ($token) {
			case 'sep':
				return self::separator();

			case 'page':
				return $this->paged_label();

			case 'name':
				/* %%name%% means the post author, or the archive author. */
				if (isset($this->context['post'])) {
					return $this->resolve_post_token('author');
				}

				return $this->resolve_author_token('display_name');

			case 'site.title':
				return (string) get_bloginfo('name');

			case 'site.description':
				return (string) get_bloginfo('description');

			case 'site.url':
				return home_url('/');

			case 'current.year':
				return gmdate('Y');

			case 'current.month':
				return gmdate('F');

			case 'current.day':
				return gmdate('j');

			case 'current.date':
				return wp_date((string) get_option('date_format')) ?: gmdate('Y-m-d');

			case 'search.query':
				return (string) get_search_query();

			case 'search.results_count':
				global $wp_query;

				return isset($wp_query->found_posts) ? (string) (int) $wp_query->found_posts : '0';

			case 'date.archive_title':
				return $this->date_archive_title();
		}

		if (strpos($token, 'post.') === 0) {
			return $this->resolve_post_token(substr($token, 5));
		}

		if (strpos($token, 'term.') === 0) {
			return $this->resolve_term_token(substr($token, 5));
		}

		if (strpos($token, 'author.') === 0) {
			return $this->resolve_author_token(substr($token, 7));
		}

		if (strpos($token, 'post_type.') === 0) {
			return $this->resolve_post_type_token(substr($token, 10));
		}

		/**
		 * Allow third parties to resolve custom variables.
		 *
		 * @param string $value   Empty by default.
		 * @param string $token   The token name without braces.
		 * @param array  $context The resolution context.
		 */
		return (string) apply_filters('crawlwp_resolve_title_variable', '', $token, $this->context);
	}

	private function resolve_post_token(string $key): string
	{
		$post = $this->context['post'] ?? null;

		if (! $post instanceof \WP_Post) {
			return '';
		}

		switch ($key) {
			case 'title':
				return get_the_title($post);

			case 'excerpt':
				return $post->post_excerpt;

			case 'auto_description':
				return $post->post_excerpt
					?: wp_trim_words(wp_strip_all_tags(strip_shortcodes($post->post_content)), 30, '...');

			case 'content':
				return wp_strip_all_tags($post->post_content);

			case 'id':
				return (string) $post->ID;

			case 'url':
				return (string) get_permalink($post);

			case 'slug':
				return $post->post_name;

			case 'date':
				return (string) get_the_date('', $post);

			case 'modified':
				return (string) get_the_modified_date('', $post);

			case 'author':
				$author = get_userdata((int) $post->post_author);

				return $author ? $author->display_name : '';

			case 'category':
				return $this->first_term_name($post, 'category');

			case 'tag':
				return $this->first_term_name($post, 'post_tag');

			case 'comment_count':
				return (string) (int) $post->comment_count;
		}

		return '';
	}

	private function resolve_term_token(string $key): string
	{
		$term = $this->context['term'] ?? null;

		if (! $term instanceof \WP_Term) {
			return '';
		}

		switch ($key) {
			case 'title':
			case 'name':
				return $term->name;

			case 'description':
				return wp_strip_all_tags($term->description);

			case 'auto_description':
				$description = wp_strip_all_tags($term->description);

				if ($description !== '') {
					return wp_trim_words($description, 30, '...');
				}

				/* translators: %s: taxonomy term name. */
				return sprintf(__('Browse all content filed under %s.', 'mihdan-index-now'), $term->name);

			case 'slug':
				return $term->slug;

			case 'count':
				return (string) (int) $term->count;

			case 'url':
				$link = get_term_link($term);

				return is_wp_error($link) ? '' : $link;
		}

		return '';
	}

	private function resolve_author_token(string $key): string
	{
		$user = $this->context['user'] ?? null;

		if (! $user instanceof \WP_User) {
			return '';
		}

		switch ($key) {
			case 'display_name':
			case 'name':
				return $user->display_name;

			case 'first_name':
				return (string) $user->first_name;

			case 'last_name':
				return (string) $user->last_name;

			case 'nickname':
				return (string) $user->nickname;

			case 'description':
				return wp_strip_all_tags((string) $user->description);

			case 'auto_description':
				$bio = wp_strip_all_tags((string) $user->description);

				if ($bio !== '') {
					return wp_trim_words($bio, 30, '...');
				}

				/* translators: 1: author display name, 2: site title. */
				return sprintf(
					__('Read all articles written by %1$s on %2$s.', 'mihdan-index-now'),
					$user->display_name,
					get_bloginfo('name')
				);

			case 'posts_count':
				return (string) count_user_posts($user->ID);

			case 'url':
				return (string) get_author_posts_url($user->ID);
		}

		return '';
	}

	private function resolve_post_type_token(string $key): string
	{
		$post_type = $this->context['post_type'] ?? null;

		if (! $post_type instanceof \WP_Post_Type) {
			return '';
		}

		switch ($key) {
			case 'name':
			case 'singular_name':
				return (string) $post_type->labels->singular_name;

			case 'plural_name':
			case 'label':
				return (string) $post_type->label;

			case 'description':
				return (string) $post_type->description;

			case 'slug':
				return (string) $post_type->name;
		}

		return '';
	}

	private function first_term_name(\WP_Post $post, string $taxonomy): string
	{
		$terms = get_the_terms($post->ID, $taxonomy);

		if (empty($terms) || is_wp_error($terms)) {
			return '';
		}

		return $terms[0]->name;
	}

	/**
	 * "Page 2 of 7" for paginated archives, empty on the first page.
	 */
	private function paged_label(): string
	{
		global $wp_query, $page, $paged;

		$current = 0;

		if (is_singular()) {
			$current = (int) $page;
		} else {
			$current = (int) $paged;
		}

		if ($current < 2) {
			return '';
		}

		$total = 0;

		if (is_singular()) {
			$post = $this->context['post'] ?? null;
			if ($post instanceof \WP_Post) {
				$total = count(explode('<!--nextpage-->', $post->post_content));
			}
		} elseif (isset($wp_query->max_num_pages)) {
			$total = (int) $wp_query->max_num_pages;
		}

		if ($total > 1) {
			/* translators: 1: current page number, 2: total number of pages. */
			return sprintf(__('Page %1$d of %2$d', 'mihdan-index-now'), $current, $total);
		}

		/* translators: %d: current page number. */
		return sprintf(__('Page %d', 'mihdan-index-now'), $current);
	}

	private function date_archive_title(): string
	{
		if (is_year()) {
			return (string) get_the_date('Y');
		}

		if (is_month()) {
			return (string) get_the_date('F Y');
		}

		if (is_day()) {
			return (string) get_the_date();
		}

		return __('Archives', 'mihdan-index-now');
	}

	/**
	 * Tidy up a resolved string: unresolved tokens are dropped and separators
	 * left dangling by empty values are collapsed.
	 */
	private static function cleanup(string $value): string
	{
		/* Drop any token we could not resolve. */
		$value = preg_replace(self::TOKEN_REGEX, '', $value) ?? $value;
		$value = preg_replace(self::MACRO_REGEX, '', $value) ?? $value;

		$separator = preg_quote(self::separator(), '/');

		/* Collapse "A - - B" into "A - B". */
		$value = preg_replace('/(?:\s*' . $separator . '\s*){2,}/u', ' ' . self::separator() . ' ', $value) ?? $value;

		/* Trim leading/trailing separators. */
		$value = preg_replace('/^(?:\s*' . $separator . '\s*)+/u', '', $value) ?? $value;
		$value = preg_replace('/(?:\s*' . $separator . '\s*)+$/u', '', $value) ?? $value;

		/* Normalise whitespace. */
		$value = preg_replace('/\s{2,}/u', ' ', $value) ?? $value;

		return trim($value);
	}

	/**
	 * Variable reference shown in the settings UI, grouped by namespace.
	 *
	 * @return array<string, array{label: string, variables: array<string, string>}>
	 */
	public static function definitions(): array
	{
		$definitions = [
			'general' => [
				'label'     => __('General', 'mihdan-index-now'),
				'variables' => [
					'sep'              => __('Title separator', 'mihdan-index-now'),
					'page'             => __('Current page number, e.g. "Page 2 of 7"', 'mihdan-index-now'),
					'site.title'       => __('Site title', 'mihdan-index-now'),
					'site.description' => __('Site tagline', 'mihdan-index-now'),
					'site.url'         => __('Site home URL', 'mihdan-index-now'),
					'current.year'     => __('Current year', 'mihdan-index-now'),
					'current.month'    => __('Current month', 'mihdan-index-now'),
					'current.date'     => __('Current date', 'mihdan-index-now'),
				],
			],
			'post'    => [
				'label'     => __('Post', 'mihdan-index-now'),
				'variables' => [
					'post.title'            => __('Post title', 'mihdan-index-now'),
					'post.auto_description' => __('Post excerpt, or the first 30 words of the content', 'mihdan-index-now'),
					'post.excerpt'          => __('Post excerpt only', 'mihdan-index-now'),
					'post.author'           => __('Post author display name', 'mihdan-index-now'),
					'post.category'         => __('First category assigned to the post', 'mihdan-index-now'),
					'post.tag'              => __('First tag assigned to the post', 'mihdan-index-now'),
					'post.date'             => __('Post publish date', 'mihdan-index-now'),
					'post.modified'         => __('Post last modified date', 'mihdan-index-now'),
					'post.url'              => __('Post permalink', 'mihdan-index-now'),
				],
			],
			'term'    => [
				'label'     => __('Taxonomy term', 'mihdan-index-now'),
				'variables' => [
					'term.title'            => __('Term name', 'mihdan-index-now'),
					'term.auto_description' => __('Term description, or a generated fallback', 'mihdan-index-now'),
					'term.description'      => __('Term description only', 'mihdan-index-now'),
					'term.count'            => __('Number of items in the term', 'mihdan-index-now'),
				],
			],
			'author'  => [
				'label'     => __('Author', 'mihdan-index-now'),
				'variables' => [
					'author.display_name'     => __('Author display name', 'mihdan-index-now'),
					'author.auto_description' => __('Author biography, or a generated fallback', 'mihdan-index-now'),
					'author.first_name'       => __('Author first name', 'mihdan-index-now'),
					'author.last_name'        => __('Author last name', 'mihdan-index-now'),
					'author.posts_count'      => __('Number of posts by the author', 'mihdan-index-now'),
				],
			],
			'archive' => [
				'label'     => __('Archive', 'mihdan-index-now'),
				'variables' => [
					'post_type.plural_name' => __('Post type plural label', 'mihdan-index-now'),
					'post_type.name'        => __('Post type singular label', 'mihdan-index-now'),
					'post_type.description' => __('Post type description', 'mihdan-index-now'),
					'date.archive_title'    => __('Date archive title', 'mihdan-index-now'),
					'search.query'          => __('Search query', 'mihdan-index-now'),
					'search.results_count'  => __('Number of search results', 'mihdan-index-now'),
				],
			],
		];

		/**
		 * Filter the variables advertised in the settings UI.
		 *
		 * @param array $definitions Grouped variable definitions.
		 */
		return apply_filters('crawlwp_title_meta_variables', $definitions);
	}
}
