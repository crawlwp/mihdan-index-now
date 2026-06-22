<?php

namespace Mihdan\IndexNow\Migrations;

class Migrations
{

	/**
	 * Migrated versions options name.
	 */
	const MIGRATED_VERSIONS_OPTION_NAME = 'crawlwp_versions';

	/**
	 * Plugin version.
	 */
	const PLUGIN_VERSION = CRAWLWP_VERSION;

	/**
	 * Migration started status.
	 */
	const STARTED = -1;

	/**
	 * Migration failed status.
	 */
	const FAILED = -2;

	/**
	 * Plugin name.
	 */
	const PLUGIN_NAME = 'CrawlWP';

	/**
	 * Init hooks.
	 *
	 * @return void
	 */
	public function setup_hooks()
	{

		if (!$this->is_allowed()) {
			return;
		}

		add_action('plugins_loaded', [$this, 'migrate'], -PHP_INT_MAX);
	}

	/**
	 * Migrate.
	 *
	 * @return void
	 */
	public function migrate()
	{
		$migrated = (array)get_option(self::MIGRATED_VERSIONS_OPTION_NAME, []);

		$migrations = array_filter(
			get_class_methods($this),
			static function ($migration) {
				return false !== strpos($migration, 'migrate_');
			}
		);

		$upgrade_versions = [];

		foreach ($migrations as $migration) {

			$upgrade_version = $this->get_upgrade_version($migration);

			$upgrade_versions[] = $upgrade_version;

			if (
				(isset($migrated[$upgrade_version]) && $migrated[$upgrade_version] >= 0) ||
				version_compare($upgrade_version, self::PLUGIN_VERSION, '>')
			) {
				continue;
			}

			if (!isset($migrated[$upgrade_version])) {
				$migrated[$upgrade_version] = static::STARTED;
			}

			// Run migration.
			$result = $this->{$migration}();

			// Some migration methods can be called several times to support AS action,
			// so do not log their completion here.
			if (null === $result) {
				continue;
			}

			$migrated[$upgrade_version] = $result ? time() : static::FAILED;
		}

		// Remove any keys that are not in the migrations list.
		$migrated = array_intersect_key($migrated, array_flip($upgrade_versions));

		// Store the current version.
		$migrated[self::PLUGIN_VERSION] = $migrated[self::PLUGIN_VERSION] ?? time();

		// Sort the array by version.
		uksort($migrated, 'version_compare');

		update_option(self::MIGRATED_VERSIONS_OPTION_NAME, $migrated);
	}

	/**
	 * Determine if migration is allowed.
	 */
	public function is_allowed(): bool
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (isset($_GET['service-worker'])) {
			return false;
		}

		return wp_doing_cron() || is_admin() || (defined('WP_CLI') && constant('WP_CLI'));
	}

	/**
	 * Get an upgrade version from the method name.
	 *
	 * @param string $method Method name.
	 *
	 * @return string
	 */
	private function get_upgrade_version(string $method): string
	{
		// Find only the digits and underscores to get version number.
		if (!preg_match('/(\d_?)+/', $method, $matches)) {
			return '';
		}

		$raw_version = $matches[0];

		if (strpos($raw_version, '_')) {
			// Modern notation: 3_10_0 means 3.10.0 version.
			return str_replace('_', '.', $raw_version);
		}
		// Legacy notation, with 1-digit subversion numbers: 360 means 3.6.0 version.
		return implode('.', str_split($raw_version));
	}

	/**
	 * Migrate to 0.1.2
	 *
	 * @return bool|null
	 * @noinspection MultiAssignmentUsageInspection
	 * @noinspection PhpUnused
	 */
	protected function migrate_0_1_2()
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
			'mihdan_index_now_plugins' => 'crawlwp_plugins',

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
			delete_option($old_option_name);
		}

		if (is_multisite()) {
			$sites = get_sites(['fields' => 'ids']);

			foreach ($sites as $site_id) {
				switch_to_blog($site_id);
				$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mihdan_index_now_log"); // phpcs:ignore
				restore_current_blog();
			}
		} else {
			$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}mihdan_index_now_log"); // phpcs:ignore
		}

		return true;
	}
}
