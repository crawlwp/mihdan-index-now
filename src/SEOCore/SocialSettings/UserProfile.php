<?php

namespace Mihdan\IndexNow\SEOCore\SocialSettings;

/**
 * Adds CrawlWP SEO social profile fields to the WordPress user profile / edit-user screen,
 * and outputs per-author Open Graph / Twitter Card meta overrides.
 *
 * Stored user meta keys:
 *   _crawlwp_facebook_author  — personal Facebook page URL
 *   _crawlwp_twitter_creator  — X (Twitter) @handle
 */
class UserProfile
{
	const META_FACEBOOK = '_crawlwp_facebook_author';
	const META_TWITTER  = '_crawlwp_twitter_creator';

	public function __construct()
	{
		/* Render fields on the logged-in user's own profile page. */
		add_action('show_user_profile', [$this, 'render_fields']);

		/* Render fields when an admin edits another user's profile. */
		add_action('edit_user_profile', [$this, 'render_fields']);

		/* Save when the user updates their own profile. */
		add_action('personal_options_update', [$this, 'save_fields']);

		/* Save when an admin updates another user's profile. */
		add_action('edit_user_profile_update', [$this, 'save_fields']);
	}

	/**
	 * Output the CrawlWP SEO social profile section inside the user profile form.
	 *
	 * @param \WP_User $user The user whose profile is being edited.
	 */
	public function render_fields(\WP_User $user): void
	{
		$facebook = (string) get_user_meta($user->ID, self::META_FACEBOOK, true);
		$twitter  = (string) get_user_meta($user->ID, self::META_TWITTER, true);
		?>
		<h2><?php esc_html_e('CrawlWP SEO — Social Profiles', 'mihdan-index-now'); ?></h2>
		<p class="description">
			<?php esc_html_e('These values override the site-wide fallbacks set in Advanced → Social Networks when your posts are shared on social media.', 'mihdan-index-now'); ?>
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">
					<label for="cwpwp_facebook_author"><?php esc_html_e('Facebook Page URL', 'mihdan-index-now'); ?></label>
				</th>
				<td>
					<input
						type="url"
						id="cwpwp_facebook_author"
						name="<?php echo esc_attr(self::META_FACEBOOK); ?>"
						class="regular-text"
						value="<?php echo esc_attr($facebook); ?>"
						placeholder="https://www.facebook.com/YourPersonalProfile"
					/>
					<p class="description">
						<?php esc_html_e('Used as the article:author Open Graph tag on your posts. Overrides the site-wide fallback.', 'mihdan-index-now'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="cwpwp_twitter_creator"><?php esc_html_e('X (Twitter) @handle', 'mihdan-index-now'); ?></label>
				</th>
				<td>
					<input
						type="text"
						id="cwpwp_twitter_creator"
						name="<?php echo esc_attr(self::META_TWITTER); ?>"
						class="regular-text"
						value="<?php echo esc_attr($twitter); ?>"
						placeholder="@your-personal-username"
					/>
					<p class="description">
						<?php esc_html_e('Used as the twitter:creator meta tag on your posts. Overrides the site-wide fallback.', 'mihdan-index-now'); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Persist the social profile fields when the profile form is submitted.
	 *
	 * @param int $user_id The ID of the user being saved.
	 */
	public function save_fields(int $user_id): void
	{
		if (! current_user_can('edit_user', $user_id)) {
			return;
		}

		if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'update-user_' . $user_id)) {
			return;
		}

		$facebook = isset($_POST[self::META_FACEBOOK]) ? esc_url_raw(wp_unslash((string) $_POST[self::META_FACEBOOK])) : '';
		$twitter  = isset($_POST[self::META_TWITTER])  ? sanitize_text_field(wp_unslash((string) $_POST[self::META_TWITTER])) : '';

		/* Ensure @handle always starts with @. */
		if ($twitter !== '' && strncmp($twitter, '@', 1) !== 0) {
			$twitter = '@' . ltrim($twitter, '@');
		}

		update_user_meta($user_id, self::META_FACEBOOK, $facebook);
		update_user_meta($user_id, self::META_TWITTER, $twitter);
	}

	/**
	 * Read a social profile meta value for a given user.
	 *
	 * @param int    $user_id  WordPress user ID.
	 * @param string $meta_key One of the META_* constants.
	 *
	 * @return string
	 */
	public static function get(int $user_id, string $meta_key): string
	{
		return (string) get_user_meta($user_id, $meta_key, true);
	}
}
