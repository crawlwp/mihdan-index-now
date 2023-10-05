<?php
/**
 * HelpTab class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

use Mihdan\IndexNow\Utils;

/**
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
				'callback' => function() {
					?>
					<h1>Поддержка IndexNow</h1>
					<p>На сегодняшний день <?php echo esc_html( date( 'd.m.Y' ) ); ?> официально технологию поддерживают только <i>Яндекс</i>, <i>Bing</i>, <i>Seznam.cz</i>, <i>Naver</i> и сам официальный сайт <i>indexnow.org</i>, остальные поисковые системы пока морозятся.</p>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_index_now_api_key',
				'title'    => __( 'IndexNow API key', 'mihdan-index-now' ),
				'callback' => function() {
					?>
					<h1>Ключ для IndexNow</h1>
					<p>Для отправки URL-адресов необходимо подтвердить, что именно вы являетесь владельцем сайта, для которого передаются данные. Для подтверждения используется специальный ключ — его нужно придумать или использовать тот, что предложил наш плагин прямо в поле API Key. При каждом запросе к API Яндекс проверяет ключ.</p>
					<h2>Требования к ключу</h2>
					<ul>
						<li>Поддерживается только кодировка UTF-8</li>
						<li>Минимальное количество символов в ключе — 8, максимальное — 128</li>
						<li>Ключ может содержать символы <code>a-z</code>, <code>A-Z</code> , <code>0-9</code>, <code>-</code></li>
					</ul>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_bing_webmaster_api_key',
				'title'    => __( 'Bing API key', 'mihdan-index-now' ),
				'callback' => function() {
					?>
					<h1>Bing API key</h1>
					<p>An API key is a unique identifier that is used to authenticate API requests.</p>
					<p>The Bing API key can be obtained through the <a href="https://www.bing.com/webmasters/home" target="_blank">Bing Webmaster Tools</a> panel under Settings -> API Access -> API Key.</p>
					<p>Warning: Do not give the API key to third parties or those you do not trust.</p>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_yandex_webmaster_authorization',
				'title'    => __( 'Yandex Recrawl: Authorization', 'mihdan-index-now' ),
				'callback' => function() {
					?>
					<div class="wposa__helptab">
						<h1>Авторизация</h1>
						<p>Отправка запросов на перееобход сайта роботом Яндекс доступна <b>только</b> для сайтов с <b>https</b>.</p>
						<h2>Шаг 1</h2>
						<p>Для выполнения действий на Яндекс.Вебмастере от имени определенного пользователя клиентское приложение должно быть зарегистрировано на сервисе Яндекс ID.</p>
						<p><a href="https://oauth.yandex.ru/client/new" target="_blank" class="button">Регистрация приложения</a></p>
						<p>При регистрации нового приложения заполняем поля следующим образом:</p>
						<table class="wposa__table">
							<tr>
								<th>Название вашего сервиса:</th>
								<td>Здесь вписываем любое название, чтобы вы сами потом могли найти его в списке приложений. Можно просто написать <code>IndexNow</code>.</td>
							</tr>
							<tr>
								<th>Иконка сервиса:</th>
								<td>Необязательное поле. При желании прикрепляем любую свою картинку.</td>
							</tr>
							<tr>
								<th>Платформы приложения:</th>
								<td>Выбираем <code>Веб-сервисы</code></td>
							</tr>
							<tr>
								<th>Redirect URI:</th>
								<td>В появившемся поле вписываем следующую ссылку:<br /><code><?php echo esc_url( admin_url( 'admin.php?page=' . MIHDAN_INDEX_NOW_SLUG ) ); ?></code>.</td>
							</tr>
							<tr>
								<th>Доступ к данным:</th>
								<td>
									Здесь в поле вписываем слово <code>webmaster</code> и в подсказках выбираем следующие права:
									<ol>
										<li>
											Получение информации о внешних ссылках на сайт <br />
											<code>webmaster:hostinfo</code>
										</li>
										<li>
											Добавление сайтов в Яндекс.Вебмастер, получение информации о статусе индексирования <br />
											<code>webmaster:verify</code>
										</li>
									</ol>
								</td>
							</tr>
						</table>
						<h2>Шаг 2</h2>
						<p>После регистрации нового приложения вы получите следующие реквизиты: <code>ClientID</code> и <code>Client secret</code>. Эти данные впишите в соответствующие поля в настройках плагина и нажмите кнопку <code>Сохранить</code> для активации кнопки получения токена авторизации.</p>
						<h2>Шаг 3</h2>
						<p>Нажмите на кнопку <code>Получить токен</code>, сработает перенаправление на сайт Яндекс, где вы должны будете подтвердить доступы к панели вебмастера для вашего только что созданного приложения. После подтверждения вы снова вернётесь на страницу настроек плагина.</p>
						<h2>Шаг 4</h2>
						<p>Выберите в блоке <code>Сайт</code> домен, который полностью совпадает с текущим сайтом (протокол, порт, www или без www) и нажмите кнопку <code>Сохранить</code>.</p>
						<h2>Шаг 5</h2>
						<p>Поставьте галочку напротив блока <code>Включить</code>.</p>
						<p>Информация о вашем ключе и сроке его жизни указана возле кнопки <code>Обновить токен</code>.</p>
					</div>
					<?php
				},
			]
		);

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_bing_webmaster',
				'title'    => __( 'Bing', 'mihdan-index-now' ),
				'callback' => function() {
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

		$screen->add_help_tab(
			[
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_google_webmaster_json_key',
				'title'    => __( 'Google API', 'mihdan-index-now' ),
				'callback' => function() {
					?>
					<h1>Google API</h1>

					<ul>
						<li>
							<a href="#steps-to-create-an-indexing-api-project">1. Steps to Create an Indexing API Project</a>
							<ul>
								<li><a href="#go-to-the-google-api-console-and-create-a-new-project">1.1 Go to the Google API Console and create a new project</a></li>
								<li><a href="#now-create-a-service-account">1.2 Now create a Service Account</a></li>
								<li><a href="#add-the-service-account-as-an-owner-of-your-google-search-console-property">1.3 Add the Service Account as an owner of your Google Search Console Property</a></li>
							</ul>
						</li>
						<li>
							<a href="#configure-the-plugin">2. Configure the Plugin</a>
							<ul>
								<li><a href="#insert-your-api-key-in-the-plugin-settings">2.1 Insert your API Key in the plugin settings</a></li>
							</ul>
						</li>
					</ul>

					<h2 id="steps-to-create-an-indexing-api-project">1. Steps to Create an Indexing API Project</h2>

					<h3 id="go-to-the-google-api-console-and-create-a-new-project">1.1 Go to the Google API Console and create a new project</h3>
					<p>Ensure that you’re creating a new <i>Indexing API</i> project which you can do automatically by <a href="https://console.developers.google.com/flows/enableapi?apiid=indexing.googleapis.com&credential=client_key" target="_blank">clicking here</a>. And then click <b>continue</b>.</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/create-google-api-project.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>If subsequent to clicking <b>Continue</b>, you see the following screen, then you’ve successfully created the project:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/google-api-project-created.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Please note: There is no need to click ‘Go to Credentials’ button. You can close this tab.</p>

					<h3 id="now-create-a-service-account">1.2 Now create a Service Account</h3>
					<p>Once you’ve created your project, create a service account by opening the <a href="https://console.cloud.google.com/iam-admin/serviceaccounts" target="_blank">service accounts page</a>. You will first be prompted to select the API project you wish to create this service account in (the one created in the previous step).</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/select-service-account-project-2.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>After selecting the project you wish to create a service account for, you’ll be taken to the following page, where you simply need to click the Create Service Account button highlighted below:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/create-service-account.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>On the <b>Create service account</b> screen, enter a name and description for the newly created service account.</p>
					<p>Select and copy the whole <b>Service Account ID</b> (the one that looks like an email address) because you will need it later. Then, click on the <b>Create</b> button at the bottom:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/indexing-api-service-account-information-2.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Click Create and Continue to proceed to the next step, where you need to change the role to Owner and, as you might’ve guessed, click continue once again…</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/set-service-account-permissions-owner.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Once you’ve set the role to <b>Owner</b> as shown above, simply click continue to save that change and then click done.</p>
					<p>You will then be able to download the file that contains your <b>API key</b>. To do so, simply click the three vertical dots in the <b>Actions</b> column, and then select <b>Manage keys</b> as shown below:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/manage-api-keys-google.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>You will then be taken to the following page when you can click <b>Add Key</b> and then select the <b>Create new key</b> option, as shown below:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/create-new-service-account-key.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Choose the default <b>JSON</b> format when prompted in the overlay, and click <b>Create</b>:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/10-service-account-key-type.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Upon clicking <b>Create</b> the .json file will be automatically downloaded in your browser meaning you’ve successfully created the API key & can proceed to the next step…</p>

					<h3 id="add-the-service-account-as-an-owner-of-your-google-search-console-property">1.3 Add the Service Account as an owner of your Google Search Console Property</h3>
					<p>To do this, you’ll need to register and verify your website with the Google Search Console (if you haven’t done so already) which is super easy: just follow the <a href="https://support.google.com/webmasters/answer/9008080" target="_blank">recommended steps</a> to verify ownership of your property.</p>
					<p>After verifying your property, open the <a href="https://search.google.com/search-console" target="_blank">Google Search Console</a>, select your property on the left (if prompted), and then click on <b>Settings</b> near the bottom:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Open-settings-in-Google-search-console.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Click on <b>Users and Permissions</b>:</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Choose-Users-and-Permissions-in-Google-Search-Console-settings.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Click on the three dots next to your account, and then click on <b>Add User</b>.</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Add-User-in-Users-and-Permissions.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>A popup will now appear. Enter the <b>Service account ID</b> (the one you copied out earlier) in the <b>Email address</b> field. Ensure that you’ve provided <b>Owner</b> level <b>Permission</b> and then click <b>Add</b>.</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Add-service-account-ID-as-owner.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>Now in a few moments, you should see the Service account listed as a new Owner.</p>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/Google-Service-account-added-to-Google-Search-Console-users.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<p>You can use a single <b>Project</b>, <b>Service Account</b>, and <b>JSON API Key</b> across multiple sites, just make sure that the Service Account is added as Owner for all the sites in the Search Console.</p>

					<h2 id="configure-the-plugin">2. Configure the Plugin</h2>

					<h3 id="insert-your-api-key-in-the-plugin-settings">2.1 Insert your API Key in the plugin settings</h3>
					<p><img class="wposa-img" src="<?php echo esc_url( Utils::get_plugin_asset_url( 'images/insert-your-api-key-in-the-plugin-settings.jpg' ) ); ?>" width="100%"  alt=""/></p>
					<?php
				},
				'priority' => 12,
			]
		);


	}
}
