<?php

namespace Mihdan\IndexNow\SEOCore\Redirects;

/**
 * Core database manager for CrawlWP redirects.
 *
 * Manages the {prefix}crawlwp_redirects custom table. Provides CRUD
 * operations and a query layer used by both the admin UI and the frontend
 * processor.
 *
 * Table option key: crawlwp_redirects_db_version
 * Table name:       {prefix}crawlwp_redirects
 */
class RedirectsManager
{
	/** Current DB schema version. Bump to trigger a dbDelta() upgrade. */
	const DB_VERSION = '1.0';

	/** Transient key for the cached redirect list used by the frontend. */
	const CACHE_KEY = 'crawlwp_redirects_cache';

	/** Transient expiry in seconds (5 minutes). */
	const CACHE_EXPIRY = 300;

	/**
	 * Full table name (with WP prefix).
	 *
	 * @var string
	 */
	private string $table;

	public function __construct()
	{
		global $wpdb;
		$this->table = $wpdb->prefix . 'crawlwp_redirects';

		// Create / upgrade the table if needed.
		add_action('plugins_loaded', [$this, 'maybe_install_table'], 1);
	}

	// -------------------------------------------------------------------------
	// Table installation
	// -------------------------------------------------------------------------

	/**
	 * Create or upgrade the DB table when the schema version changes.
	 */
	public function maybe_install_table(): void
	{
		if (get_option('crawlwp_redirects_db_version') === self::DB_VERSION) {
			return;
		}

		$this->install_table();
	}

