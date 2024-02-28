<?php
/**
 * HelpTab class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

use Mihdan\IndexNow\Utils;

/**
 * @todo translate strings
 *
 * Class HelpTab.
 *
 * @package mihdan-index-now
 */
class HelpTab {
	/**
	 * Setup hooks.
	 */
	public function setup_hooks() {
		add_action( 'load-toplevel_page_' . MIHDAN_INDEX_NOW_SLUG, [ $this, 'add_tabs' ] );
	}

	/**
	 * Add custom tabs.
	 */
	public function add_tabs() {
		$screen = get_current_screen();

		if ( ! $screen ) {
			return;
		}

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_search_engine_support',
				'title'    => __( 'Search engine support', 'mihdan-index-now' ),
				'callback' => function () {
					?>
					<h1><?php esc_html_e( 'About IndexNow', 'mihdan-index-now' ); ?></h1>
					<p><?php esc_html_e( 'IndexNow is an easy way for websites owners to instantly inform search engines about latest content changes on their website', 'mihdan-index-now' ); ?></p>
					<p><?php printf(
							esc_html__( 'As of today %s, the protocol is officially supported only by Microsoft Bing, Naver, Seznam.cz and Yandex. %sLearn more%s', 'mihdan-index-now' ),
							date( 'd.m.Y' ), '<a href="https://www.indexnow.org/" target="_blank">', '</a>'
						); ?>
					</p>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_index_now_api_key',
				'title'    => __( 'IndexNow API key', 'mihdan-index-now' ),
				'callback' => function () {
					?>
					<h1><?php esc_html_e( 'IndexNow API Key', 'mihdan-index-now' ); ?></h1>
					<p><?php esc_html_e( 'To submit URLs for indexing via IndexNow, you must confirm that you are the owner of the site. For confirmation, a special key is used that is hosted on your website server. Our plugin automatically handles this for you. You only need to come up a key or use the one suggested by our plugin directly in the API Key field.', 'mihdan-index-now' ); ?></p>
					<h2><?php esc_html_e( 'Key Requirement', 'mihdan-index-now' ); ?></h2>
					<ul>
						<li><?php esc_html_e( 'The minimum number of characters in the key is — 8, the maximum is — 128', 'mihdan-index-now' ); ?></li>
						<li><?php printf(
								esc_html__( 'The key can contain the characters %1$sa-z%2$s, %1$sA-Z%2$s, %1$s0-9%2$s, %1$s-%2$s, -', 'mihdan-index-now' ),
								'<code>', '</code>'
							); ?>
						</li>
					</ul>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_bing_webmaster_api_key',
				'title'    => __( 'Bing API key', 'mihdan-index-now' ),
				'callback' => function () {
					?>
					<h1>Bing API key</h1>
					<p>An API key is a unique identifier that is used to authenticate API requests.</p>
					<p>The Bing API key can be obtained through the <a href="https://www.bing.com/webmasters/home"
																	   target="_blank">Bing Webmaster Tools</a> panel under Settings -> API Access -> API Key.
					</p>
					<p>Warning: Do not give the API key to third parties or those you do not trust.</p>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_yandex_webmaster_authorization',
				'title'    => __( 'Yandex Recrawl: Authorization', 'mihdan-index-now' ),
				'callback' => function () {
					?>
					<div class="wposa__helptab">
						<h1><?php esc_html_e( 'Authorization', 'mihdan-index-now' ); ?></h1>
						<p><?php esc_html_e( 'Sending requests to crawl a site by the Yandex robot is only available for sites with https.', 'mihdan-index-now' ); ?></p>
						<h2>Step 1</h2>
						<p>To perform actions on Yandex.Webmaster on behalf of a specific user, the client application must be registered with the Yandex ID service.</p>
						<p>
							<a href="https://oauth.yandex.com/client/new" target="_blank" class="button">Application Registration</a>
						</p>
						<p>When registering a new application, fill in the fields as follows:</p>
						<table class="wposa__table">
							<tr>
								<th>Service name:</th>
								<td>Enter any name so that you can later find it in the list of applications. You can simply write
									<code>IndexNow</code>.
								</td>
							</tr>
							<tr>
								<th>Service icon:</th>
								<td>Optional field. If desired, attach any pictures.</td>
							</tr>
							<tr>
								<th>Application platforms:</th>
								<td>Select <code>Web services</code></td>
							</tr>
							<tr>
								<th>Redirect URI:</th>
								<td>Enter the following URL:<br/><code><?php echo esc_url( admin_url( 'admin.php?page=' . MIHDAN_INDEX_NOW_SLUG ) ); ?></code>.
								</td>
							</tr>
							<tr>
								<th>Data access:</th>
								<td>
									Enter the word
									<code>webmaster</code> in the field and select the following permissions:
									<ol>
										<li>
											Obtaining information about external links to site<br/>
											<code>webmaster:hostinfo</code>
										</li>
										<li>
											Adding sites to Yandex.Webmaster and receiving indexing status information
											<br/> <code>webmaster:verify</code>
										</li>
									</ol>
								</td>
							</tr>
						</table>
						<h2>Step 2</h2>
						<p>After registering a new application, you will receive the following details:
							<code>ClientID</code> and
							<code>Client secret</code>. Enter this data into the appropriate fields in the plugin settings and click the
							<code>Save</code> button to enable the button to receive an authorization token.</p>
						<h2>Step 3</h2>
						<p>Click on the
							<code>Get token</code> button and you will be redirected to the Yandex website, where you will have to grant access to the webmaster panel for your newly created application. After confirmation, you will be redirected to the plugin settings page.
						</p>
						<h2>Step 4</h2>
						<p>In the
							<code>Host ID</code> settings, select a domain that completely matches the current website (protocol, port, www or without www) and click the
							<code>Save</code> button.</p>
						<h2>Step 5</h2>
						<p>Don't forget to enable the Yandex API integration. Information about your key and its lifetime is indicated next to the
							<code>Update Token</code> button.</p>
					</div>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_google_webmaster_json_key',
				'title'    => __( 'Google API', 'mihdan-index-now' ),
				'callback' => function () {
					?>
					<h1>Google API</h1>

					<ul>
						<li>
							<a href="#steps-to-create-an-indexing-api-project">1. Steps to Create an Indexing API Project</a>
							<ul>
								<li>
									<a href="#go-to-the-google-api-console-and-create-a-new-project">1.1 Go to the Google API Console and create a new project</a>
								</li>
								<li><a href="#now-create-a-service-account">1.2 Now create a Service Account</a></li>
								<li>
									<a href="#add-the-service-account-as-an-owner-of-your-google-search-console-property">1.3 Add the Service Account as an owner of your Google Search Console Property</a>
								</li>
							</ul>
						</li>
						<li>
							<a href="#configure-the-plugin">2. Configure the Plugin</a>
							<ul>
								<li>
									<a href="#insert-your-api-key-in-the-plugin-settings">2.1 Insert your API Key in the plugin settings</a>
								</li>
							</ul>
						</li>
					</ul>

					<h2 id="steps-to-create-an-indexing-api-project">1. Steps to Create an Indexing API Project</h2>

					<h3 id="go-to-the-google-api-console-and-create-a-new-project">1.1 Go to the Google API Console and create a new project</h3>
					<p>Ensure that you’re creating a new <i>Indexing API</i> project which you can do automatically by
						<a href="https://console.developers.google.com/flows/enableapi?apiid=indexing.googleapis.com&credential=client_key"
						   target="_blank">clicking here</a>. And then click <b>continue</b>.</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/create-google-api-project.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>If subsequent to clicking
						<b>Continue</b>, you see the following screen, then you’ve successfully created the project:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/google-api-project-created.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Please note: There is no need to click ‘Go to Credentials’ button. You can close this tab.</p>

					<h3 id="now-create-a-service-account">1.2 Now create a Service Account</h3>
					<p>Once you’ve created your project, create a service account by opening the <a
							href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">service accounts page</a>. You will first be prompted to select the API project you wish to create this service account in (the one created in the previous step).
					</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/select-service-account-project-2.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>After selecting the project you wish to create a service account for, you’ll be taken to the following page, where you simply need to click the Create Service Account button highlighted below:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/create-service-account.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>On the
						<b>Create service account</b> screen, enter a name and description for the newly created service account.
					</p>
					<p>Select and copy the whole
						<b>Service Account ID</b> (the one that looks like an email address) because you will need it later. Then, click on the
						<b>Create</b> button at the bottom:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/indexing-api-service-account-information-2.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Click Create and Continue to proceed to the next step, where you need to change the role to Owner and, as you might’ve guessed, click continue once again…</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/set-service-account-permissions-owner.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Once you’ve set the role to
						<b>Owner</b> as shown above, simply click continue to save that change and then click done.</p>
					<p>You will then be able to download the file that contains your
						<b>API key</b>. To do so, simply click the three vertical dots in the
						<b>Actions</b> column, and then select <b>Manage keys</b> as shown below:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/manage-api-keys-google.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>You will then be taken to the following page when you can click
						<b>Add Key</b> and then select the <b>Create new key</b> option, as shown below:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/create-new-service-account-key.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Choose the default <b>JSON</b> format when prompted in the overlay, and click <b>Create</b>:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/10-service-account-key-type.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Upon clicking
						<b>Create</b> the .json file will be automatically downloaded in your browser meaning you’ve successfully created the API key & can proceed to the next step…
					</p>

					<h3 id="add-the-service-account-as-an-owner-of-your-google-search-console-property">1.3 Add the Service Account as an owner of your Google Search Console Property</h3>
					<p>To do this, you’ll need to register and verify your website with the Google Search Console (if you haven’t done so already) which is super easy: just follow the
						<a
							href="https://support.google.com/webmasters/answer/9008080" target="_blank">recommended steps</a> to verify ownership of your property.
					</p>
					<p>After verifying your property, open the <a href="https://search.google.com/search-console"
																  target="_blank">Google Search Console</a>, select your property on the left (if prompted), and then click on
						<b>Settings</b> near the bottom:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Open-settings-in-Google-search-console.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Click on <b>Users and Permissions</b>:</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Choose-Users-and-Permissions-in-Google-Search-Console-settings.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Click on the three dots next to your account, and then click on <b>Add User</b>.</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Add-User-in-Users-and-Permissions.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>A popup will now appear. Enter the
						<b>Service account ID</b> (the one you copied out earlier) in the
						<b>Email address</b> field. Ensure that you’ve provided <b>Owner</b> level
						<b>Permission</b> and then click <b>Add</b>.</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Add-service-account-ID-as-owner.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>Now in a few moments, you should see the Service account listed as a new Owner.</p>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Google-Service-account-added-to-Google-Search-Console-users.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<p>You can use a single <b>Project</b>, <b>Service Account</b>, and
						<b>JSON API Key</b> across multiple sites, just make sure that the Service Account is added as Owner for all the sites in the Search Console.
					</p>

					<h2 id="configure-the-plugin">2. Configure the Plugin</h2>

					<h3 id="insert-your-api-key-in-the-plugin-settings">2.1 Insert your API Key in the plugin settings</h3>
					<p><img class="wposa-img"
							src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/insert-your-api-key-in-the-plugin-settings.jpg' ) ); ?>"
							width="100%" alt=""/></p>
					<?php
				},
				'priority' => 12,
			]
		);


	}
}
