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
	 * Stages in execution order. Every stage is batched and reports its own
	 * next offset, so the browser keeps requesting the same stage until it
	 * reports done and only then moves on to the next one.
	 */
	public const STAGES = ['settings', 'posts', 'terms', 'users', 'redirects'];

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

	public static function first_stage(): string
	{
		return self::STAGES[0];
	}

	/**
	 * The stage that follows $stage, or `done` when it was the last one.
	 */
	public static function next_stage(string $stage): string
	{
		$position = array_search($stage, self::STAGES, true);

		if ($position === false) {
			return 'done';
		}

		return self::STAGES[$position + 1] ?? 'done';
	}

	/**
	 * @return array<int,array{id:string,label:string,available:bool,posts:int,terms:int,users:int,redirects:int}>
	 */
	public static function inventory(): array
	{
		$out   = [];
		$empty = ['posts' => 0, 'terms' => 0, 'users' => 0, 'redirects' => 0];

		foreach (self::sources() as $source) {
			$available = $source->is_available();
			$counts    = $available ? array_merge($empty, $source->counts()) : $empty;

			$out[] = [
				'id'        => $source->id(),
				'label'     => $source->label(),
				'available' => $available,
				'posts'     => (int) $counts['posts'],
				'terms'     => (int) $counts['terms'],
				'users'     => (int) $counts['users'],
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

		if (! in_array($stage, self::STAGES, true)) {
			$stage = self::first_stage();
		}

		$result = self::run_stage($source, $stage, $offset, $overwrite);
		$done   = ! empty($result['done']);

		$next        = $done ? self::next_stage($stage) : $stage;
		$next_offset = $done ? 0 : (int) ($result['next_offset'] ?? 0);

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

	/**
	 * @return array<string,mixed>
	 */
	private static function run_stage(Source $source, string $stage, int $offset, bool $overwrite): array
	{
		switch ($stage) {
			case 'settings':
				return $source->import_settings($offset, self::BATCH_SIZE, $overwrite);

			case 'terms':
				return $source->import_terms($offset, self::BATCH_SIZE, $overwrite);

			case 'users':
				return $source->import_users($offset, self::BATCH_SIZE, $overwrite);

			case 'redirects':
				return $source->import_redirects($offset, self::BATCH_SIZE);

			case 'posts':
			default:
				return $source->import_posts($offset, self::BATCH_SIZE, $overwrite);
		}
	}
}
