<?php
/**
 * Plugin Name: IndexNow for Yandex/Bing
 * Version: 1.0.0
 */

namespace Mihdan\IndexNow;

/**
 * @link https://yandex.ru/support/webmaster/indexnow/key.html
 */
define( 'MIHDAN_INDEX_NOW_KEY' , 'you_key_was_here' );

require_once __DIR__ . '/src/class-main.php';

( new Main() )->setup_hooks();
