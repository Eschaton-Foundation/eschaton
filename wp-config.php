<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'eschaton_clean' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', 'root' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'tTlU&-&GOUYi^3&q,oV+Xuw1y{X<Z<>JSh`28![8MzV0Wymk@!?tD3wrfAgu7^&K' );
define( 'SECURE_AUTH_KEY',   'ojEl59X*!XKN&3$f41m.3=$dxA*VTdSi%B$;ohQ[{Cwr~*f=3SY!LjDxFRt_FUID' );
define( 'LOGGED_IN_KEY',     'hdYTjYR[P2H64<*=6Eo=6:jwu[Pv]JhyU} L^odAmY4QkKso$O.:]Y0GA$53a.6m' );
define( 'NONCE_KEY',         'bXMY0vyn2p,3U7ts`L<$pWyI59[ENEK%d/>lnNiv)~T&5A;#_OgOJ/<H^;k5<aDD' );
define( 'AUTH_SALT',         '5I(wb8+J#]$xPCzF&bMIx^mqFp^hCvqZ+RAyLpAW&4}FyTw](@xgrBuQ^K87T2?$' );
define( 'SECURE_AUTH_SALT',  'BN>_+=TW6v[FVr|bBVKoFQGr ]u/lOfZ3UDSvJFMOq%Qllfonws??<B_G[1b@Ux,' );
define( 'LOGGED_IN_SALT',    'Io([pyfgdj+Q^C9=!OSGdr[J~kKIl4yy{%9>{2diY:8lziQ$R^vXIZrLG;6VvHmp' );
define( 'NONCE_SALT',        '~1sH|%UFi9K}h#R3%qHH>&%2y[Ax B+G7DYS@SM/*)`_3t#S;VKmEfQIxfxjg5i[' );
define( 'WP_CACHE_KEY_SALT', 'iM$0[i_,z_=,%RS?: C}{u:GxJbi>t8xqWAl0^g#,NK[?)P/}E]EvikT4BhVWaaH' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'prod_';


/* Add any custom values between this line and the "stop editing" line. */



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
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
	define( 'SCRIPT_DEBUG', true );
	define( 'WP_DEBUG_LOG', true );
	define( 'WP_DEBUG_DISPLAY', true );
}

define('FS_METHOD', 'direct');


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
