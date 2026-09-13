<?php

namespace Mihdan\IndexNow\Tests\Unit;

use Mihdan\IndexNow\SEOCore\MetaBox\MetaFields;
use Mihdan\IndexNow\SEOCore\SitemapSettings\VideoSitemapProvider;
use PHPUnit\Framework\TestCase;
use WP_Post;

class VideoSitemapProviderTest extends TestCase
{
	private VideoSitemapProvider $provider;

	protected function setUp(): void
	{
		parent::setUp();

		$GLOBALS['crawlwp_test_state']['home_url'] = 'https://example.test';
		$GLOBALS['crawlwp_test_state']['permalink'] = 'https://example.test/sample-post/';
		$GLOBALS['crawlwp_test_state']['post_meta'] = [];
		$GLOBALS['crawlwp_test_state']['post_thumbnail_url'] = [];

		$this->provider = new VideoSitemapProvider();
	}

	/**
	 * Invoke private extract_video method via Reflection.
	 */
	private function extractVideo(WP_Post $post): ?array
	{
		$reflection = new \ReflectionClass($this->provider);
		$method = $reflection->getMethod('extract_video');
		$method->setAccessible(true);

		return $method->invoke($this->provider, $post);
	}

	/**
	 * Invoke private build_xml method via Reflection.
	 */
	private function buildXml(): string
	{
		$reflection = new \ReflectionClass($this->provider);
		$method = $reflection->getMethod('build_xml');
		$method->setAccessible(true);

		return $method->invoke($this->provider);
	}

	private function makePost(array $data): WP_Post
	{
		return new WP_Post((object) $data);
	}

