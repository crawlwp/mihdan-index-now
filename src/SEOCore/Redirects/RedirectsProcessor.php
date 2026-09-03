<?php

namespace Mihdan\IndexNow\SEOCore\Redirects;

/**
 * Frontend redirect processor.
 *
 * Runs on every page request, matches the current URL against enabled
 * redirects stored in the database, and performs the appropriate HTTP
 * response (redirect, 410 Gone, 451 Unavailable).
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

		add_action('template_redirect', [$this, 'process'], 1);
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
		$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';

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

		if ($match_type === 'regex') {
			return $this->match_regex($from, $subject, $redirect->to_url, $to_url);
		}

		// Exact match — normalise both sides.
		return $this->match_exact($from, $subject, $redirect->to_url, $to_url);
	}

	/**
	 * Perform an exact-match comparison.
	 *
	 * Handles full URLs (stored when auto-created from permalink changes) and
	 * path-only values (stored when entered manually by the admin).
	 *
	 * @param string $from    Stored from_url.
	 * @param string $subject Current request path or full URI.
	 * @param string $raw_to  Stored to_url.
	 * @param string $to_url  Resolved destination (output).
	 * @return bool
	 */
	private function match_exact(string $from, string $subject, string $raw_to, string &$to_url = null): bool
	{
		// If $from is a full URL, reduce it to its path for comparison.
		if (filter_var($from, FILTER_VALIDATE_URL)) {
			$from_parsed = parse_url($from, PHP_URL_PATH);

			if ($from_parsed === null) {
				return false;
			}

			$from = urldecode($from_parsed);
		} else {
			$from = urldecode($from);
		}

		// Normalise trailing slashes.
		$from    = rtrim($from, '/');
		$subject = rtrim($subject, '/');

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
	 * @param string $raw_to  Stored to_url (may contain $1, $2, …).
	 * @param string $to_url  Resolved destination (output).
	 * @return bool
	 */
	private function match_regex(string $pattern, string $subject, string $raw_to, string &$to_url = null): bool
	{
		// Wrap in delimiters if the user hasn't.
		if ($pattern === '' || $pattern[0] !== '/') {
			$pattern = '/' . $pattern . '/';
		}

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$matched = @preg_match($pattern, $subject, $matches);

		if (!$matched) {
			return false;
		}

		// Replace $1, $2 … capture group placeholders in the destination.
		$to_url = preg_replace_callback('/\$(\d+)/', function($m) use ($matches) {
			$idx = (int) $m[1];
			return isset($matches[$idx]) ? $matches[$idx] : '';
		}, $raw_to);

		return true;
	}
}
