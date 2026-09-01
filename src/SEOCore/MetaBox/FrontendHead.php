<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

class FrontendHead
{
	public function __construct()
	{
		add_action('wp_head', [$this, 'output_meta_tags'], 1);
		add_action('template_redirect', [$this, 'handle_redirect']);
	}

	/**
	 * Handle per-post redirects.
	 */
	public function handle_redirect(): void
	{
		if (! is_singular()) {
			return;
		}

		$post_id = get_queried_object_id();

		if (! $post_id) {
			return;
		}

		$redirect_url  = MetaFields::get($post_id, MetaFields::REDIRECT_URL);
		$redirect_type = MetaFields::get($post_id, MetaFields::REDIRECT_TYPE, '301');

		if (empty($redirect_url)) {
			return;
		}

		$code = (int) $redirect_type;

		if ($code === 410) {
			status_header(410);
			nocache_headers();
			echo '<!DOCTYPE html><html><head><title>410 Gone</title></head><body><h1>410 Gone</h1><p>This content has been permanently removed.</p></body></html>';
			exit;
		}

		if (! in_array($code, [301, 302, 307], true)) {
			$code = 301;
		}

		wp_redirect(esc_url_raw($redirect_url), $code);
		exit;
	}

	public function output_meta_tags(): void
	{
		if (! is_singular()) {
			return;
		}

		$post_id = get_queried_object_id();

		if (! $post_id) {
			return;
		}

		$data      = MetaFields::get_all($post_id);
		$post      = get_post($post_id);
		$site_name = get_bloginfo('name');

		$seo_title = $this->resolve_variables(
			$data['seo_title'] ?: '{{title}} {{sep}} {{sitename}}',
			$post
		);

		$seo_desc = $this->resolve_variables(
			$data['seo_description'] ?: '{{excerpt}}',
			$post
		);

		$this->output_title_tag($seo_title);
		$this->output_description($seo_desc);
		$this->output_robots($data);
		$this->output_canonical($data, $post_id);
		$this->output_open_graph($data, $seo_title, $seo_desc, $post_id, $site_name);
		$this->output_twitter_card($data, $seo_title, $seo_desc, $post_id);
		$this->output_schema($data, $seo_title, $post);
	}

	private function resolve_variables(string $template, \WP_Post $post): string
	{
		$author_obj = get_userdata($post->post_author);
		$author     = $author_obj ? $author_obj->display_name : '';
		$terms      = get_the_terms($post->ID, 'category');
		$category   = (! empty($terms) && ! is_wp_error($terms)) ? $terms[0]->name : '';
		$excerpt    = $post->post_excerpt ?: wp_trim_words(wp_strip_all_tags($post->post_content), 30, '...');

		$tokens = [
			'{{title}}'       => $post->post_title,
			'{{sitename}}'    => get_bloginfo('name'),
			'{{sep}}'         => '—',
			'{{category}}'    => $category,
			'{{excerpt}}'     => $excerpt,
			'{{currentyear}}' => gmdate('Y'),
			'{{author}}'      => $author,
		];

		return str_replace(array_keys($tokens), array_values($tokens), $template);
	}

	private function output_title_tag(string $title): void
	{
		if ($title !== '') {
			echo '<title>' . esc_html($title) . '</title>' . "\n";
		}
	}

	private function output_description(string $desc): void
	{
		if ($desc !== '') {
			echo '<meta name="description" content="' . esc_attr($desc) . '" />' . "\n";
		}
	}

	private function output_robots(array $data): void
	{
		$directives = [];

		if ($data['robots_index'] === 'noindex') {
			$directives[] = 'noindex';
		}

		if ($data['robots_follow'] === 'nofollow') {
			$directives[] = 'nofollow';
		}

		$advanced = is_array($data['robots_advanced']) ? $data['robots_advanced'] : [];
		$allowed  = ['noimageindex', 'noarchive', 'nosnippet', 'notranslate'];

		foreach ($advanced as $directive) {
			if (in_array($directive, $allowed, true)) {
				$directives[] = $directive;
			}
		}

		if ($data['max_snippet'] === 'none') {
			$directives[] = 'max-snippet:0';
		} elseif ($data['max_snippet'] === '160') {
			$directives[] = 'max-snippet:160';
		}

		if ($data['max_image'] === 'standard') {
			$directives[] = 'max-image-preview:standard';
		} elseif ($data['max_image'] === 'none') {
			$directives[] = 'max-image-preview:none';
		}

		if (! empty($directives)) {
			echo '<meta name="robots" content="' . esc_attr(implode(', ', $directives)) . '" />' . "\n";
		}
	}

