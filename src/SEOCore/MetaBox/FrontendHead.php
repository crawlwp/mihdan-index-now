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
}
