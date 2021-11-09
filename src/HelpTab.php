<?php
/**
 * HelpTab class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow;

use WP_Screen;

class HelpTab {
	public function setup_hooks() {
		//add_action( 'load-toplevel_page_' . MIHDAN_INDEX_NOW_SLUG, [ $this, 'add_tabs' ] );
	}

	/**
	 * @link
	 */
	public function add_tabs() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_yandex_webmaster_authorization',
				'title'    => __( 'Yandex Recrawl: Authorization', 'mihdan-index-now' ),
				'callback'  => function() {
					?>
					<h1>Authorization</h1>
					<p>In order to perform actions on Yandex.Webmaster on behalf of a particular user, a client application must be registered in the <a href="https://oauth.yandex.com/" target="_blank">Yandex</a>.OAuth service.</p>
					<p>Authorization uses the <a href="http://tools.ietf.org/html/draft-ietf-oauth-v2" target="_blank">OAuth 2.0</a> protocol. The interaction between the application and the OAuth server is shown on the <a href="https://tech.yandex.com/oauth/doc/dg/concepts/ya-oauth-intro.xml" target="_blank">OAuth implementation in Yandex</a> page.</p>
					<p>The user information is accepted as an access token in the <code>Authorization</code> HTTP header. The authorization procedure is described in the <a href="https://tech.yandex.com/oauth/doc/dg/reference/web-client-docpage/" target="_blank">Extract a token from the URL</a> section of the Yandex.OAuth documentation.</p>
					<p>If the user data is requested without an access token, the HTTP status 401 <code>Unauthorized</code> is returned.</p>
					<?php
				},
				'priority' => 10,
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_bing_webmaster',
				'title'    => __( 'Bing', 'mihdan-index-now' ),
				'callback'  => function() {
					?>
					<h1>Authorization</h1>
					<p>In order to perform actions on Yandex.Webmaster on behalf of a particular user, a client application must be registered in the Yandex.OAuth service.</p>
					<p>Authorization uses the OAuth 2.0 protocol. The interaction between the application and the OAuth server is shown on the OAuth implementation in Yandex page.</p>
					<p>The user information is accepted as an access token in the Authorization HTTP header. The authorization procedure is described in the Extract a token from the URL section of the Yandex.OAuth documentation.</p>
					<p>If the user data is requested without an access token, the HTTP status 401 Unauthorized is returned.</p>
					<?php
				},
				'priority' => 11,
			]
		);
	}
}
