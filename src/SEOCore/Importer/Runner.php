<?php

namespace Mihdan\IndexNow\SEOCore\Importer;

use Mihdan\IndexNow\SEOCore\Importer\Sources\AIOSEO;
use Mihdan\IndexNow\SEOCore\Importer\Sources\RankMath;
use Mihdan\IndexNow\SEOCore\Importer\Sources\SEOPress;
use Mihdan\IndexNow\SEOCore\Importer\Sources\SlimSEO;
use Mihdan\IndexNow\SEOCore\Importer\Sources\TheSEOFramework;
use Mihdan\IndexNow\SEOCore\Importer\Sources\Yoast;

class Runner
{
	public const BATCH_SIZE = 50;

	/**
	 * @return Source[]
	 */
	public static function sources(): array
	{
		return [
			new Yoast(),
			new RankMath(),
			new AIOSEO(),
			new SEOPress(),
			new TheSEOFramework(),
			new SlimSEO(),
		];
	}

	public static function source(string $id): ?Source
	{
		foreach (self::sources() as $source) {
			if ($source->id() === $id) {
				return $source;
			}
		}

		return null;
	}

	/**
	 * @return array<int,array{id:string,label:string,available:bool,posts:int,terms:int,redirects:int}>
	 */
	public static function inventory(): array
	{
		$out = [];

		foreach (self::sources() as $source) {
			$available = $source->is_available();
			$counts    = $available ? $source->counts() : ['posts' => 0, 'terms' => 0, 'redirects' => 0];

			$out[] = [
				'id'        => $source->id(),
				'label'     => $source->label(),
				'available' => $available,
				'posts'     => (int) $counts['posts'],
				'terms'     => (int) $counts['terms'],
				'redirects' => (int) $counts['redirects'],
			];
		}

		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function run_step(string $id, string $stage, int $offset, bool $overwrite): array
	{
		$source = self::source($id);

		if ($source === null || ! $source->is_available()) {
			return [
				'ok'      => false,
				'message' => __('Unknown or unavailable import source.', 'mihdan-index-now'),
			];
		}

		if ($stage === 'posts') {
			$result = $source->import_posts($offset, self::BATCH_SIZE, $overwrite);
			$next   = ! empty($result['done']) ? 'terms' : 'posts';
			$next_offset = ! empty($result['done']) ? 0 : (int) $result['next_offset'];
		} elseif ($stage === 'terms') {
			$result = $source->import_terms($offset, self::BATCH_SIZE, $overwrite);
			$next   = ! empty($result['done']) ? 'redirects' : 'terms';
			$next_offset = ! empty($result['done']) ? 0 : (int) $result['next_offset'];
		} else {
			$imported = $source->import_redirects();
			$result   = [
				'imported' => $imported,
				'skipped'  => 0,
				'done'     => true,
			];
			$next        = 'done';
			$next_offset = 0;
		}

		return [
			'ok'          => true,
			'stage'       => $stage,
			'next_stage'  => $next,
			'next_offset' => $next_offset,
			'imported'    => (int) ($result['imported'] ?? 0),
			'skipped'     => (int) ($result['skipped'] ?? 0),
			'done'        => $next === 'done',
		];
	}
}
