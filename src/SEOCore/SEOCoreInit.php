<?php

namespace Mihdan\IndexNow\SEOCore;

use Mihdan\IndexNow\SEOCore\Breadcrumbs\Breadcrumbs;
use Mihdan\IndexNow\SEOCore\Breadcrumbs\BreadcrumbSettings;
use Mihdan\IndexNow\SEOCore\Notifications\Notifications;
use Mihdan\IndexNow\SEOCore\MetaBox\Assets;
use Mihdan\IndexNow\SEOCore\MetaBox\PostListColumn;
use Mihdan\IndexNow\SEOCore\MetaBox\FrontendHead;
use Mihdan\IndexNow\SEOCore\MetaBox\MetaBox;
use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationFrontendOutput;
use Mihdan\IndexNow\SEOCore\SiteVerification\SiteVerificationSettings;
use Mihdan\IndexNow\SEOCore\RssSettings\RssSettings;
use Mihdan\IndexNow\SEOCore\SiteInfoSettings\SiteInfoSettings;
use Mihdan\IndexNow\SEOCore\SocialSettings\SocialSettings;
use Mihdan\IndexNow\SEOCore\SocialSettings\UserProfile;
use Mihdan\IndexNow\SEOCore\TitleMeta\FrontendOutput;
use Mihdan\IndexNow\SEOCore\Sitemap;
use Mihdan\IndexNow\SEOCore\SitemapSettings\SitemapSettings;
use Mihdan\IndexNow\SEOCore\RobotsSettings\RobotsSettings;
use Mihdan\IndexNow\SEOCore\SitemapSettings\NewsSitemapProvider;
use Mihdan\IndexNow\SEOCore\SitemapSettings\CustomUrlsSitemapProvider;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsManager;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsSettings;
use Mihdan\IndexNow\SEOCore\Redirects\RedirectsProcessor;
use Mihdan\IndexNow\SEOCore\Redirects\PermalinkTracker;
use Mihdan\IndexNow\SEOCore\FeatureGate\FeatureGate;

class SEOCoreInit
{
	use GetInstanceTrait;

	public function __construct()
	{
		// Feature gate — registers the "SEO Features" promo/settings tab.
		// Must be first so the tab appears before all other header menus.
		new FeatureGate();

		// Notifications are always active (even when features are disabled)
		// so admins are alerted to critical site-wide issues.
		new Notifications();

		new AdvancedSettings();
		new SiteVerificationSettings();
		new SiteVerificationFrontendOutput();

		// The on-page SEO output features are gated behind the master toggle.
		// Admins who are upgrading from another SEO plugin can review settings
		// first, then flip the switch on the "SEO Features" tab.
		if (FeatureGate::is_enabled()) {

			// Redirects — manager must be instantiated early so the DB table
			// is created on plugins_loaded before any other code queries it.
			$redirects_manager = new RedirectsManager();
			new RedirectsSettings($redirects_manager);
			new RedirectsProcessor($redirects_manager);
			new PermalinkTracker($redirects_manager);
			new CoreSettings\CoreSettings();
			new CoreSettings\Assets();
			new SiteInfoSettings();
			new SocialSettings();
			new RssSettings();
			new BreadcrumbSettings();
			new RobotsSettings();
			new SitemapSettings();
			new NewsSitemapProvider();
			new CustomUrlsSitemapProvider();
			new UserProfile();

			new MetaBox();
			new Assets();
			new PostListColumn();
			new FrontendHead();

			// init hooking to prevent "Function _load_textdomain_just_in_time was called incorrectly" error.
			add_action('init', function() {
				$breadcrumbs = new Breadcrumbs();
				$breadcrumbs->setup();
			});

			new FrontendOutput();
			new Sitemap();
		}
	}
}
