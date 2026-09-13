<?php

namespace Mihdan\IndexNow\SEOCore\SitemapSettings;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;

/**
 * Video sitemap at /wp-sitemap-crawlwpvideo-1.xml
 *
 * Video detection happens once, when a post is saved: the extracted video data
 * is stored in the _crawlwp_video_data post meta key. The sitemap therefore only
 * has to query the posts that actually carry that key instead of scanning the
 * content of the latest 1 000 posts on every request, which also means older
 * posts with videos are no longer silently dropped.
 *
 * Existing content is indexed by a self-rescheduling background batch scan
 * (crawlwp_video_sitemap_backfill) that can be re-run at any time with the
 * "Rescan posts for videos" button on the sitemap settings screen.
 *
 * The rendered XML is cached in the crawlwp_video_sitemap_xml transient for an
 * hour and invalidated whenever a post is saved or deleted, or when the sitemap
 * settings change.
 */
class VideoSitemapProvider extends \WP_Sitemaps_Provider
{
	const PROVIDER_NAME = 'crawlwpvideo';

	/** Post meta key holding the detected video data. */
	const META_KEY = '_crawlwp_video_data';

	/** Transient holding the rendered sitemap XML. */
	const XML_TRANSIENT = 'crawlwp_video_sitemap_xml';

	/** How long the rendered XML is cached and advertised as cacheable. */
	const CACHE_TTL = HOUR_IN_SECONDS;

	/** Hard sitemap limit defined by the sitemaps protocol. */
	const MAX_URLS = 50000;

	/** Cron hook for the background batch scan of existing posts. */
	const BACKFILL_HOOK = 'crawlwp_video_sitemap_backfill';

	/** Option flag set once the batch scan has processed every post. */
	const BACKFILL_OPTION = 'crawlwp_video_sitemap_backfilled';

	/** Option holding the offset of the next batch to scan. */
	const BACKFILL_OFFSET_OPTION = 'crawlwp_video_sitemap_backfill_offset';

	/** Number of posts scanned per batch. */
	const BACKFILL_BATCH = 100;

	/** admin-post.php action for the manual rescan button. */
	const RESCAN_ACTION = 'crawlwp_video_sitemap_rescan';

	public function __construct()
	{
		$this->name        = self::PROVIDER_NAME;
		$this->object_type = 'crawlwp_video_item';

		add_action('init', [$this, 'register_provider'], 20);
		add_action('template_redirect', [$this, 'maybe_render'], 1);

		/* Detect videos on save instead of on every sitemap request. */
		add_action('save_post', [$this, 'sync_post_video_data'], 99, 2);
		add_action('deleted_post', [$this, 'flush_cache']);
		add_action('update_option_crawlwp_' . SitemapSettings::SECTION, [$this, 'flush_cache']);
		add_action('add_option_crawlwp_' . SitemapSettings::SECTION, [$this, 'flush_cache']);

		/* One-off background scan of pre-existing content, plus manual rescan. */
		add_action('init', [$this, 'maybe_schedule_backfill'], 30);
		add_action(self::BACKFILL_HOOK, [$this, 'run_backfill_batch']);
		add_action('admin_post_' . self::RESCAN_ACTION, [$this, 'handle_rescan']);
	}

	public function get_url_list($page_num, $object_subtype = ''): array
	{
		$urls = [];

		foreach ($this->get_entries() as $entry) {
			$urls[] = ['loc' => $entry['loc']];
		}

		return $urls;
	}

	public function get_max_num_pages($object_subtype = ''): int
	{
		return SitemapSettings::get('video_enabled', 'off') === 'on' ? 1 : 0;
	}

	public function register_provider(): void
	{
		if (SitemapSettings::get('video_enabled', 'off') !== 'on') {
			return;
		}

		wp_register_sitemap_provider(self::PROVIDER_NAME, $this);
	}

