<?php
/**
 * Plugin Name: IndexNow for Yandex/Bing
 * Version: 1.0.0
 */

namespace Mihdan\IndexNow;

use WP_Post;

/**
 * @link https://yandex.ru/support/webmaster/indexnow/key.html
 */
define( 'MIHDAN_INDEX_NOW_KEY' , 'you_key_was_here' );

add_action(
    'transition_post_status',
	/**
	 * Fires actions related to the transitioning of a post's status.
	 *
	 * @param string  $new_status Transition to this post status.
	 * @param string  $old_status Previous post status.
	 * @param WP_Post $post       Post data.
	 *
	 * @link https://yandex.ru/dev/webmaster/doc/dg/reference/host-recrawl-post.html
	 */
    function ( $new_status, $old_status, WP_Post $post ) {
	    // Срабатывает только на статус publish.
	    if ( 'publish' !== $new_status || 'publish' === $old_status || ! in_array( $post->post_type, [ 'post', 'page' ] ) ) {
		    return;
	    }

	    ping_with_yandex( $post );
    },
    10,
    3
);

function ping_with_yandex( WP_Post $post ) {

	$url = 'https://yandex.com/indexnow';
	$args = array(
		'timeout' => 30,
		'body' => json_encode(
			array(
        'host'        => parse_url( get_home_url(), PHP_URL_HOST ),
        'key'         => MIHDAN_INDEX_NOW_KEY,
        'keyLocation' => '',
				'urlList'     => [ get_permalink( $post->ID ) ]
			)
		),
	);

	$response = wp_remote_post( $url, $args );
}
