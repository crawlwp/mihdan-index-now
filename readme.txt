=== Mihdan: Index Now ===
Contributors: mihdan
Donate link: https://www.kobzarev.com/donate/
Tags: indexnow, index-now, yandex, bing, google, seo, cloudflare, duck-duck-go
Requires at least: 5.0
Tested up to: 5.8
Stable tag: 1.2.0.2
Requires PHP: 7.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Плагин уведомлений поисковых систем Яндекс/Google/Bing/Cloudflare о появлении новых страниц на сайте по протоколу IndexNow.

== Description ==

Плагин уведомлений поисковых систем Яндекс/Google/Bing/Cloudflare о появлении новых страниц на сайте по протоколу [IndexNow](https://www.indexnow.org).

### What is IndexNow? ###

IndexNow is an easy way for websites owners to instantly inform search engines about latest content changes on their website. In its simplest form, IndexNow is a simple ping so that search engines know that a URL and its content has been added, updated, or deleted, allowing search engines to quickly reflect this change in their search results.

Without IndexNow, it can take days to weeks for search engines to discover that the content has changed, as search engines don’t crawl every URL often. With IndexNow, search engines know immediately the "URLs that have changed, helping them prioritize crawl for these URLs and thereby limiting organic crawling to discover new content."

IndexNow is offered under the terms of the Attribution-ShareAlike Creative Commons License and has support from Microsoft Bing, Yandex.

### Requirement for search engines ###

Search Engines adopting the IndexNow protocol agree that submitted URLs will be automatically shared with all other participating Search Engines. To participate, search engines must have a noticeable presence in at least one market.

== Frequently Asked Questions ==

= What are the search engines' endpoint to submit URLs? =

Microsoft Bing - https://www.bing.com/indexnow?url=url-changed&key=your-key
Yandex - https://yandex.com/indexnow?url=url-changed&key=your-key

Starting November 2021, IndexNow-enabled search engines will share immediately all URLs submitted to all other IndexNow-enabled search engines, so when you notify one, you will notify all search engines.

= I submitted a URL, what will happen next? =

If search engines like your URL, search engines will attempt crawling it to get the latest content quickly based on their crawl scheduling logic and crawl quota for your site.

= I submitted 10 thousand URLs today, what will happen next? =

If search engines like your URLs and have enough crawl quota for your site, search engines will attempt crawling some or all these URLs.

= I submitted a URL, but I don’t see the URL indexed? =

Using IndexNow ensures that search engines are aware of your website changes. Using IndexNow does not guarantee that web pages will be crawled or indexed by search engines. It may take time for the change to reflect in search engines.

= I just started using IndexNow, should I publish URLs changed last year? =

No, you should publish only URLs changing (added, updated, or deleted) since the time you start to use IndexNow.

= Does the URLs submitted count on my crawl quota? =

Yes, every crawl counts towards your crawl quota. By publishing them to IndexNow, you notify search engines that you care about these URLs, search engines will generally prioritize crawling these URLs versus other URLs they know.

= Why do I not see all the URLs submitted indexed by search engines? =

Search engines can choose not to crawl and index URLs if they do not meet their selection criterion.

= Why is my URL indexed on one search engine but not the others? =

Search Engines can choose not to select specific URL if it does not meet its selection criterion.

= I have a small website that has few web pages. Should I use IndexNow? =

Yes, if you want search engines to discover content as soon as it’s changed then you should use IndexNow. You will not have to wait many hours or worse weeks to see your changes on search engines.

= Can I submit the same URL many times a day? =

Avoid submitting the same URL many times a day. If pages are edited often, then it is preferable to wait 10 minutes between edits before notifying search engines. If pages are updated constantly (examples: time in Waimea, Weather in Tokyo), it’s preferable to not use IndexNow for every change.

= Can I submit 404 URLs through the API? =

Yes, you can submit dead links (http 404, http 410) pages to notify search engines about new dead links.

= Can I submit new redirects? =

Yes, you can submit URLs newly redirecting (example 301 redirect, 302 redirect, html with meta refresh tag, etc.) to notify search engines that the content has changed.

= Can I submit all URLs for my site? =

Use IndexNow to submit only URLs having changed (added, updated, or deleted) recently, including all URLs if all URLs have been changed recently. Use sitemaps to inform search engines about all your URLs. Search engines will visit sitemaps every few days.

= I received a HTTP 429 Too Many Requests response from one Search Engine, what should I do? =

Such HTTP 429 Too Many Requests response status code indicates you are sending too many requests in a given amount of time, slow down or retry later.

= When do I need to change my key? =

Search engines will attempt crawling the {key}.txt file only once to verify ownership when they received a new key. Also, you don’t need to modify your key often.

= Can I use more than one key per host? =

Yes, if your websites use different Content Management Systems, each Content Management System can use its own key; publish different key files at the root of the host.

= Can I use one file key for the whole domain? =

No each host in your domain must have its own key. If your site has host-a.example.org and host-b.example.org, you need to have a key file for each host.

= Can I use for same key on two or more hosts? =

Yes, you can reuse the same key on two or more hosts, and two or more domains.

= I have a sitemap, do I need IndexNow? =

Yes, when sitemaps are an easy way for webmasters to inform search engines about all pages on their sites that are available for crawling, sitemaps are visited by Search Engines infrequently. With IndexNow, webmasters ''don't'' have to wait for search engines to discover and crawl sitemaps but can directly notify search engines of new content.

= What if I have another question about using IndexNow? =
See the documentation available from each search engine for more details about IndexNow.

== Changelog ==

= 1.2.0 (08.11.2021) =
* Added Help tab
* Added sidebar help links
* Added Log for incoming request
* Push search engine on insert comment

= 1.1.5 (05.11.2021) =
* Fixed bug with current time for Log table
* Remove Log table on plugin uninstall
* Set minimum requirement PHP version to 7.1

= 1.1.4 (03.11.2021) =
* Added support for WooCommerce products and other CPT
* Fixed bug with pagination for Log table
* Fixed bug with duplicate entries in Log table

= 1.1.3 (02.11.2021) =
* Changed database structure
* Added bulk actions handler for log table

= 1.1.2 (02.11.2021) =
* Added support for Page post type

= 1.1.1 (02.11.2021) =
* Added FAQ
* Fixed bug with duplicate log entries

= 1.1.0 (01.11.2021) =
* Added logger for search engines response
* Added top-level plugin menu

= 1.0.1 (31.10.2021) =
* Updated a method for generate API key
* Added Bing support
* Added settings link to plugins list page

= 1.0.0 (28.10.2021) =
* Init plugin
