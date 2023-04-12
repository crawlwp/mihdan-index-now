<?php
/**
 *
 */

namespace Mihdan\IndexNow;

use Mihdan\IndexNow\Views\WPOSA;
use WP_Post;
use WP_Comment;

class Hooks {
	/**
	 * @var WPOSA
	 */
	private $wposa;

	/**
	 * @var int $ping_delay Ping delay in seconds.
	 */
	private $ping_delay;

	/**
	 * Hooks constructor.
	 *
	 * @param WPOSA $wposa WPOSA instance.
	 */
	public function __construct( WPOSA $wposa ) {
		$this->wposa      = $wposa;
		$this->ping_delay = (int) $this->wposa->get_option( 'ping_delay', 'general', 60 );
	}

	public function setup_hooks() {
		add_action( 'transition_post_status', [ $this, 'post_updated' ], 10, 3 );
		add_action( 'wp_insert_comment', [ $this, 'comment_updated' ], 10, 2 );
		add_action( 'saved_term', [ $this, 'term_updated' ], 10, 3 );
	}

	/**
	 * Fires actions related to the transitioning of a post's status.
	 *
	 * @param string  $new_status New post status.
	 * @param string  $old_status Old post status.
	 * @param WP_Post $post       Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function post_updated( string $new_status, string $old_status, WP_Post $post ): void {

		if ( $new_status !== 'publish' ) {
			return;
		}

		if ( ! empty( $_REQUEST['meta-box-loader'] ) ) { // phpcs:ignore
			return;
		}

		if ( wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}

		if ( function_exists( 'is_post_publicly_viewable' ) && ! is_post_publicly_viewable( $post ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, (array) $this->wposa->get_option( 'post_types', 'general', [] ), true ) ) {
			return;
		}

		// Disable for Bulk Edit screen.
		if ( isset( $_REQUEST['bulk_edit'] ) && $this->wposa->get_option( 'disable_for_bulk_edit', 'general', 'on' ) === 'on' ) {
			return;
		}

		// Delay.
		$last_update = (int) get_post_meta(
			$post->ID,
			Utils::get_plugin_prefix() . '_last_update',
			true
		);

		if ( ( current_time( 'timestamp' ) - $last_update ) < $this->ping_delay ) {
			return;
		}

		if ( $old_status === $new_status ) {
			// Post updated.
			if ( $this->wposa->get_option( 'ping_on_post_updated', 'general', 'on' ) === 'on' ) {
				do_action( 'mihdan_index_now/post_updated', $post->ID, $post );
			}
		} else {
			// Post added.
			if ( $this->wposa->get_option( 'ping_on_post', 'general', 'on' ) === 'on' ) {
				do_action( 'mihdan_index_now/post_added', $post->ID, $post );
			}
		}

		update_post_meta(
			$post->ID,
			Utils::get_plugin_prefix() . '_last_update',
			current_time( 'timestamp' )
		);
	}

	public function comment_updated( int $id, WP_Comment $comment ): void {

		// Delay.
		$last_update = (int) get_comment_meta(
			$id,
			Utils::get_plugin_prefix() . '_last_update',
			true
		);

		if ( ( current_time( 'timestamp' ) - $last_update ) < $this->ping_delay ) {
			return;
		}

		do_action( 'mihdan_index_now/comment_updated', $comment->comment_post_ID, $comment );

		update_comment_meta(
			$id,
			Utils::get_plugin_prefix() . '_last_update',
			current_time( 'timestamp' )
		);
	}

	/**
	 * Fires after a term has been saved, and the term cache has been cleared.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function term_updated( int $term_id, int $tt_id, string $taxonomy ): void {

		// Delay.
		$last_update = (int) get_term_meta(
			$term_id,
			Utils::get_plugin_prefix() . '_last_update',
			true
		);

		if ( ( current_time( 'timestamp' ) - $last_update ) < $this->ping_delay ) {
			return;
		}

		do_action( 'mihdan_index_now/term_updated', $term_id, $taxonomy );

		update_term_meta(
			$term_id,
			Utils::get_plugin_prefix() . '_last_update',
			current_time( 'timestamp' )
		);
	}
}
