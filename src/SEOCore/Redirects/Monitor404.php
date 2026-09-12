<?php

namespace Mihdan\IndexNow\SEOCore\Redirects;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;
use Mihdan\IndexNow\Utils;
use Mihdan\IndexNow\Views\WPOSA;

/**
 * Logs 404 hits and offers one-click redirect creation.
 */
class Monitor404
{
	const DB_VERSION = '1.0';
	const SECTION    = 'monitor_404';

	/** @var RedirectsManager */
	private RedirectsManager $manager;

	private string $table;

	public function __construct(RedirectsManager $manager)
	{
		global $wpdb;

		$this->manager = $manager;
		$this->table   = $wpdb->prefix . 'crawlwp_404_log';

		add_action('plugins_loaded', [$this, 'maybe_install_table'], 2);
		$this->maybe_install_table();
		add_action('template_redirect', [$this, 'log'], 0);
		add_action('crawlwp_setup_fields', [$this, 'settings_fields'], 26, 2);

		add_action('wp_ajax_crawlwp_404_list', [$this, 'ajax_list']);
		add_action('wp_ajax_crawlwp_404_redirect', [$this, 'ajax_redirect']);
		add_action('wp_ajax_crawlwp_404_delete', [$this, 'ajax_delete']);
		add_action('wp_ajax_crawlwp_404_clear', [$this, 'ajax_clear']);
	}

