<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

$is_test_env = getenv( 'XXX_ENV' ) === 'test';

$ev_test_domain = 'test.in.docker.local';

// It's necessary for e2e tests,
// without this action WP-core redirects internal (port=80) selenuim requests to the port 8000
if ( $is_test_env && isset( $_SERVER['HTTP_HOST'] ) && $_SERVER['HTTP_HOST'] === $ev_test_domain ) {
    define( 'WP_HOME', 'http://' . $ev_test_domain );
    define( 'WP_SITEURL', 'http://' . $ev_test_domain );
}

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', $is_test_env ? 'wordpress_test' : 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'wordpress' );

/** MySQL database password */
define( 'DB_PASSWORD', 'wordpress' );

/** MySQL hostname */
define( 'DB_HOST', 'mariadb' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         ']kb/mvd*uPF/|gXy(uK.)BE-py{X]`V^AIyo1rmLD!)JJS%)?d#@gq,3?! n5+hP');
define('SECURE_AUTH_KEY',  'een/Q7>.m7js-EMU6la6zZ]+f{ {jHbcXE[u;BdGo0RK2?r9y.+q-Xu +]?U}3Nk');
define('LOGGED_IN_KEY',    'O1e-@9&pG+K$or{&4%`94o;IwePKaW~4hLBZ`p3-<)!d|,v5rVdp.1$>648SivN]');
define('NONCE_KEY',        'b,j*@EfEz<xB5[ev]Y5fvx~1Jb|f12de8kLH4@(BtaR![`ohM+T(KeLvP9#2F-ju');
define('AUTH_SALT',        ':LE2|y`$_qAp%&:QRQ.>NXp9! i&I4lcTkCyYXJ(GX)uRAL{4?~TPgF?8ZKYe.~-');
define('SECURE_AUTH_SALT', '?~|||32-~-<T5~7D@hW;T[&nBzrHWwcO&92P/19a!Y]TK|FH[WckZ[q+4]K[A+U=');
define('LOGGED_IN_SALT',   'RCX6z]*Ml:{Fst/4++A_*+wD,1/)E>XC|/XTZW-iw[CRApq0T~SA}maJ|NW/neR?');
define('NONCE_SALT',       '9lf0ofA}.7-U0G^Lfv! oI~zf+J?>66N+q|v?VMCF/dTS<~eR3yXr+)i|+c,^gO,');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = $is_test_env ? 'test_' : 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', true );

// @see https://make.wordpress.org/core/2020/07/24/new-wp_get_environment_type-function-in-wordpress-5-5/
define( 'WP_ENVIRONMENT_TYPE', 'development' );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
