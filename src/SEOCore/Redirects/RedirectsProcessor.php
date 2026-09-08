<?php

namespace Mihdan\IndexNow\SEOCore\Redirects;

/**
 * Frontend redirect processor.
 *
 * Runs on every page request, matches the current URL against enabled
 * redirects stored in the database, and performs the appropriate HTTP
 * response (redirect, 410 Gone, 451 Unavailable).
 *
 * Supported match types: exact, regex, contains, starts_with, ends_with.
 *
 * Uses a transient cache (via RedirectsManager::get_enabled()) so the DB
 * is only hit once per cache window rather than on every page load.
 */
class RedirectsProcessor
{
	/** Maximum accepted length (in characters) of a regex from_url pattern. */
	const MAX_REGEX_LENGTH = 500;

	/** @var RedirectsManager */
	private RedirectsManager $manager;

	public function __construct(RedirectsManager $manager)
	{
		$this->manager = $manager;

		add_action('template_redirect', [$this, 'process'], -1);
	}

	/**
	 * Match the current request against stored redirects and respond.
	 *
	 * Hooked to template_redirect at priority -1 so CrawlWP fires before
	 * themes or other plugins.
	 */
	public function process(): void
	{
		if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
			return;
		}

		// Never interfere with sitemaps, feeds, robots.txt or REST API requests.
		if (!empty(get_query_var('sitemap')) || is_feed() || is_robots()) {
			return;
		}

		if (defined('REST_REQUEST') && REST_REQUEST) {
			return;
		}

		$redirects = $this->manager->get_enabled();

		if (empty($redirects)) {
			return;
		}

		$request_uri      = $_SERVER['REQUEST_URI'] ?? '/';
		$request_path     = urldecode((string) parse_url($request_uri, PHP_URL_PATH));
		$request_query    = parse_url($request_uri, PHP_URL_QUERY);
		$request_path_key = trim($request_path, '/');
		$request_uri_key  = trim($request_uri, '/');

		if (!is_string($request_query)) {
			$request_query = null;
		}

		$to_url = '';

