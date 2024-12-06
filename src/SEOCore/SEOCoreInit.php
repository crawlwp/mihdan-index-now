<?php

namespace Mihdan\IndexNow\SEOCore;

use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationFrontendOutput;
use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationSettings;

class SEOCoreInit
{
	use GetInstanceTrait;

	public function __construct()
	{
		new CoreSettings();

		new SiteVerificationSettings();
		new SiteVerificationFrontendOutput();
	}
}
