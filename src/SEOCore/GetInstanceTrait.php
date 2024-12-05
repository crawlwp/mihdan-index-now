<?php

namespace Mihdan\IndexNow\SEOCore;

trait GetInstanceTrait
{
	public static function get_instance()
	{
		static $instance = null;

		if (is_null($instance)) {
			$instance = new self();
		}

		return $instance;
	}
}