	private function output_canonical(array $data, int $post_id): void
	{
		$canonical = $data['canonical_url'] ?: get_permalink($post_id);
		echo '<link rel="canonical" href="' . esc_url($canonical) . '" />' . "\n";
	}

	private function output_open_graph(array $data, string $title, string $desc, int $post_id, string $site_name): void
	{
		$og_title = ($data['og_sync'] === '1') ? $title : ($data['og_title'] ?: $title);
		$og_desc  = ($data['og_sync'] === '1') ? $desc : ($data['og_description'] ?: $desc);

		echo '<meta property="og:type" content="article" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr($og_title) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr($og_desc) . '" />' . "\n";
		echo '<meta property="og:url" content="' . esc_url(get_permalink($post_id)) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr($site_name) . '" />' . "\n";

		$og_image = $this->get_image_url($data['og_image'], $post_id);
		if ($og_image) {
			echo '<meta property="og:image" content="' . esc_url($og_image) . '" />' . "\n";
		}
	}

	private function output_twitter_card(array $data, string $title, string $desc, int $post_id): void
	{
		$card_type = $data['x_card_type'] === 'summary' ? 'summary' : 'summary_large_image';

		$x_title = ($data['x_sync'] === '1')
			? (($data['og_sync'] === '1') ? $title : ($data['og_title'] ?: $title))
			: ($data['x_title'] ?: $title);

		$x_desc = ($data['x_sync'] === '1')
			? (($data['og_sync'] === '1') ? $desc : ($data['og_description'] ?: $desc))
			: ($data['x_description'] ?: $desc);

		echo '<meta name="twitter:card" content="' . esc_attr($card_type) . '" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr($x_title) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr($x_desc) . '" />' . "\n";

		if (! empty($data['x_creator'])) {
			echo '<meta name="twitter:creator" content="' . esc_attr($data['x_creator']) . '" />' . "\n";
		}

		$x_image = $data['x_image'] ? $this->get_image_url($data['x_image'], $post_id) : $this->get_image_url($data['og_image'], $post_id);
		if ($x_image) {
			echo '<meta name="twitter:image" content="' . esc_url($x_image) . '" />' . "\n";
		}
	}

	private function output_schema(array $data, string $title, \WP_Post $post): void
	{
		$schema_type = $data['schema_type'];

		if ($schema_type === 'none' || $schema_type === '') {
			return;
		}

		$headline = $data['schema_headline'] ?: $title;

		$author_obj  = get_userdata($post->post_author);
		$author_name = $author_obj ? $author_obj->display_name : '';

		$schema = [
			'@context' => 'https://schema.org',
			'@type'    => $schema_type,
			'headline' => $headline,
		];

		if (! empty($data['schema_section'])) {
			$schema['articleSection'] = $data['schema_section'];
		}

		$schema['author'] = [
			'@type' => 'Person',
			'name'  => $author_name,
		];

		$schema['datePublished'] = get_the_date('Y-m-d', $post);
		$schema['dateModified']  = get_the_modified_date('Y-m-d', $post);

		echo '<script type="application/ld+json">' . "\n";
		echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		echo "\n" . '</script>' . "\n";
	}

	private function get_image_url(mixed $image_id, int $post_id): string
	{
		if (! empty($image_id)) {
			$url = wp_get_attachment_image_url((int) $image_id, 'full');
			if ($url) {
				return $url;
			}
		}

		$thumbnail = get_the_post_thumbnail_url($post_id, 'full');

		return $thumbnail ?: '';
	}
}
