<?php

namespace Mihdan\IndexNow\SEOCore;

use Mihdan\IndexNow\SEOCore\MetaBox\Assets;
use Mihdan\IndexNow\SEOCore\MetaBox\FrontendHead;
use Mihdan\IndexNow\SEOCore\MetaBox\MetaBox;
use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationFrontendOutput;
use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationSettings;
use Mihdan\IndexNow\SEOCore\SiteInfoSettings\SiteInfoSettings;
use Mihdan\IndexNow\SEOCore\SocialSettings\SocialSettings;
use Mihdan\IndexNow\SEOCore\SocialSettings\UserProfile;
use Mihdan\IndexNow\SEOCore\TitleMeta\FrontendOutput;
use Mihdan\IndexNow\SEOCore\TitleMeta\Sitemap;

class SEOCoreInit
{
	use GetInstanceTrait;

	public function __construct()
	{
		new CoreSettings\CoreSettings();
		new CoreSettings\Assets();
		new AdvancedSettings();
		new SiteInfoSettings();
		new SocialSettings();
		new UserProfile();

		new SiteVerificationSettings();
		new SiteVerificationFrontendOutput();

		new MetaBox();
		new Assets();
		new FrontendHead();

		new FrontendOutput();
		new Sitemap();
	}
}
