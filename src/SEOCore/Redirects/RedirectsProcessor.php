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
	 * Hooked to template_redirect at priority 1 so CrawlWP fires before
	 * themes or other plugins.
	 */
	public function process(): void
	{
		if (is_admin() || wp_doing_ajax()) {
			return;
		}

		$redirects = $this->manager->get_enabled();

		if (empty($redirects)) {
			return;
		}

		// Build the current request path (and optionally full URL with query).
		$request_uri = $_SERVER['REQUEST_URI'] ?? '/';

		// Normalise: decode percent-encoding and strip trailing slash for
		// comparison, but preserve it in the original for regex capture groups.
		$request_path       = urldecode(parse_url($request_uri, PHP_URL_PATH));
		$request_with_query = $request_uri;

		foreach ($redirects as $redirect) {
			if (!$this->matches($redirect, $request_path, $request_with_query, $to_url)) {
				continue;
			}

			// Track usage.
			$this->manager->increment_hits((int) $redirect->id);
			$this->manager->update_last_accessed((int) $redirect->id);

			$type = (int) $redirect->redirect_type;

			// Content-deleted and legally-unavailable pages just send a status header.
			if ($type === 410 || $type === 451) {
				status_header($type);
				exit;
			}

			// Standard redirects.
			wp_redirect($to_url, $type);
			exit;
		}
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
	 * @param object $redirect           DB row object.
	 * @param string $request_path       Decoded request path (no query string).
	 * @param string $request_with_query Full request URI including query string.
	 * @param string $to_url             Resolved destination URL (output).
	 * @return bool
	 */
	private function matches(object $redirect, string $request_path, string $request_with_query, string &$to_url = null): bool
	{
		$from       = $redirect->from_url;
		$match_type = $redirect->match_type;
		$ignore_qs  = (bool) $redirect->ignore_query_string;

		// Determine the subject to compare against.
		$subject = $ignore_qs ? $request_path : $request_with_query;

		// Capture the query string from the request so we can pass it through
		// to the destination when ignore_qs is disabled.
		$request_query = parse_url($request_with_query, PHP_URL_QUERY);

		if ($match_type === 'regex') {
			return $this->match_regex($from, $subject, $redirect->to_url, $to_url);
		}

		if ($match_type === 'contains') {
			return $this->match_contains($from, $subject, $redirect->to_url, $to_url);
		}

		if ($match_type === 'starts_with') {
			return $this->match_starts_with($from, $subject, $redirect->to_url, $to_url);
		}

		if ($match_type === 'ends_with') {
			return $this->match_ends_with($from, $subject, $redirect->to_url, $to_url);
		}

		// Exact match — normalise both sides.
		if (!$this->match_exact($from, $subject, $redirect->to_url, $to_url)) {
			return false;
		}

		// When ignore_qs is disabled and the stored rule has no query string,
		// the match stripped the request query for comparison purposes. Now
		// append it to the destination so users land on the equivalent URL.
		if (!$ignore_qs && $request_query !== null && $request_query !== '') {
			$from_has_query = strpos(urldecode($from), '?') !== false;
			if (!$from_has_query) {
				$separator = (strpos($to_url, '?') !== false) ? '&' : '?';
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
	 * @param string $from Stored from_url.
	 * @param string $subject Current request path or full URI (depends on ignore_qs).
	 * @param string $raw_to Stored to_url.
	 * @param string|null $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_exact(string $from, string $subject, string $raw_to, string &$to_url = null): bool
	{
		// If $from is a full URL, reduce it to its path (and query) for comparison.
		if (filter_var($from, FILTER_VALIDATE_URL)) {
			$parsed_path  = parse_url($from, PHP_URL_PATH);
			$parsed_query = parse_url($from, PHP_URL_QUERY);

			if ($parsed_path === null) {
				return false;
			}

			$from = urldecode($parsed_path);
			if ($parsed_query !== null && $parsed_query !== '') {
				$from .= '?' . $parsed_query;
			}
		} else {
			$from = urldecode($from);
		}

		// When the stored rule has no query string, compare against the path
		// portion of the request only. This ensures "/old-page/" matches
		// "/old-page/?any=params" regardless of the ignore_qs setting.
		$from_has_query = strpos($from, '?') !== false;
		if (!$from_has_query) {
			// Strip query string from subject to compare paths only.
			$subject = urldecode(parse_url($subject, PHP_URL_PATH));
		}

		// Normalise leading/trailing slashes on both sides — the stored
		// from_url may now be a bare path segment (e.g. "old-page") when it
		// has no query string, so the request path must be trimmed the same
		// way for the comparison to succeed.
		$from    = trim($from, '/');
		$subject = trim($subject, '/');

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
	 * @param string|null $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_regex(string $pattern, string $subject, string $raw_to, string &$to_url = null): bool
	{
		if ($pattern === '') {
			return false;
		}

		// Detect whether the user already supplied PCRE delimiters themselves
		// (e.g. "#^/blog/([^/]+)/?$#i"). Only a small set of common delimiter
		// characters are recognised, and the character must also close the
		// pattern — otherwise a bare pattern that merely starts with "^" or
		// "[" would be misdetected as already delimited.
		$first           = $pattern[0];
		$common_delims   = ['/', '#', '~', '%', '!', '@'];
		$already_wrapped = in_array($first, $common_delims, true) && strrpos($pattern, $first) > 0;

		if (!$already_wrapped) {
			// Use "#" rather than "/" as the delimiter: redirect patterns almost
			// always target URL paths, which are full of literal "/" characters
			// that would otherwise force the admin to escape every one of them.
			$pattern = '#' . str_replace('#', '\#', $pattern) . '#';
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$matched = @preg_match($pattern, $subject, $matches);

		if (!$matched) {
			return false;
		}

		// Replace $1, $2 … capture group placeholders in the destination.
		$resolved = preg_replace_callback('/\$(\d+)/', function($m) use ($matches) {
			$idx = (int) $m[1];
			return $matches[$idx] ?? '';
		}, $raw_to);

		// preg_replace_callback returns null on error; treat that as no match.
		if ($resolved === null) {
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
	 * @param string|null $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_contains(string $from, string $subject, string $raw_to, string &$to_url = null): bool
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
	 * @param string|null $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_starts_with(string $from, string $subject, string $raw_to, string &$to_url = null): bool
	{
		// The stored from_url may lack a leading slash (bare path segment), so
		// strip the leading slash from the request subject too before comparing.
		$from    = ltrim(urldecode($from), '/');
		$subject = ltrim($subject, '/');

		if ($from === '' || stripos($subject, $from) !== 0) {
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
	 * @param string|null $to_url Resolved destination (output).
	 * @return bool
	 */
	private function match_ends_with(string $from, string $subject, string $raw_to, string &$to_url = null): bool
	{
		// The stored from_url may lack a trailing slash (bare path segment), so
		// strip the trailing slash from the request subject too before comparing.
		$from    = rtrim(urldecode($from), '/');
		$subject = rtrim($subject, '/');

		if ($from === '') {
			return false;
		}

		$from_len    = strlen($from);
		$subject_len = strlen($subject);

		if ($subject_len < $from_len || strcasecmp(substr($subject, -$from_len), $from) !== 0) {
			return false;
		}

		$to_url = $raw_to;
		return true;
	}
}
