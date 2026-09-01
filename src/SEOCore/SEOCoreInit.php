<?php

namespace Mihdan\IndexNow\SEOCore;

use Mihdan\IndexNow\SEOCore\MetaBox\Assets;
use Mihdan\IndexNow\SEOCore\MetaBox\FrontendHead;
use Mihdan\IndexNow\SEOCore\MetaBox\MetaBox;
use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationFrontendOutput;
use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationSettings;

class SEOCoreInit
{
	use GetInstanceTrait;

	public function __construct()
	{
		new AdvancedSettings();

		new SiteVerificationSettings();
		new SiteVerificationFrontendOutput();

		new MetaBox();
		new Assets();
		new FrontendHead();
	}
}