	/**
	 * Run dbDelta() to create / upgrade the redirects table.
	 */
	public function install_table(): void
	{
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$this->table} (
			id bigint(20) NOT NULL AUTO_INCREMENT,
			from_url varchar(2048) NOT NULL DEFAULT '',
			to_url varchar(2048) NOT NULL DEFAULT '',
			redirect_type smallint(4) NOT NULL DEFAULT 301,
			match_type varchar(20) NOT NULL DEFAULT 'exact',
			note text NOT NULL DEFAULT '',
			ignore_query_string tinyint(1) NOT NULL DEFAULT 1,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			hits bigint(20) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			last_accessed datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY from_url (from_url(255)),
			KEY enabled (enabled)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta($sql);

		update_option('crawlwp_redirects_db_version', self::DB_VERSION);
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert a new redirect row.
	 *
	 * @param array $data Redirect field values.
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function insert(array $data)
	{
		global $wpdb;

		$data = apply_filters('crawlwp_redirect_before_insert', $this->sanitize($data));
		$data['created_at'] = current_time('mysql', true);

		$result = $wpdb->insert($this->table, $data, $this->get_formats($data));

		if ($result) {
			$this->flush_cache();
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update an existing redirect row.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Fields to update.
	 * @return bool
	 */
	public function update(int $id, array $data): bool
	{
		global $wpdb;

		$data = apply_filters('crawlwp_redirect_before_update', $this->sanitize($data), $id);

		$result = $wpdb->update($this->table, $data, ['id' => $id], $this->get_formats($data), ['%d']);

		if ($result !== false) {
			$this->flush_cache();
			return true;
		}

		return false;
	}

	/**
	 * Delete a redirect row.
	 *
	 * @param int $id Row ID.
	 * @return bool
	 */
	public function delete(int $id): bool
	{
		global $wpdb;

		$result = $wpdb->delete($this->table, ['id' => $id], ['%d']);

		if ($result !== false) {
			$this->flush_cache();
			return true;
		}

		return false;
	}

	/**
	 * Retrieve a single redirect by ID.
	 *
	 * @param int $id Row ID.
	 * @return object|null
	 */
	public function get(int $id)
	{
		global $wpdb;

		return $wpdb->get_row($wpdb->prepare(
			"SELECT * FROM {$this->table} WHERE id = %d",
			$id
		));
	}

	/**
	 * Retrieve a list of redirects with optional filtering / pagination.
	 *
	 * @param array $args {
	 *   @type string $search      Full-text search against from_url / to_url / note.
	 *   @type string $status      'all' | 'active' | 'inactive'. Default 'all'.
	 *   @type int    $type        Redirect HTTP status code filter (0 = all).
	 *   @type int    $per_page    Rows per page. Default 20.
	 *   @type int    $page        1-based page number. Default 1.
	 *   @type string $orderby     Column name. Default 'id'.
	 *   @type string $order       'ASC' | 'DESC'. Default 'DESC'.
	 * }
	 * @return object[]
	 */
	public function get_all(array $args = []): array
	{
		global $wpdb;

		$defaults = [
			'search'   => '',
			'status'   => 'all',
			'type'     => 0,
			'per_page' => 20,
			'page'     => 1,
			'orderby'  => 'id',
			'order'    => 'DESC',
		];
		$args = array_merge($defaults, $args);

		list($where, $values) = $this->build_where($args);

		$allowed_cols = ['id', 'from_url', 'to_url', 'redirect_type', 'hits', 'created_at', 'last_accessed'];
		$orderby = in_array($args['orderby'], $allowed_cols, true) ? $args['orderby'] : 'id';
		$order   = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

		$per_page = max(1, (int) $args['per_page']);
		$offset   = ($args['page'] - 1) * $per_page;

		$sql = "SELECT * FROM {$this->table}{$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$values[] = $per_page;
		$values[] = $offset;

		return $wpdb->get_results($wpdb->prepare($sql, $values));
	}

	/**
	 * Count redirects matching the given filters.
	 *
	 * @param array $args Same keys as get_all().
	 * @return int
	 */
	public function get_count(array $args = []): int
	{
		global $wpdb;

		list($where, $values) = $this->build_where($args);

		$sql = "SELECT COUNT(*) FROM {$this->table}{$where}";

		if (!empty($values)) {
			return (int) $wpdb->get_var($wpdb->prepare($sql, $values));
		}

		return (int) $wpdb->get_var($sql);
	}

	/**
	 * Increment the hit counter for a redirect row.
	 *
	 * @param int $id Row ID.
	 */
	public function increment_hits(int $id): void
	{
		global $wpdb;
		$wpdb->query($wpdb->prepare(
			"UPDATE {$this->table} SET hits = hits + 1 WHERE id = %d",
			$id
		));
	}

	/**
	 * Update the last_accessed timestamp for a redirect row.
	 *
	 * @param int $id Row ID.
	 */
	public function update_last_accessed(int $id): void
	{
		global $wpdb;
		$wpdb->update(
			$this->table,
			['last_accessed' => current_time('mysql', true)],
			['id' => $id],
			['%s'],
			['%d']
		);
	}

	/**
	 * Get all enabled redirects for the frontend processor.
	 * Results are cached in a transient for performance.
	 *
	 * @return object[]
	 */
	public function get_enabled(): array
	{
		$cached = get_transient(self::CACHE_KEY);

		if ($cached !== false) {
			return $cached;
		}

		$rows = $this->get_all([
			'status'   => 'active',
			'per_page' => 5000,
			'page'     => 1,
			'orderby'  => 'id',
			'order'    => 'ASC',
		]);

		set_transient(self::CACHE_KEY, $rows, self::CACHE_EXPIRY);

		return $rows;
	}

	/**
	 * Flush the frontend redirect cache.
	 */
	public function flush_cache(): void
	{
		delete_transient(self::CACHE_KEY);
	}

	/**
	 * Check whether a redirect from a given URL already exists.
	 *
	 * @param string $from_url From URL to check.
	 * @return bool
	 */
	public function exists_from_url(string $from_url): bool
	{
		global $wpdb;

		$count = $wpdb->get_var($wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table} WHERE from_url = %s",
			$from_url
		));

		return (int) $count > 0;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Sanitize a data array before DB write.
	 *
	 * @param array $data Raw data.
	 * @return array Sanitized data.
	 */
	private function sanitize(array $data): array
	{
		$clean = [];

		if (isset($data['from_url'])) {
			$clean['from_url'] = $this->strip_home_url(trim($data['from_url']));
		}

		if (isset($data['to_url'])) {
			$clean['to_url'] = esc_url_raw(trim($data['to_url']));
		}

		if (isset($data['redirect_type'])) {
			$allowed = [301, 302, 307, 410, 451];
			$type = (int) $data['redirect_type'];
			$clean['redirect_type'] = in_array($type, $allowed, true) ? $type : 301;
		}

		if (isset($data['match_type'])) {
			$clean['match_type'] = in_array($data['match_type'], ['exact', 'regex'], true) ? $data['match_type'] : 'exact';
		}

		if (isset($data['note'])) {
			$clean['note'] = sanitize_textarea_field($data['note']);
		}

		if (isset($data['ignore_query_string'])) {
			$clean['ignore_query_string'] = (int) (bool) $data['ignore_query_string'];
		}

		if (isset($data['enabled'])) {
			$clean['enabled'] = (int) (bool) $data['enabled'];
		}

		return $clean;
	}

	/**
	 * Strip the site's home URL origin from a "from" URL, keeping only the path/query/fragment.
	 * If the value is already a relative path it is returned unchanged (after sanitizing).
	 *
	 * @param string $url Raw user input (full URL or relative path).
	 * @return string Path-only value, e.g. "/old-page/" or "/page/?foo=bar".
	 */
	private function strip_home_url(string $url): string
	{
		if ($url === '') {
			return '';
		}

		// Build the home origin (scheme + host, no trailing slash) for comparison.
		$home   = untrailingslashit(home_url());
		$parsed = wp_parse_url($home);
		$origin = isset($parsed['scheme'], $parsed['host'])
			? $parsed['scheme'] . '://' . $parsed['host']
			: '';

		// If the user pasted a full URL whose host matches ours, strip the origin prefix.
		if ($origin !== '' && stripos($url, $origin) === 0) {
			$url = substr($url, strlen($origin));
		}

		// Ensure the path starts with a slash so it is treated as site-relative.
		if ($url !== '' && $url[0] !== '/') {
			$url = '/' . $url;
		}

		// Sanitize: allow only path, query string, and fragment.
		return esc_url_raw($url, ['http', 'https', '']);
	}

	/**
	 * Build the WHERE clause and prepared values array for a query.
	 *
	 * @param array $args Query args.
	 * @return array {0: string WHERE clause, 1: array values}
	 */
	private function build_where(array $args): array
	{
		$conditions = [];
		$values     = [];

		if (!empty($args['search'])) {
			$search = '%' . $args['search'] . '%';
			$conditions[] = "(from_url LIKE %s OR to_url LIKE %s OR note LIKE %s)";
			$values[] = $search;
			$values[] = $search;
			$values[] = $search;
		}

		if (isset($args['status']) && $args['status'] !== 'all') {
			if ($args['status'] === 'active') {
				$conditions[] = "enabled = 1";
			} elseif ($args['status'] === 'inactive') {
				$conditions[] = "enabled = 0";
			}
		}

		if (!empty($args['type'])) {
			$conditions[] = "redirect_type = %d";
			$values[]     = (int) $args['type'];
		}

		$where = !empty($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';

		return [$where, $values];
	}

	/**
	 * Return sprintf format specifiers for an array of DB values.
	 *
	 * @param array $data Associative data array.
	 * @return string[]
	 */
	private function get_formats(array $data): array
	{
		$int_fields = ['redirect_type', 'ignore_query_string', 'enabled', 'hits'];
		$formats    = [];

		foreach (array_keys($data) as $key) {
			$formats[] = in_array($key, $int_fields, true) ? '%d' : '%s';
		}

		return $formats;
	}
}
