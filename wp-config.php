<?php
/**
 * The base configurations of the WordPress.
 *
 * This file has the following configurations: MySQL settings, Table Prefix,
 * Secret Keys, WordPress Language, and ABSPATH. You can find more information
 * by visiting {@link http://codex.wordpress.org/Editing_wp-config.php Editing
 * wp-config.php} Codex page. You can get the MySQL settings from your web host.
 *
 * This file is used by the wp-config.php creation script during the
 * installation. You don't have to use the web site, you can just copy this file
 * to "wp-config.php" and fill in the values.
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'gouwesta_ontwikkelen');

/** MySQL database username */
define('DB_USER', 'gouwesta_ontwikk');

/** MySQL database password */
define('DB_PASSWORD', 'Welk0m01');

/** MySQL hostname */
define('DB_HOST', 'localhost');

/** Database Charset to use in creating database tables. */
define('DB_CHARSET', 'utf8');

/** The Database Collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY',         'oe)wo%X(%sQI4T|@;OI6-;[*iiIuwaWDn|j%W4e&iB[xof=?%~}$OMq&#[v.KBxW');
define('SECURE_AUTH_KEY',  'B8=Q1z~Eq-#US62fO5I_xaEyI=xhjA<cp_rN|O^>Ww`n4,$_CMt0;qL?rY4t%,SO');
define('LOGGED_IN_KEY',    'd+RJ,Xr>.}tV|z]SGQv1-#EB@p6Bm_(%bI)vIXpLY}I%wB]IOu2:T&--.?+jtJv1');
define('NONCE_KEY',        'J}:U5=T(&y$3?W8}it%~|Hg_KPG#PYm|PWY7&u7Y/}VJF}88uL/YBI9OB)PWyQ@l');
define('AUTH_SALT',        '~ctn:p9+}wemnS45Bh1Kn7.l@OSU$hiGHkk9pox+bUe:8[$7MEZME`?=3uBcg(`V');
define('SECURE_AUTH_SALT', '?au[H%f:9KbA=E1Y!=xjAdA{/}!.:wqw`.vUj(&-{Hf^/g&#f+[@sgyeWKUt`8}N');
define('LOGGED_IN_SALT',   ')wW[y+Q`$q0H>/}T1}nB(??RSVY]<,B0#~FX23ucx$-V0>#ZP7:ixCnP2ldD0!hh');
define('NONCE_SALT',       '|d@S(M(0f3QuD(+c3$Cufpn>pds:~}Y,.R9QpGWB(~x47nNI|wz?ytO!Yls|||Xh');

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each a unique
 * prefix. Only numbers, letters, and underscores please!
 */
$table_prefix  = 'wp_';

/**
 * WordPress Localized Language, defaults to English.
 *
 * Change this to localize WordPress. A corresponding MO file for the chosen
 * language must be installed to wp-content/languages. For example, install
 * de_DE.mo to wp-content/languages and set WPLANG to 'de_DE' to enable German
 * language support.
 */
define('WPLANG', 'nl_NL');

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 */
define('WP_DEBUG', false);

/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( !defined('ABSPATH') )
	define('ABSPATH', dirname(__FILE__) . '/');

/** Sets up WordPress vars and included files. */
require_once(ABSPATH . 'wp-settings.php');

define('WP_HOME','http://gouwestadmakelaardij.nl/ontwikkel/');
define('WP_SITEURL','http://gouwestadmakelaardij.nl/ontwikkel/');
