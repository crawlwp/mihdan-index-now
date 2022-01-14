<?php
/**
 *
 */

namespace Mihdan\IndexNow;

use WP_Post;
use WP_Comment;

class Hooks {
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
	 * @param WP_Post $post    Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
	public function post_updated( $new_status, $old_status, WP_Post $post ): void {

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

		do_action( 'mihdan_index_now/post_updated', $post->ID, $post );
	}

	public function comment_updated( int $id, WP_Comment $comment ): void {
		do_action( 'mihdan_index_now/comment_updated', $comment->comment_post_ID, $comment );
	}

	/**
	 * Fires after a term has been saved, and the term cache has been cleared.
	 *
	 * @param int    $term_id  Term ID.
	 * @param int    $tt_id    Term taxonomy ID.
	 * @param string $taxonomy Taxonomy slug.
	 */
	public function term_updated( int $term_id, int $tt_id, string $taxonomy ): void {
		do_action( 'mihdan_index_now/term_updated', $term_id, $taxonomy );
	}
}
