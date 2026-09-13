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

	$yoast = TokenMapper::convert('%%title%% %%sep%% %%sitename%%', 'yoast');
	cwp_assert($yoast === '{{ post.title }} {{ sep }} {{ site.title }}', 'Yoast title tokens');

	$rm = TokenMapper::convert('%excerpt% on %sitename%', 'rankmath');
	cwp_assert($rm === '{{ post.auto_description }} on {{ site.title }}', 'Rank Math excerpt/sitename');

	$aio = TokenMapper::convert('#post_title #separator_sa #site_title', 'aioseo');
	cwp_assert($aio === '{{ post.title }} {{ sep }} {{ site.title }}', 'AIOSEO tokens');

	$sp = TokenMapper::convert('%%post_title%% %%sep%% %%sitetitle%%', 'seopress');
	cwp_assert($sp === '{{ post.title }} {{ sep }} {{ site.title }}', 'SEOPress tokens');

	$slim = TokenMapper::convert('{{ post.categories }}', 'slimseo');
	cwp_assert($slim === '{{ post.category }}', 'Slim SEO categories alias');

	$tsf = TokenMapper::convert('A plain title', 'tsf');
	cwp_assert($tsf === 'A plain title', 'TSF leaves plain text');

	echo "\nAll tests passed.\n";
	exit(0);
}
