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

	public function run()
	{
		$this->bgProcess->save()->dispatch();
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
