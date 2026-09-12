<?php

namespace Mihdan\IndexNow\Tests\Unit;

use Mihdan\IndexNow\SEOCore\InternalLinks\AutoLinker;
use PHPUnit\Framework\TestCase;

/**
 * Keyword auto-linking. The linker must only ever touch text nodes.
 */
class AutoLinkerTest extends TestCase
{
	private const RULES = ['keyword' => 'https://example.com/target/'];

	protected function setUp(): void
	{
		parent::setUp();

		$GLOBALS['crawlwp_test_state']['permalink'] = 'https://example.test/current-post/';
	}

	public function test_parse_rules_skips_malformed_lines(): void
	{
		$rules = AutoLinker::parse_rules("seo|https://example.com/seo\n\nbadline\ncrawlwp|https://crawlwp.com");

		$this->assertArrayHasKey('seo', $rules);
		$this->assertArrayHasKey('crawlwp', $rules);
		$this->assertArrayNotHasKey('badline', $rules);
	}

	public function test_links_keyword_inside_a_paragraph(): void
	{
		$html = AutoLinker::apply_rules('<p>Learn keyword today</p>', self::RULES, 1);

		$this->assertStringContainsString('href="https://example.com/target/"', $html);
		$this->assertStringContainsString('>keyword</a>', $html);
	}

	/**
	 * @dataProvider protectedMarkupProvider
	 */
	public function test_protected_markup_is_untouched(string $content): void
	{
		$this->assertStringNotContainsString(
			'https://example.com/target/',
			AutoLinker::apply_rules($content, self::RULES, 3)
		);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public function protectedMarkupProvider(): array
	{
		return [
			'existing anchor' => ['<p><a href="/x">keyword</a></p>'],
			'heading'         => ['<h2>keyword</h2>'],
			'code block'      => ['<pre><code>keyword</code></pre>'],
			'attribute'       => ['<p><img src="a.jpg" alt="keyword"></p>'],
		];
	}

	public function test_respects_the_per_keyword_limit(): void
	{
		$html = AutoLinker::apply_rules('<p>keyword keyword keyword</p>', self::RULES, 2);

		$this->assertSame(2, substr_count($html, '<a href='));
	}

	public function test_does_not_link_to_the_current_page(): void
	{
		$GLOBALS['crawlwp_test_state']['permalink'] = 'https://example.com/target/';

		$this->assertStringNotContainsString(
			'<a href=',
			AutoLinker::apply_rules('<p>keyword</p>', self::RULES, 1)
		);
	}

	public function test_content_without_a_match_is_returned_unchanged(): void
	{
		$content = '<p>Nothing to see here — plain &amp; unchanged.</p>';

		$this->assertSame($content, AutoLinker::apply_rules($content, self::RULES, 3));
	}

	public function test_links_keyword_inside_nested_inline_tags(): void
	{
		$html = AutoLinker::apply_rules('<p>Learn <strong>keyword</strong> today</p>', self::RULES, 1);

		$this->assertStringContainsString('<strong><a href="https://example.com/target/">keyword</a></strong>', $html);
	}

	public function test_leaves_shortcode_bodies_verbatim(): void
	{
		$html = AutoLinker::apply_rules('<p>See [gallery keyword] then keyword</p>', self::RULES, 3);

		$this->assertStringContainsString('[gallery keyword]', $html);
		$this->assertSame(1, substr_count($html, '<a href='));
	}

	public function test_links_keywords_inside_tables(): void
	{
		$html = AutoLinker::apply_rules('<table><tr><td>keyword</td></tr></table>', self::RULES, 1);

		$this->assertStringContainsString('<td><a href="https://example.com/target/">keyword</a></td>', $html);
	}

	public function test_html_processor_preserves_original_markup(): void
	{
		if (! class_exists(\WP_HTML_Processor::class)) {
			$this->markTestSkipped('WP_HTML_Processor is not available.');
		}

		$html = AutoLinker::apply_rules('<div><p>keyword</div>', self::RULES, 1);

		$this->assertSame('<div><p><a href="https://example.com/target/">keyword</a></div>', $html);
	}
}