	public function maybe_render(): void
	{
		if (get_query_var('sitemap') !== self::PROVIDER_NAME) {
			return;
		}

		if (SitemapSettings::get('video_enabled', 'off') !== 'on') {
			wp_die(esc_html__('The Video Sitemap is disabled.', 'mihdan-index-now'), '', ['response' => 404]);
		}

		$xml = get_transient(self::XML_TRANSIENT);

		if (! is_string($xml) || $xml === '') {
			$xml = $this->build_xml();

			set_transient(self::XML_TRANSIENT, $xml, self::CACHE_TTL);
		}

		header('Content-Type: application/xml; charset=UTF-8');
		header('Cache-Control: public, max-age=' . self::CACHE_TTL);
		header('Expires: ' . gmdate('D, d M Y H:i:s', time() + self::CACHE_TTL) . ' GMT');

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- every value is escaped in build_xml().
		echo $xml;
		exit;
	}

	// -------------------------------------------------------------------------
	// Rendering
	// -------------------------------------------------------------------------

	/**
	 * Build the complete, fully escaped sitemap XML document.
	 */
	private function build_xml(): string
	{
		$stylesheet_url = SitemapStylesheet::get_stylesheet_url('video');

		$xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		if ($stylesheet_url !== '') {
			$xml .= '<?xml-stylesheet type="text/xsl" href="' . esc_url($stylesheet_url) . '" ?>' . "\n";
		}
		$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		foreach ($this->get_entries() as $entry) {
			$xml .= "\t<url>\n";
			$xml .= "\t\t<loc>" . esc_url($entry['loc']) . "</loc>\n";
			$xml .= "\t\t<video:video>\n";
			$xml .= "\t\t\t<video:thumbnail_loc>" . esc_url($entry['thumbnail']) . "</video:thumbnail_loc>\n";
			$xml .= "\t\t\t<video:title>" . esc_xml($entry['title']) . "</video:title>\n";
			$xml .= "\t\t\t<video:description>" . esc_xml($entry['description']) . "</video:description>\n";

			if ($entry['content_loc'] !== '') {
				$xml .= "\t\t\t<video:content_loc>" . esc_url($entry['content_loc']) . "</video:content_loc>\n";
			}

			if ($entry['player_loc'] !== '') {
				$xml .= "\t\t\t<video:player_loc>" . esc_url($entry['player_loc']) . "</video:player_loc>\n";
			}

			$xml .= "\t\t</video:video>\n";
			$xml .= "\t</url>\n";
		}

		$xml .= '</urlset>';

		return $xml;
	}

	// -------------------------------------------------------------------------
	// Cache invalidation
	// -------------------------------------------------------------------------

	/**
	 * Drop the cached XML.
	 *
	 * Hooks: deleted_post, update_option_crawlwp_sitemap_settings, save_post.
	 */
	public function flush_cache(): void
	{
		delete_transient(self::XML_TRANSIENT);
	}

	// -------------------------------------------------------------------------
	// Detection on save
	// -------------------------------------------------------------------------

	/**
	 * Store (or remove) the video data of a post when it is saved.
	 *
	 * Hook: save_post
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function sync_post_video_data($post_id, $post = null): void
	{
		$post_id = (int) $post_id;

		if ($post_id <= 0 || wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
			return;
		}

		$post = $post instanceof \WP_Post ? $post : get_post($post_id);

		if (! $post instanceof \WP_Post) {
			return;
		}

		$this->update_video_data($post);
		$this->flush_cache();
	}

	/**
	 * Detect the post's video and persist the result in post meta.
	 *
	 * @param \WP_Post $post Post object.
	 *
	 * @return bool Whether video data is now stored for the post.
	 */
	private function update_video_data(\WP_Post $post): bool
	{
		$entry = $this->is_eligible($post) ? $this->extract_video($post) : null;

		if ($entry === null) {
			delete_post_meta($post->ID, self::META_KEY);

			return false;
		}

		update_post_meta($post->ID, self::META_KEY, $entry);

		return true;
	}

	/**
	 * Whether a post may appear in the video sitemap at all.
	 */
	private function is_eligible(\WP_Post $post): bool
	{
		if ($post->post_status !== 'publish' || $post->post_password !== '') {
			return false;
		}

		$public = get_post_types(['public' => true]);

		return isset($public[$post->post_type]);
	}

