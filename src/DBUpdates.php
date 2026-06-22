<?php

namespace Mihdan\IndexNow;

class DBUpdates
{
	public static $instance;

	const DB_VER = 1;

	public function init_options()
	{
		add_option('crawlwp_lite_db_ver', 0);
	}

	public function maybe_update()
	{
		$this->init_options();

		if (get_option('crawlwp_lite_db_ver', 0) >= self::DB_VER) {
			return;
		}

		$this->update();
	}

	public function update()
	{
		// no PHP timeout for running updates
		if (function_exists('set_time_limit') && false === strpos(ini_get('disable_functions'), 'set_time_limit') && !ini_get('safe_mode')) {
			@set_time_limit(0);
		}

		// this is the current database schema version number
		$current_db_ver = get_option('crawlwp_lite_db_ver');

		// this is the target version that we need to reach
		$target_db_ver = self::DB_VER;

		// run update routines one by one until the current version number
		// reaches the target version number
		while ($current_db_ver < $target_db_ver) {
			// increment the current db_ver by one
			$current_db_ver++;

			// each db version will require a separate update function
			$update_method = "update_routine_{$current_db_ver}";

			if (method_exists($this, $update_method)) {
				call_user_func(array($this, $update_method));
			}
		}

		// update the option in the database, so that this process can always
		// pick up where it left off
		update_option('crawlwp_lite_db_ver', $current_db_ver);
	}

	protected function update_routine_1()
	{
		global $wpdb;

		delete_option('mihdan_index_now_version');

		$options_map = [
			'mihdan_index_now_general' => 'crawlwp_general',
			'mihdan_index_now_index_now' => 'crawlwp_index_now',
			'mihdan_index_now_bing_webmaster' => 'crawlwp_bing_webmaster',
			'mihdan_index_now_google_webmaster' => 'crawlwp_google_webmaster',
			'mihdan_index_now_yandex_webmaster' => 'crawlwp_yandex_webmaster',
			'mihdan_index_now_logs' => 'crawlwp_logs',
			'mihdan_index_now_webmaster_tools' => 'crawlwp_webmaster_tools',
			'mihdan_index_now_site_verification' => 'crawlwp_site_verification',
			'mihdan_index_now_email_reports' => 'crawlwp_email_reports',
		];

		foreach ($options_map as $old_option_name => $new_option_name) {

			$new_option = get_option($new_option_name, '');

			if (!empty($new_option)) {
				continue;
			}

			$old_option = get_option($old_option_name, '');

			if (empty($old_option)) {
				continue;
			}

			update_option($new_option_name, $old_option);
		}

		if (is_multisite()) {
			$sites = get_sites(['fields' => 'ids']);

			foreach ($sites as $site_id) {
				switch_to_blog($site_id);
				$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}index_now_log"); // phpcs:ignore
				restore_current_blog();
			}
		} else {
			$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}index_now_log"); // phpcs:ignore
		}

		return true;
	}

	public static function get_instance()
	{
		if (!isset(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
