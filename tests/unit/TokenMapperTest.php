<?php

namespace Mihdan\IndexNow\Tests\Unit;

use Mihdan\IndexNow\SEOCore\Importer\TokenMapper;
use PHPUnit\Framework\TestCase;

/**
 * Template-token translation from the source SEO plugins.
 */
class TokenMapperTest extends TestCase
{
	/**
	 * @dataProvider tokenProvider
	 */
	public function test_convert(string $template, string $source, string $expected): void
	{
		$this->assertSame($expected, TokenMapper::convert($template, $source));
	}

	/**
	 * @return array<string, array{0: string, 1: string, 2: string}>
	 */
	public function tokenProvider(): array
	{
		return [
			'yoast title'       => ['%%title%% %%sep%% %%sitename%%', 'yoast', '{{ post.title }} {{ sep }} {{ site.title }}'],
			'rank math excerpt' => ['%excerpt% on %sitename%', 'rankmath', '{{ post.auto_description }} on {{ site.title }}'],
			'aioseo'            => ['#post_title #separator_sa #site_title', 'aioseo', '{{ post.title }} {{ sep }} {{ site.title }}'],
			'seopress'          => ['%%post_title%% %%sep%% %%sitetitle%%', 'seopress', '{{ post.title }} {{ sep }} {{ site.title }}'],
			'slim seo alias'    => ['{{ post.categories }}', 'slimseo', '{{ post.category }}'],
			'plain text'        => ['A plain title', 'tsf', 'A plain title'],
		];
	}

	public function test_unknown_source_is_left_untouched(): void
	{
		$this->assertSame('%%title%%', TokenMapper::convert('%%title%%', 'not-a-plugin'));
	}

	public function test_the_seo_framework_tokens_are_mapped(): void
	{
		$this->assertNotSame(
			[],
			TokenMapper::map_for('tsf'),
			'TSF/Genesis tokens must be mapped so they do not survive as raw text.'
		);
	}
}
