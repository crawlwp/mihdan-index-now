<?php

namespace Mihdan\IndexNow\BackgroundProcess;

use Mihdan\IndexNow\BackgroundProcess\Libs\WP_Background_Process;
use Mihdan\IndexNow\Utils;

class Setup extends WP_Background_Process
{
	protected $action = 'crawlwp_bg_process';

	protected $cron_interval = 1;

	protected $is_alternate_cron_runner = true;

	public function __construct()
	{
		// Uses unique prefix per blog so each blog has separate queue.
		$this->prefix = 'wp_' . get_current_blog_id();

		parent::__construct();

		add_filter($this->identifier . '_seconds_between_batches', function ($seconds) {
			return 1;
		});
	}

	public function dispatch()
	{
		if (apply_filters('crawlwp_alternate_cron_runner', $this->is_alternate_cron_runner)) {
			return $this->maybe_handle();
		}

		// Perform remote post.
		return parent::dispatch();
	}

	public function maybe_handle()
	{
		// Don't lock up other requests while processing.
		session_write_close();

		if ($this->is_processing()) {
			// Background process already running.
			return $this->maybe_wp_die();
		}

		if ($this->is_cancelled()) {
			$this->clear_scheduled_event();
			$this->delete_all();

			return $this->maybe_wp_die();
		}

		if ($this->is_paused()) {
			$this->clear_scheduled_event();
			$this->paused();

			return $this->maybe_wp_die();
		}

		if ($this->is_queue_empty()) {
			// No data to process.
			return $this->maybe_wp_die();
		}

		if ( ! $this->is_alternate_cron_runner) {
			check_ajax_referer($this->identifier, 'nonce');
		}

		$this->handle();

		return $this->maybe_wp_die();
	}

	protected function task($item)
	{
		if ( ! defined('CRAWLWP_BACKGROUND_PROCESS_TASK')) {
			define('CRAWLWP_BACKGROUND_PROCESS_TASK', 'true');
		}

		$action = Utils::_var($item, 'action', '');

		do_action('mihdan_index_now/bg_process_task', $action, $item, $this);

		return false;
	}
}
