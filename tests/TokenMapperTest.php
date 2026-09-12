<?php

/**
 * Standalone tests for TokenMapper and Schema Graph builders.
 *
 * Run: php tests/TokenMapperTest.php
 */

namespace {

	$base = dirname(__DIR__);
	require $base . '/src/SEOCore/Importer/TokenMapper.php';
	require $base . '/src/SEOCore/Schema/Graph.php';
	require $base . '/src/SEOCore/InternalLinks/AutoLinker.php';

	use Mihdan\IndexNow\SEOCore\Importer\TokenMapper;
	use Mihdan\IndexNow\SEOCore\Schema\Graph;
	use Mihdan\IndexNow\SEOCore\InternalLinks\AutoLinker;

	$failed = 0;

	function cwp_assert(bool $ok, string $msg): void
	{
		global $failed;
		if ($ok) {
			echo "OK  {$msg}\n";
			return;
		}
		$failed++;
		echo "FAIL {$msg}\n";
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

	$faq = Graph::faq([
		['question' => 'Q1', 'answer' => 'A1'],
		['question' => '', 'answer' => 'skip'],
	]);
	cwp_assert(is_array($faq) && $faq['@type'] === 'FAQPage' && count($faq['mainEntity']) === 1, 'FAQ schema');

	$howto = Graph::howto([
		'name'  => 'Install CrawlWP',
		'steps' => [['text' => 'Download'], ['text' => 'Activate']],
	]);
	cwp_assert(is_array($howto) && $howto['@type'] === 'HowTo' && count($howto['step']) === 2, 'HowTo schema');

	cwp_assert(Graph::faq([]) === null, 'Empty FAQ is null');
	cwp_assert(Graph::duration('15') === 'PT15M', 'ISO duration');

	$recipe = Graph::recipe(['name' => 'Pie', 'prep_time' => '10', 'ingredients' => ['flour']]);
	cwp_assert(is_array($recipe) && $recipe['@type'] === 'Recipe', 'Recipe schema');

	$event = Graph::event(['name' => 'Meetup', 'start_date' => '2026-01-01', 'location' => 'Lagos']);
	cwp_assert(is_array($event) && $event['@type'] === 'Event', 'Event schema');

	$job = Graph::job(['title' => 'Dev', 'organization' => 'CrawlWP']);
	cwp_assert(is_array($job) && $job['@type'] === 'JobPosting', 'Job schema');

	$course = Graph::course(['name' => 'SEO 101', 'provider' => 'CrawlWP']);
	cwp_assert(is_array($course) && $course['@type'] === 'Course', 'Course schema');

	$video = Graph::video(['name' => 'Intro', 'url' => 'https://example.com/v.mp4']);
	cwp_assert(is_array($video) && $video['@type'] === 'VideoObject', 'Video schema');

	$rules = AutoLinker::parse_rules("seo|https://example.com/seo\n\nbadline\ncrawlwp|https://crawlwp.com");
	cwp_assert(isset($rules['seo'], $rules['crawlwp']) && ! isset($rules['badline']), 'Parse auto-link rules');

	$html = AutoLinker::apply_rules('<p>Learn seo today</p>', ['seo' => 'https://example.com/seo'], 1);
	cwp_assert(strpos($html, 'href="https://example.com/seo"') !== false, 'Auto-link applies once');

	$already = AutoLinker::apply_rules('<p><a href="/x">seo</a></p>', ['seo' => 'https://example.com/seo'], 1);
	cwp_assert(strpos($already, 'https://example.com/seo') === false, 'Skip existing anchors');

	if ($failed > 0) {
		echo "\n{$failed} test(s) failed.\n";
		exit(1);
	}

	echo "\nAll tests passed.\n";
	exit(0);
}
