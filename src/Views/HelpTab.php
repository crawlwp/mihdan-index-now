<?php
/**
 * HelpTab class.
 *
 * @package mihdan-index-now
 */

namespace Mihdan\IndexNow\Views;

use WP_Screen;

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
				'id'       => MIHDAN_INDEX_NOW_PREFIX . '_search_engine_support',
				'title'    => __( 'Search engine support', 'mihdan-index-now' ),
				'callback' => function() {
					?>
					<h1>Поддержка IndexNow</h1>
					<p>На сегодняшний день (10.11.2021) официально технологию поддерживают только Яндекс и Bing, остальные поисковые системы добавлены заранее.</p>
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
					<h1>Ключ для Bing API</h1>
					<p>Ключ API — это уникальный идентификатор, который используется для аутентификации запросов API.</p>
					<p>Ключ для доступа к API Bing можно получить через панель <a href="https://www.bing.com/webmasters/home" target="_blank">Bing Webmaster Tools</a> в разделе Настройки -> Доступ по API -> Ключ API.</p>
					<p>Предупреждение: Не давайте ключ API третьим лицам или тем, кому не доверяете.</p>
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
					<h1>Авторизация</h1>
					<p>Отправка запросов на перееобход сайта роботом Яндекс доступна <b>только</b> для сайтов с <b>https</b>.</p>
					<p>Для выполнения действий на Яндекс.Вебмастере от имени определенного пользователя клиентское приложение должно быть зарегистрировано на сервисе Яндекс.OAuth. <a href="https://oauth.yandex.ru/client/new" target="_blank" class="button">Зарегистрировать новое приложение</a>.</p>
					<p>При регистрации приложения в блоке Яндекс.Вебмастер (webmaster) выберите <b>все типы доступов</b>.</p>
					<p>В качестве <b>Callback URI #2</b> укажите <code><?php echo esc_url( admin_url( 'admin.php?page=' . MIHDAN_INDEX_NOW_SLUG ) ); ?></code></p>
					<p>После регистрации нового приложения вы получите <b>ID</b> приложения и его <b>Пароль</b>. ID приложения впишите в поле <b>App ID</b>, а Пароль приложения - в поле <b>App Password</b> и нажмите кнопку <b>Save changes</b> для получения токена авторизации.</p>
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




	}
}