	public function test_extract_video_from_direct_mp4_url(): void
	{
		$post = $this->makePost([
			'ID'           => 101,
			'post_title'   => 'Direct MP4 Post',
			'post_content' => '<p>Watch this clip: https://cdn.example.test/videos/sample.mp4 in full HD.</p>',
			'post_excerpt' => 'A post with direct MP4 video.',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://cdn.example.test/videos/sample.mp4', $entry['content_loc']);
		$this->assertSame('', $entry['player_loc']);
		$this->assertSame('https://example.test/sample-post/', $entry['loc']);
		$this->assertSame('Direct MP4 Post', $entry['title']);
		$this->assertSame('A post with direct MP4 video.', $entry['description']);
		$this->assertSame('https://example.test/wp-includes/images/media/video.png', $entry['thumbnail']);
	}

	public function test_extract_video_from_gutenberg_block(): void
	{
		$post = $this->makePost([
			'ID'           => 102,
			'post_title'   => 'Gutenberg Video Post',
			'post_content' => '<!-- wp:video {"id":42} --><figure class="wp-block-video"><video controls src="https://example.test/wp-content/uploads/2026/09/clip.mp4"></video></figure><!-- /wp:video -->',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://example.test/wp-content/uploads/2026/09/clip.mp4', $entry['content_loc']);
		$this->assertSame('', $entry['player_loc']);
	}

	public function test_extract_video_from_html_source_tag(): void
	{
		$post = $this->makePost([
			'ID'           => 103,
			'post_title'   => 'HTML5 Video Post',
			'post_content' => '<video controls width="640"><source src="https://example.test/media/intro.mp4" type="video/mp4"></video>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://example.test/media/intro.mp4', $entry['content_loc']);
		$this->assertSame('', $entry['player_loc']);
	}

	public function test_extract_video_from_shortcodes(): void
	{
		$post1 = $this->makePost([
			'ID'           => 104,
			'post_title'   => 'Shortcode mp4 attr',
			'post_content' => '[video mp4="https://example.test/uploads/shortcode.mp4"]',
		]);

		$entry1 = $this->extractVideo($post1);
		$this->assertNotNull($entry1);
		$this->assertSame('https://example.test/uploads/shortcode.mp4', $entry1['content_loc']);

		$post2 = $this->makePost([
			'ID'           => 105,
			'post_title'   => 'Shortcode src attr',
			'post_content' => '[video src="https://example.test/uploads/src_shortcode.mp4"]',
		]);

		$entry2 = $this->extractVideo($post2);
		$this->assertNotNull($entry2);
		$this->assertSame('https://example.test/uploads/src_shortcode.mp4', $entry2['content_loc']);
	}

	public function test_extract_video_from_mp4_with_query_and_hash(): void
	{
		$post = $this->makePost([
			'ID'           => 106,
			'post_title'   => 'MP4 with query',
			'post_content' => '<video src="https://cdn.example.test/video.mp4?token=xyz123&expire=999#t=10,20"></video>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://cdn.example.test/video.mp4?token=xyz123&expire=999#t=10,20', $entry['content_loc']);
	}

	public function test_extract_video_from_relative_mp4_url(): void
	{
		$post = $this->makePost([
			'ID'           => 107,
			'post_title'   => 'Relative MP4 Post',
			'post_content' => '<video controls src="/wp-content/uploads/2026/09/local.mp4"></video>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://example.test/wp-content/uploads/2026/09/local.mp4', $entry['content_loc']);
	}

	public function test_extract_video_poster_attribute_thumbnail(): void
	{
		$post = $this->makePost([
			'ID'           => 108,
			'post_title'   => 'Video with Poster',
			'post_content' => '<video controls poster="https://example.test/images/poster.jpg" src="https://example.test/video.mp4"></video>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://example.test/images/poster.jpg', $entry['thumbnail']);
	}

	public function test_extract_video_featured_image_preferred_over_poster(): void
	{
		$GLOBALS['crawlwp_test_state']['post_thumbnail_url'][109] = 'https://example.test/featured.jpg';

		$post = $this->makePost([
			'ID'           => 109,
			'post_title'   => 'Featured Image Priority',
			'post_content' => '<video controls poster="https://example.test/poster.jpg" src="https://example.test/video.mp4"></video>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://example.test/featured.jpg', $entry['thumbnail']);
	}

	public function test_extract_video_youtube(): void
	{
		$post = $this->makePost([
			'ID'           => 110,
			'post_title'   => 'YouTube Post',
			'post_content' => '<p>https://www.youtube.com/watch?v=dQw4w9WgXcQ</p>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://www.youtube.com/embed/dQw4w9WgXcQ', $entry['player_loc']);
		$this->assertSame('', $entry['content_loc']);
	}

	public function test_extract_video_vimeo(): void
	{
		$post = $this->makePost([
			'ID'           => 111,
			'post_title'   => 'Vimeo Post',
			'post_content' => '<p>https://vimeo.com/123456789</p>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://player.vimeo.com/video/123456789', $entry['player_loc']);
		$this->assertSame('', $entry['content_loc']);
	}

	public function test_extract_video_schema_extra_video(): void
	{
		$GLOBALS['crawlwp_test_state']['post_meta'][112][MetaFields::SCHEMA_EXTRA] = [
			'video' => [
				'url' => 'https://example.test/schema-video.mp4',
			],
		];

		$post = $this->makePost([
			'ID'           => 112,
			'post_title'   => 'Schema Video Post',
			'post_content' => '<p>No video embed in content.</p>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNotNull($entry);
		$this->assertSame('https://example.test/schema-video.mp4', $entry['content_loc']);
		$this->assertSame('', $entry['player_loc']);
	}

	public function test_extract_video_no_video_returns_null(): void
	{
		$post = $this->makePost([
			'ID'           => 113,
			'post_title'   => 'Plain Post',
			'post_content' => '<p>Just some plain content with no videos.</p>',
		]);

		$entry = $this->extractVideo($post);

		$this->assertNull($entry);
	}

	public function test_sync_post_video_data_persists_meta_for_mp4(): void
	{
		$post = $this->makePost([
			'ID'           => 114,
			'post_title'   => 'Sync MP4 Post',
			'post_content' => '<video src="https://example.test/synced.mp4"></video>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		]);

		$this->provider->sync_post_video_data(114, $post);

		$stored = $GLOBALS['crawlwp_test_state']['post_meta'][114][VideoSitemapProvider::META_KEY] ?? null;
		$this->assertIsArray($stored);
		$this->assertSame('https://example.test/synced.mp4', $stored['content_loc']);
		$this->assertSame('', $stored['player_loc']);
	}

	public function test_sync_post_video_data_deletes_meta_when_video_removed(): void
	{
		$GLOBALS['crawlwp_test_state']['post_meta'][115][VideoSitemapProvider::META_KEY] = [
			'loc'         => 'https://example.test/sample-post/',
			'content_loc' => 'https://example.test/old.mp4',
		];

		$post = $this->makePost([
			'ID'           => 115,
			'post_title'   => 'No Video Post',
			'post_content' => '<p>Video was removed.</p>',
			'post_status'  => 'publish',
			'post_type'    => 'post',
		]);

		$this->provider->sync_post_video_data(115, $post);

		$this->assertArrayNotHasKey(VideoSitemapProvider::META_KEY, $GLOBALS['crawlwp_test_state']['post_meta'][115] ?? []);
	}

	public function test_build_xml_includes_mp4_content_loc(): void
	{
		$GLOBALS['crawlwp_test_state']['wp_query_posts'] = [116];
		$GLOBALS['crawlwp_test_state']['post_meta'][116][VideoSitemapProvider::META_KEY] = [
			'loc'         => 'https://example.test/mp4-video-post/',
			'title'       => 'My MP4 Video',
			'description' => 'A description of the mp4 video',
			'thumbnail'   => 'https://example.test/thumb.jpg',
			'content_loc' => 'https://example.test/media/sample.mp4',
			'player_loc'  => '',
		];

		$xml = $this->buildXml();

		$this->assertStringContainsString('xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"', $xml);
		$this->assertStringContainsString('<loc>https://example.test/mp4-video-post/</loc>', $xml);
		$this->assertStringContainsString('<video:thumbnail_loc>https://example.test/thumb.jpg</video:thumbnail_loc>', $xml);
		$this->assertStringContainsString('<video:title>My MP4 Video</video:title>', $xml);
		$this->assertStringContainsString('<video:description>A description of the mp4 video</video:description>', $xml);
		$this->assertStringContainsString('<video:content_loc>https://example.test/media/sample.mp4</video:content_loc>', $xml);
		$this->assertStringNotContainsString('<video:player_loc>', $xml);

		$parsed = simplexml_load_string($xml);
		$this->assertInstanceOf(\SimpleXMLElement::class, $parsed);
	}
}