	// -------------------------------------------------------------------------
	// Backfill of existing content
	// -------------------------------------------------------------------------

	/**
	 * Schedule the background scan of existing posts when it has not run yet.
	 *
	 * Hook: init
	 */
	public function maybe_schedule_backfill(): void
	{
		if (get_option(self::BACKFILL_OPTION) === '1') {
			return;
		}

		if (! wp_next_scheduled(self::BACKFILL_HOOK)) {
			wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::BACKFILL_HOOK);
		}
	}

	/**
	 * Scan one batch of published posts and re-schedule itself until done.
	 *
	 * Hook: crawlwp_video_sitemap_backfill
	 */
	public function run_backfill_batch(): void
	{
		if (get_option(self::BACKFILL_OPTION) === '1') {
			return;
		}

		$offset = (int) get_option(self::BACKFILL_OFFSET_OPTION, 0);

		$query = new \WP_Query([
			'post_type'              => get_post_types(['public' => true]),
			'post_status'            => 'publish',
			'posts_per_page'         => self::BACKFILL_BATCH,
			'offset'                 => $offset,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'has_password'           => false,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
		]);

		if (empty($query->posts)) {
			update_option(self::BACKFILL_OPTION, '1', false);
			delete_option(self::BACKFILL_OFFSET_OPTION);
			$this->flush_cache();

			return;
		}

		foreach ($query->posts as $post) {
			if ($post instanceof \WP_Post) {
				$this->update_video_data($post);
			}
		}

		update_option(self::BACKFILL_OFFSET_OPTION, $offset + count($query->posts), false);
		$this->flush_cache();

		wp_schedule_single_event(time() + MINUTE_IN_SECONDS, self::BACKFILL_HOOK);
	}

	/**
	 * Handle the "Rescan posts for videos" button.
	 *
	 * Hook: admin_post_crawlwp_video_sitemap_rescan
	 */
	public function handle_rescan(): void
	{
		if (! current_user_can('manage_options')) {
			wp_die(esc_html__('You are not allowed to do this.', 'mihdan-index-now'), '', ['response' => 403]);
		}

		check_admin_referer(self::RESCAN_ACTION);

		delete_option(self::BACKFILL_OPTION);
		delete_option(self::BACKFILL_OFFSET_OPTION);
		wp_clear_scheduled_hook(self::BACKFILL_HOOK);
		$this->flush_cache();

		wp_schedule_single_event(time() + 5, self::BACKFILL_HOOK);

		$redirect = wp_get_referer() ?: admin_url();

		wp_safe_redirect(add_query_arg('crawlwp_video_rescan', '1', $redirect));
		exit;
	}

	/**
	 * Nonced URL for the manual rescan button.
	 */
	public static function get_rescan_url(): string
	{
		return wp_nonce_url(
			admin_url('admin-post.php?action=' . self::RESCAN_ACTION),
			self::RESCAN_ACTION
		);
	}

	// -------------------------------------------------------------------------
	// Data
	// -------------------------------------------------------------------------

	/**
	 * @return array<int,array{loc:string,title:string,description:string,thumbnail:string,content_loc:string,player_loc:string}>
	 */
	private function get_entries(): array
	{
		$query = new \WP_Query([
			'post_type'              => get_post_types(['public' => true]),
			'post_status'            => 'publish',
			'posts_per_page'         => self::MAX_URLS,
			'fields'                 => 'ids',
			'has_password'           => false,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_term_cache' => false,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'meta_query'             => [
				'relation' => 'AND',
				[
					'key'     => self::META_KEY,
					'compare' => 'EXISTS',
				],
				[
					'relation' => 'OR',
					[
						'key'     => MetaFields::ROBOTS_INDEX,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => MetaFields::ROBOTS_INDEX,
						'value'   => 'noindex',
						'compare' => '!=',
					],
				],
			],
		]);

		$post_ids = array_map('intval', (array) $query->posts);

		if ($post_ids === []) {
			return [];
		}

		/* Prime the meta cache so the loop below runs without extra queries. */
		update_meta_cache('post', $post_ids);

		$entries = [];

		foreach ($post_ids as $post_id) {
			$entry = $this->normalize_entry(get_post_meta($post_id, self::META_KEY, true));

			if ($entry !== null) {
				$entries[] = $entry;
			}
		}

		return $entries;
	}

	/**
	 * Validate stored meta and fill in any missing key.
	 *
	 * @param mixed $data Raw meta value.
	 *
	 * @return array{loc:string,title:string,description:string,thumbnail:string,content_loc:string,player_loc:string}|null
	 */
	private function normalize_entry($data): ?array
	{
		if (! is_array($data) || empty($data['loc'])) {
			return null;
		}

		return [
			'loc'         => (string) $data['loc'],
			'title'       => (string) ($data['title'] ?? ''),
			'description' => (string) ($data['description'] ?? ''),
			'thumbnail'   => (string) ($data['thumbnail'] ?? ''),
			'content_loc' => (string) ($data['content_loc'] ?? ''),
			'player_loc'  => (string) ($data['player_loc'] ?? ''),
		];
	}

	/**
	 * @return array{loc:string,title:string,description:string,thumbnail:string,content_loc:string,player_loc:string}|null
	 */
	private function extract_video(\WP_Post $post): ?array
	{
		$extra = MetaFields::get($post->ID, MetaFields::SCHEMA_EXTRA, []);

		if (is_string($extra)) {
			$decoded = json_decode($extra, true);
			$extra   = is_array($decoded) ? $decoded : [];
		}

		$video = is_array($extra) ? ($extra['video'] ?? []) : [];
		$url   = is_array($video) ? (string) ($video['url'] ?? $video['content_url'] ?? '') : '';

		$content = $post->post_content;
		$player  = '';

		if ($url === '' && preg_match('#https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})#', $content, $m)) {
			$player = 'https://www.youtube.com/embed/' . $m[1];
			$url    = 'https://www.youtube.com/watch?v=' . $m[1];
		}

		if ($url === '' && preg_match('#https?://(?:www\.)?vimeo\.com/(\d+)#', $content, $m)) {
			$player = 'https://player.vimeo.com/video/' . $m[1];
			$url    = 'https://vimeo.com/' . $m[1];
		}

		if ($url === '' && preg_match('#https?://[^\s"\'<>\?\#\[\]]+\.mp4(?=[?\#\s"\'<>\)\[\]]|$)(?:[?\#][^\s"\'<>\)\[\]]*)?#i', $content, $m)) {
			$url = $m[0];
		}

		if ($url === '' && preg_match('#(?:\b(?:src|href|mp4)\s*=\s*["\']?|\[(?:embed|video)[^\]]*\]\s*)(/[^\s"\'<>?\#\[\]]+\.mp4(?=[?\#\s"\'<>\)\[\]]|$)(?:[?\#][^\s"\'<>\)\[\]]*)?)#i', $content, $m)) {
			$url = home_url($m[1]);
		}

		if ($url === '') {
			return null;
		}

		$thumb = get_the_post_thumbnail_url($post, 'full') ?: '';

		if ($thumb === '' && preg_match('#poster=["\']?(https?://[^\s"\'\]]+)["\']?#i', $content, $pm)) {
			$thumb = $pm[1];
		}

		if ($thumb === '' && preg_match('#poster=["\']?(/[^\s"\'\]]+)["\']?#i', $content, $pm)) {
			$thumb = home_url($pm[1]);
		}

		if ($thumb === '') {
			$thumb = home_url('/wp-includes/images/media/video.png');
		}

		$desc = wp_trim_words(wp_strip_all_tags($post->post_excerpt ?: $post->post_content), 30, '...');

		return [
			'loc'         => (string) get_permalink($post),
			'title'       => get_the_title($post),
			'description' => $desc !== '' ? $desc : get_the_title($post),
			'thumbnail'   => $thumb,
			'content_loc' => $player === '' ? $url : '',
			'player_loc'  => $player,
		];
	}
}