		foreach ($redirects as $redirect) {
			if (!$this->matches(
				$redirect,
				$request_path,
				$request_uri,
				$request_query,
				$request_path_key,
				$request_uri_key,
				$to_url
			)) {
				continue;
			}

			$type = (int) $redirect->redirect_type;

			// Content-deleted and legally-unavailable pages just send a status header.
			if ($type === 410 || $type === 451) {
				$this->manager->record_hit((int) $redirect->id);

				nocache_headers();
				status_header($type);
				exit;
			}

			// Standard redirects — only follow a destination that passes validation.
			$to_url = $this->validate_destination($to_url, $request_uri);

			if ($to_url === '') {
				return;
			}

			$this->manager->record_hit((int) $redirect->id);

			wp_redirect($to_url, $type, 'CrawlWP');
			exit;
		}
	}

	/**
	 * Validate a resolved redirect destination before it is used.
	 *
	 * Accepts site-relative paths beginning with a single "/" and absolute
	 * http(s) URLs with a host. Rejects protocol-relative ("//"), backslash
	 * tricks ("/\\"), non-http schemes (javascript:, data:, …) and any
	 * destination identical to the current request (self-redirect loop).
	 *
	 * @param string $url Resolved destination URL.
	 * @param string $request_uri Current request URI (path + query).
	 * @return string The destination when safe, empty string otherwise.
	 */
	private function validate_destination(string $url, string $request_uri = ''): string
	{
		$url = trim($url);

		if ($url === '') {
			return '';
		}

		// Reject control characters / whitespace which browsers may strip.
		if (preg_match('/[\x00-\x1F\x7F]/', $url)) {
			return '';
		}

		if ($url[0] === '/') {
			// Site-relative path: must not be protocol-relative or use a backslash.
			if (isset($url[1]) && ($url[1] === '/' || $url[1] === '\\')) {
				return '';
			}
		} else {
			$parsed = wp_parse_url($url);

			if (!is_array($parsed) || empty($parsed['scheme']) || empty($parsed['host'])) {
				return '';
			}

			if (!in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
				return '';
			}
		}

		// Self-redirect loop guard: compare the destination against the current
		// request, resolving site-relative values to the site origin so a path
		// and its absolute equivalent are treated as the same location.
		if ($request_uri !== '') {
			$home   = wp_parse_url(home_url());
			$origin = isset($home['scheme'], $home['host']) ? $home['scheme'] . '://' . $home['host'] : '';

			$current = untrailingslashit($origin . $request_uri);
			$target  = untrailingslashit(($url[0] === '/') ? $origin . $url : $url);

			if ($url === $request_uri || strcasecmp($target, $current) === 0) {
				return '';
			}
		}

		return $url;
	}

	// -------------------------------------------------------------------------
	// Matching logic
	// -------------------------------------------------------------------------

	/**
	 * Test whether a redirect rule matches the current request.
	 *
	 * Sets $to_url (passed by reference) to the final destination URL when
	 * a match is found.
	 *
	 * @param object      $redirect            DB row object.
	 * @param string      $request_path        Decoded request path (no query string).
	 * @param string      $request_with_query  Full request URI including query string.
	 * @param string|null $request_query       Raw query string from the request.
	 * @param string      $request_path_key    Path with leading/trailing slashes stripped.
	 * @param string      $request_uri_key     Full URI with leading/trailing slashes stripped.
	 * @param string      $to_url              Resolved destination URL (output).
	 * @return bool
	 */
	private function matches(
		object $redirect,
		string $request_path,
		string $request_with_query,
		?string $request_query,
		string $request_path_key,
		string $request_uri_key,
		string &$to_url
	): bool {
		$from       = $redirect->from_url;
		$match_type = $redirect->match_type;
		$ignore_qs  = (bool) $redirect->ignore_query_string;
		$raw_to     = $redirect->to_url;
		$subject    = $ignore_qs ? $request_path : $request_with_query;

		switch ($match_type) {
			case 'regex':
				$matched = $this->match_regex($from, $subject, $raw_to, $to_url);
				break;
			case 'contains':
				$matched = $this->match_contains($from, $subject, $raw_to, $to_url);
				break;
			case 'starts_with':
				$matched = $this->match_starts_with($from, $subject, $raw_to, $to_url);
				break;
			case 'ends_with':
				$matched = $this->match_ends_with($from, $subject, $raw_to, $to_url);
				break;
			default:
				$matched = $this->match_exact($from, $ignore_qs, $request_path_key, $request_uri_key, $raw_to, $to_url);
				break;
		}

		if (!$matched) return false;

		// When ignore_qs is disabled and the stored rule has no query string,
		// the match stripped the request query for comparison purposes. Now
		// append it to the destination so users land on the equivalent URL.
		if (!$ignore_qs && $request_query !== null && $request_query !== '') {
			$from_has_query = strpos(urldecode($from), '?') !== false;
			if (!$from_has_query) {
				$separator = !empty($to_url) && strpos($to_url, '?') !== false ? '&' : '?';
				$to_url   .= $separator . $request_query;
			}
		}

		return true;
	}

	/**
	 * Perform an exact-match comparison.
	 *
	 * Handles full URLs (stored when auto-created from permalink changes) and
	 * path-only values (stored when entered manually by the admin).
	 *
	 * When the stored from_url contains a query string, the full URI (path +
	 * query) is compared. When from_url has no query string, only the path
	 * portion of the request is compared — regardless of the ignore_qs setting
	 * — so a rule like "/old-page/" correctly matches "/old-page/?foo=bar".
	 *
	 * @param string $from             Stored from_url.
	 * @param bool   $ignore_qs        Whether the rule ignores query strings.
	 * @param string $request_path_key Path with leading/trailing slashes stripped.
	 * @param string $request_uri_key  Full URI with leading/trailing slashes stripped.
	 * @param string $raw_to           Stored to_url.
	 * @param string $to_url           Resolved destination (output).
	 * @return bool
	 */
	private function match_exact(
		string $from,
		bool $ignore_qs,
		string $request_path_key,
		string $request_uri_key,
		string $raw_to,
		string &$to_url
	): bool {
		// Fast path: stored rules are path-only. Only parse when from_url is an
		// absolute http(s) URL (legacy rows / unsanitised auto-created values).
		if (strncasecmp($from, 'http://', 7) === 0 || strncasecmp($from, 'https://', 8) === 0) {
			$parsed = parse_url($from);

			if (!is_array($parsed) || !isset($parsed['path'])) {
				return false;
			}

			$from = urldecode($parsed['path']);
			if (isset($parsed['query']) && $parsed['query'] !== '') {
				$from .= '?' . $parsed['query'];
			}
		} else {
			$from = urldecode($from);
		}

		$from_has_query = strpos($from, '?') !== false;
		$from           = trim($from, '/');

		// Path-only rules always compare against the path. Query-bearing rules
		// compare against the path when ignore_qs is on, otherwise the full URI.
		$subject = $from_has_query
			? ($ignore_qs ? $request_path_key : $request_uri_key)
			: $request_path_key;

		if (strcasecmp($from, $subject) !== 0) {
			return false;
		}

		$to_url = $raw_to;
		return true;
	}

	/**
	 * Perform a regex match with optional capture-group substitution in to_url.
	 *
	 * @param string $pattern Pattern stored in from_url.
	 * @param string $subject Current request path or full URI.
	 * @param string $raw_to Stored to_url (may contain $1, $2, …).
	 * @param string $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_regex(string $pattern, string $subject, string $raw_to, string &$to_url): bool
	{
		if ($pattern === '' || strlen($pattern) > self::MAX_REGEX_LENGTH) {
			return false;
		}

		// Always wrap the stored pattern in fixed "#" delimiters. User-supplied
		// delimiters and modifiers are never honoured — a path-like pattern such
		// as "/old-page/" is therefore treated as a plain pattern body rather
		// than an already-delimited regex. "#" is used instead of "/" because
		// URL paths are full of literal slashes.
		$regex = '#' . str_replace('#', '\#', $pattern) . '#';

		// Invalid patterns fail preg_match (false); non-matches return 0.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$matched = @preg_match($regex, $subject, $matches);

		if ($matched !== 1) {
			return false;
		}

		// Replace $1, $2 … capture group placeholders in the destination.
		$resolved = preg_replace_callback('/\$(\d+)/', static function ($m) use ($matches) {
			$idx = (int) $m[1];
			return $matches[$idx] ?? '';
		}, $raw_to);

		// preg_replace_callback returns null on error; treat that as no match.
		if ($resolved === null) {
			return false;
		}

		// A capture group must never be able to change the destination host
		// (e.g. "http://$1" with a request of "/old/evil.com").
		$raw_host      = (string) wp_parse_url($raw_to, PHP_URL_HOST);
		$resolved_host = (string) wp_parse_url($resolved, PHP_URL_HOST);

		if ($raw_host !== $resolved_host) {
			return false;
		}

		$to_url = $resolved;

		return true;
	}

	/**
	 * Perform a case-insensitive "contains" match — the rule matches when
	 * $from appears anywhere within the request subject.
	 *
	 * @param string $from Stored from_url (substring to look for).
	 * @param string $subject Current request path or full URI.
	 * @param string $raw_to Stored to_url.
	 * @param string $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_contains(string $from, string $subject, string $raw_to, string &$to_url): bool
	{
		$from = urldecode($from);

		if ($from === '' || stripos($subject, $from) === false) {
			return false;
		}

		$to_url = $raw_to;
		return true;
	}

	/**
	 * Perform a case-insensitive "starts with" match.
	 *
	 * @param string $from Stored from_url.
	 * @param string $subject Current request path or full URI.
	 * @param string $raw_to Stored to_url.
	 * @param string $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_starts_with(string $from, string $subject, string $raw_to, string &$to_url): bool
	{
		// The stored from_url may lack a leading slash (bare path segment), so
		// strip the leading slash from the request subject too before comparing.
		$from     = ltrim(urldecode($from), '/');
		$from_len = strlen($from);

		if ($from_len === 0) {
			return false;
		}

		$subject = ltrim($subject, '/');

		if (strlen($subject) < $from_len || strncasecmp($subject, $from, $from_len) !== 0) {
			return false;
		}

		$to_url = $raw_to;
		return true;
	}

	/**
	 * Perform a case-insensitive "ends with" match.
	 *
	 * @param string $from Stored from_url.
	 * @param string $subject Current request path or full URI.
	 * @param string $raw_to Stored to_url.
	 * @param string $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_ends_with(string $from, string $subject, string $raw_to, string &$to_url): bool
	{
		// The stored from_url may lack a trailing slash (bare path segment), so
		// strip the trailing slash from the request subject too before comparing.
		$from     = rtrim(urldecode($from), '/');
		$from_len = strlen($from);

		if ($from_len === 0) {
			return false;
		}

		$subject = rtrim($subject, '/');

		if (strlen($subject) < $from_len || substr_compare($subject, $from, -$from_len, $from_len, true) !== 0) {
			return false;
		}

		$to_url = $raw_to;
		return true;
	}
}
