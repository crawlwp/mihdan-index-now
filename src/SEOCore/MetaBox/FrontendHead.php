<?php

namespace Mihdan\IndexNow\SEOCore\MetaBox;

/**
 * Front end behaviour driven by the per-post SEO metabox.
 *
 * Meta tag output lives in {@see \Mihdan\IndexNow\SEOCore\TitleMeta\FrontendOutput},
 * which resolves the per-post values registered here together with the global
 * defaults configured under Title & Meta. Keeping a single writer avoids
 * duplicated title, description and Open Graph tags.
 */
class FrontendHead
{
	public function __construct()
	{
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

		$redirect_url  = (string) MetaFields::get($post_id, MetaFields::REDIRECT_URL);
		$redirect_type = MetaFields::get($post_id, MetaFields::REDIRECT_TYPE, '301');

		$code = (int) $redirect_type;

		// 410 does not need a destination — the URL field may legitimately be empty.
		if ($code === 410) {
			nocache_headers();
			status_header(410);
			echo '<!DOCTYPE html><html><head><title>410 Gone</title></head><body><h1>410 Gone</h1><p>This content has been permanently removed.</p></body></html>';
			exit;
		}

		if ($redirect_url === '') {
			return;
		}

		// Re-validate on output: absolute http(s) URL with a host, or a single-slash relative path.
		$redirect_url = MetaFields::sanitize_url($redirect_url);

		if ($redirect_url === '') {
			return;
		}

		if (! in_array($code, [301, 302, 307], true)) {
			$code = 301;
		}

		// Loop guard: never redirect a URL to itself.
		$target_url  = untrailingslashit($redirect_url[0] === '/' ? home_url($redirect_url) : $redirect_url);
		$request_uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';
		$request_url = untrailingslashit((is_ssl() ? 'https://' : 'http://') . wp_parse_url(home_url(), PHP_URL_HOST) . $request_uri);

		if ($target_url === $request_url || $target_url === untrailingslashit((string) get_permalink($post_id))) {
			return;
		}

		wp_redirect($redirect_url, $code, 'CrawlWP');
		exit;
	}
}
