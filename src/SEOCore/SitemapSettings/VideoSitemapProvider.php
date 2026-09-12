<?php

namespace Mihdan\IndexNow\SEOCore\SitemapSettings;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;

/**
 * Video sitemap at /wp-sitemap-crawlwpvideo-1.xml
 */
class VideoSitemapProvider extends \WP_Sitemaps_Provider
{
	const PROVIDER_NAME = 'crawlwpvideo';

	public function __construct()
	{
		$this->name        = self::PROVIDER_NAME;
		$this->object_type = 'crawlwp_video_item';

		add_action('init', [$this, 'register_provider'], 20);
		add_action('template_redirect', [$this, 'maybe_render'], 1);
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

		header('Content-Type: application/xml; charset=UTF-8');
		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:video="http://www.google.com/schemas/sitemap-video/1.1">' . "\n";

		foreach ($this->get_entries() as $entry) {
			echo "\t<url>\n";
			echo "\t\t<loc>" . esc_url($entry['loc']) . "</loc>\n";
			echo "\t\t<video:video>\n";
			echo "\t\t\t<video:thumbnail_loc>" . esc_url($entry['thumbnail']) . "</video:thumbnail_loc>\n";
			echo "\t\t\t<video:title>" . esc_xml($entry['title']) . "</video:title>\n";
			echo "\t\t\t<video:description>" . esc_xml($entry['description']) . "</video:description>\n";
			if ($entry['content_loc'] !== '') {
				echo "\t\t\t<video:content_loc>" . esc_url($entry['content_loc']) . "</video:content_loc>\n";
			}
			if ($entry['player_loc'] !== '') {
				echo "\t\t\t<video:player_loc>" . esc_url($entry['player_loc']) . "</video:player_loc>\n";
			}
			echo "\t\t</video:video>\n";
			echo "\t</url>\n";
		}

		echo '</urlset>';
		exit;
	}

	/**
	 * @return array<int,array{loc:string,title:string,description:string,thumbnail:string,content_loc:string,player_loc:string}>
	 */
	private function get_entries(): array
	{
		$query = new \WP_Query([
			'post_type'      => get_post_types(['public' => true]),
			'post_status'    => 'publish',
			'posts_per_page' => 1000,
			'has_password'   => false,
			'no_found_rows'  => true,
		]);

		$entries = [];

		foreach ($query->posts as $post) {
			if (! $post instanceof \WP_Post) {
				continue;
			}

			if (MetaFields::get($post->ID, MetaFields::ROBOTS_INDEX) === 'noindex') {
				continue;
			}

			$found = $this->extract_video($post);

			if ($found === null) {
				continue;
			}

			$entries[] = $found;
		}

		return $entries;
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

		$content = (string) $post->post_content;
		$player  = '';

		if ($url === '' && preg_match('#https?://(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/)([A-Za-z0-9_-]{6,})#', $content, $m)) {
			$player = 'https://www.youtube.com/embed/' . $m[1];
			$url    = 'https://www.youtube.com/watch?v=' . $m[1];
		}

		if ($url === '' && preg_match('#https?://(?:www\.)?vimeo\.com/(\d+)#', $content, $m)) {
			$player = 'https://player.vimeo.com/video/' . $m[1];
			$url    = 'https://vimeo.com/' . $m[1];
		}

		if ($url === '') {
			return null;
		}

		$thumb = get_the_post_thumbnail_url($post, 'full') ?: '';

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
