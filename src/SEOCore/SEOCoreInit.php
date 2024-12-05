<?php

namespace Mihdan\IndexNow\SEOCore;

class SEOCoreInit
{
	use GetInstanceTrait;

	public function __construct()
	{
		new CoreSettings();
	}
}