	public function maybe_install_table(): void
	{
		if (get_option('crawlwp_404_db_version') === self::DB_VERSION) {
			return;
		}

		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			url varchar(2048) NOT NULL DEFAULT '',
			referer varchar(2048) NOT NULL DEFAULT '',
			hits bigint(20) NOT NULL DEFAULT 1,
			last_seen datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY url (url(191))
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);
		update_option('crawlwp_404_db_version', self::DB_VERSION);
	}

	public function log(): void
	{
		if (is_admin() || wp_doing_ajax() || wp_doing_cron() || ! is_404()) {
			return;
		}

		if (self::get('enabled', 'on') === 'off') {
			return;
		}

		$uri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

		if ($uri === '' || preg_match('#\.(css|js|map|png|jpe?g|gif|svg|webp|ico|woff2?|ttf|eot|mp4|zip)$#i', $uri)) {
			return;
		}

		$path = (string) (wp_parse_url($uri, PHP_URL_PATH) ?: $uri);
		$path = '/' . ltrim($path, '/');

		$referer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw(wp_unslash($_SERVER['HTTP_REFERER'])) : '';

		global $wpdb;

		$existing = $wpdb->get_var($wpdb->prepare(
			"SELECT id FROM {$this->table} WHERE url = %s LIMIT 1",
			$path
		));

		if ($existing) {
			$wpdb->query($wpdb->prepare(
				"UPDATE {$this->table} SET hits = hits + 1, last_seen = %s, referer = %s WHERE id = %d",
				current_time('mysql', true),
				$referer,
				(int) $existing
			));
			return;
		}

		$wpdb->insert($this->table, [
			'url'        => $path,
			'referer'    => $referer,
			'hits'       => 1,
			'last_seen'  => current_time('mysql', true),
			'created_at' => current_time('mysql', true),
		]);
	}

	public function settings_fields(WPOSA $wposa, $settingsInstance): void
	{
		if ($wposa->get_active_header_menu() !== Utils::get_plugin_prefix() . '_advanced_settings') {
			return;
		}

		$wposa->add_section([
			'header_menu_id' => 'advanced_settings',
			'id'             => self::SECTION,
			'title'          => __('404 Monitor', 'mihdan-index-now'),
			'desc'           => __('Log 404 hits and create a redirect in one click.', 'mihdan-index-now'),
		]);

		$wposa->add_field(self::SECTION, [
			'id'      => 'enabled',
			'type'    => 'switch',
			'name'    => __('Log 404 errors', 'mihdan-index-now'),
			'default' => 'on',
		]);

		$wposa->add_field(self::SECTION, [
			'id'   => 'manager_ui',
			'type' => 'html',
			'name' => '',
			'desc' => $this->render_ui(),
		]);
	}

	public function ajax_list(): void
	{
		$this->guard();

		global $wpdb;
		$rows = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY hits DESC, last_seen DESC LIMIT 100");

		ob_start();
		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__('URL', 'mihdan-index-now') . '</th>';
		echo '<th>' . esc_html__('Hits', 'mihdan-index-now') . '</th>';
		echo '<th>' . esc_html__('Last seen', 'mihdan-index-now') . '</th>';
		echo '<th>' . esc_html__('Redirect to', 'mihdan-index-now') . '</th>';
		echo '</tr></thead><tbody>';

		if (! $rows) {
			echo '<tr><td colspan="4">' . esc_html__('No 404s logged yet.', 'mihdan-index-now') . '</td></tr>';
		} else {
			foreach ($rows as $row) {
				echo '<tr data-id="' . esc_attr((string) $row->id) . '">';
				echo '<td><code>' . esc_html($row->url) . '</code></td>';
				echo '<td>' . esc_html((string) $row->hits) . '</td>';
				echo '<td>' . esc_html((string) $row->last_seen) . '</td>';
				echo '<td><input type="text" class="cwp-404-to regular-text" placeholder="/new-url"> ';
				echo '<button type="button" class="button cwp-404-save">' . esc_html__('Redirect', 'mihdan-index-now') . '</button> ';
				echo '<button type="button" class="button-link-delete cwp-404-del">' . esc_html__('Delete', 'mihdan-index-now') . '</button></td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '<p><button type="button" class="button" id="cwp404Clear">' . esc_html__('Clear log', 'mihdan-index-now') . '</button></p>';

		wp_send_json_success(['html' => ob_get_clean()]);
	}

	public function ajax_redirect(): void
	{
		$this->guard();

		$id  = isset($_POST['id']) ? absint($_POST['id']) : 0;
		$to  = isset($_POST['to']) ? MetaFields::sanitize_url(wp_unslash($_POST['to'])) : '';

		global $wpdb;
		$row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));

		if (! $row || $to === '') {
			wp_send_json_error(['message' => __('Missing URL.', 'mihdan-index-now')]);
		}

		$ok = $this->manager->insert([
			'from_url'            => $row->url,
			'to_url'              => $to,
			'redirect_type'       => 301,
			'match_type'          => 'exact',
			'note'                => __('Created from 404 monitor', 'mihdan-index-now'),
			'ignore_query_string' => 1,
			'enabled'             => 1,
		]);

		if (! $ok) {
			wp_send_json_error(['message' => __('Could not create redirect.', 'mihdan-index-now')]);
		}

		$wpdb->delete($this->table, ['id' => $id], ['%d']);
		wp_send_json_success();
	}

	public function ajax_delete(): void
	{
		$this->guard();
		global $wpdb;
		$wpdb->delete($this->table, ['id' => absint($_POST['id'] ?? 0)], ['%d']);
		wp_send_json_success();
	}

	public function ajax_clear(): void
	{
		$this->guard();
		global $wpdb;
		$wpdb->query("TRUNCATE TABLE {$this->table}");
		wp_send_json_success();
	}

	private function guard(): void
	{
		if (! current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Permission denied.', 'mihdan-index-now')], 403);
		}
		check_ajax_referer('crawlwp_404_nonce', 'nonce');
	}

	private function render_ui(): string
	{
		$nonce = wp_create_nonce('crawlwp_404_nonce');
		ob_start();
		?>
		<div id="cwp404" data-nonce="<?php echo esc_attr($nonce); ?>">
			<p><button type="button" class="button" id="cwp404Refresh"><?php esc_html_e('Load 404 log', 'mihdan-index-now'); ?></button></p>
			<div id="cwp404Table"></div>
		</div>
		<script>
		(function ($) {
			function load() {
				$.post(ajaxurl, { action: 'crawlwp_404_list', nonce: $('#cwp404').data('nonce') }).done(function (res) {
					if (res && res.success) { $('#cwp404Table').html(res.data.html); }
				});
			}
			$(document).on('click', '#cwp404Refresh', load);
			$(document).on('click', '.cwp-404-save', function () {
				var $tr = $(this).closest('tr');
				$.post(ajaxurl, {
					action: 'crawlwp_404_redirect',
					nonce: $('#cwp404').data('nonce'),
					id: $tr.data('id'),
					to: $tr.find('.cwp-404-to').val()
				}).done(load);
			});
			$(document).on('click', '.cwp-404-del', function () {
				var $tr = $(this).closest('tr');
				$.post(ajaxurl, { action: 'crawlwp_404_delete', nonce: $('#cwp404').data('nonce'), id: $tr.data('id') }).done(load);
			});
			$(document).on('click', '#cwp404Clear', function () {
				if (!window.confirm('Clear the 404 log?')) return;
				$.post(ajaxurl, { action: 'crawlwp_404_clear', nonce: $('#cwp404').data('nonce') }).done(load);
			});
		}(jQuery));
		</script>
		<?php
		return (string) ob_get_clean();
	}

	public static function get(string $field, $default = '')
	{
		$options = get_option('crawlwp_' . self::SECTION, []);
		return is_array($options) ? ($options[$field] ?? $default) : $default;
	}
}
