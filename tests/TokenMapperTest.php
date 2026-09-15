<?php

/**
 * Standalone tests for TokenMapper.
 *
 * Run: php tests/TokenMapperTest.php
 */

namespace {

	$base = dirname(__DIR__);
	require_once $base . '/tests/bootstrap.php';
	require_once $base . '/src/SEOCore/Importer/TokenMapper.php';

	use Mihdan\IndexNow\SEOCore\Importer\TokenMapper;

	function cwp_assert(bool $ok, string $msg): void
	{
		if (! $ok) {
			echo "FAIL {$msg}\n";
			exit(1);
		}
		echo "OK  {$msg}\n";
	}

	$yoast = TokenMapper::convert('%%title%% %%sep%% %%sitename%% %%parent_title%% %%tagline%%', 'yoast');
	cwp_assert($yoast === '{{ post.title }} {{ sep }} {{ site.title }} {{ post.parent_title }} {{ site.description }}', 'Yoast title tokens');

	$rm = TokenMapper::convert('%post_title% on %blog_title% | %blog_description% %parent_title%', 'rankmath');
	cwp_assert($rm === '{{ post.title }} on {{ site.title }} | {{ site.description }} {{ post.parent_title }}', 'Rank Math title/blog/parent');

	$aio = TokenMapper::convert('#post_title #separator_sa #site_title #tagline #parent_title #author_bio', 'aioseo');
	cwp_assert($aio === '{{ post.title }} {{ sep }} {{ site.title }} {{ site.description }} {{ post.parent_title }} {{ author.description }}', 'AIOSEO tokens');

	$sp = TokenMapper::convert('%%post_title%% %%sep%% %%sitetitle%%', 'seopress');
	cwp_assert($sp === '{{ post.title }} {{ sep }} {{ site.title }}', 'SEOPress tokens');

	$slim = TokenMapper::convert('{{ post.categories }}', 'slimseo');
	cwp_assert($slim === '{{ post.category }}', 'Slim SEO categories alias');

	$tsf = TokenMapper::convert('A plain title', 'tsf');
	cwp_assert($tsf === 'A plain title', 'TSF leaves plain text');

	echo "\nAll tests passed.\n";
	exit(0);
}
