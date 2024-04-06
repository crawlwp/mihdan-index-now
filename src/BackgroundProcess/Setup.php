<?php

namespace Mihdan\IndexNow\BackgroundProcess;

use Mihdan\IndexNow\Dependencies\WP_Background_Process;

class Setup extends WP_Background_Process
{
	protected $action = 'crawlwp_bg_process';

	public function __construct()
	{
		// Uses unique prefix per blog so each blog has separate queue.
		$this->prefix = 'wp_' . get_current_blog_id();

		parent::__construct();
	}

	protected function task($item)
	{
		define('CRAWLWP_BACKGROUND_PROCESS_TASK', 'true');

		do_action('mihdan_index_now/bg_process_task', $item);

		return false;
	}
}
