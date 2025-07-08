<?php

namespace Mihdan\IndexNow\BackgroundProcess;

class Dispatch
{
	/**
	 * @var Setup
	 */
	protected $bgProcess;

	public function __construct()
	{
		add_action('plugins_loaded', [$this, 'init']);
	}

	public function init()
	{
		$this->bgProcess = new Setup();
	}

	public function add_bg_task($task)
	{
		$this->bgProcess->push_to_queue($task);
	}

	public function save_and_run()
	{
		if ($this->bgProcess->get_batches_count() < $this->get_batches_upper_limit()) {
			$this->bgProcess->save()->dispatch();
		}
	}

	public function get_batches_upper_limit()
	{
		return apply_filters('crawlwp_bg_process_batches_upper_limit', 20);
	}

	/**
	 * @return self
	 */
	public static function get_instance()
	{
		static $instance = null;

		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}
}
