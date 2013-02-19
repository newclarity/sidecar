<?php
/*
 * Plugin Name: Sidecar for WordPress
 * Plugin URI: http://github.com/newclarity/sidecar
 * Description:
 * Version: 0.4.11
 * Author: NewClarity, MikeSchinkel
 * Author URI: http://newclarity.net
 * Text Domain: sidecar
 * License: GPLv2
 *
 *  Copyright 2012
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License, version 2, as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */
define( 'SIDECAR_FILE', __FILE__ );
define( 'SIDECAR_DIR', dirname( __FILE__ ) );
define( 'SIDECAR_PATH', plugin_dir_path( __FILE__ ) );

define( 'SIDECAR_VER', '0.4.11' );
define( 'SIDECAR_MIN_PHP', '5.2.4' );
define( 'SIDECAR_MIN_WP', '3.2' );

/**
 * TODO: Change this to use a class auto-loader (maybe)
 */

require(SIDECAR_DIR . '/classes/class-singleton-base.php');
require(SIDECAR_DIR . '/classes/class-plugin-base.php');
require(SIDECAR_DIR . '/classes/class-admin-page.php');
require(SIDECAR_DIR . '/classes/class-admin-tab.php');
require(SIDECAR_DIR . '/classes/class-form.php');
require(SIDECAR_DIR . '/classes/class-field.php');
require(SIDECAR_DIR . '/classes/class-shortcode.php');

/**
 *
 */
final class Sidecar {
  /**
   * @var string
   */
  private static $_installed_dir = false;

  /**
   * @var string
   */
  private static $_this_url = false;

  /**
   * @var string
   */
  private static $_this_domain = false;


  /**
   * @param string $message
   * @param array $args
   */
  static function show_error( $message, $args ) {
    $args = func_get_args();
    echo '<div class="error"><p><strong>ERROR</strong>[Sidecar]: ' . call_user_func_array( 'sprintf', $args ) . '</p></div>';
  }

  /**
   * Tests an array element for value, first checking for isset().
   *
   * @param array $array
   * @param string $element
   * @param mixed $value
   * @param bool $exactly
   * @return bool
   */
  static function element_is( $array, $element, $value = true, $exactly = false ) {
    return isset( $array[$element] ) && ( $exactly ? $value === $array[$element] : $value == $array[$element] );
  }

  /**
 	 * Returns the domain for this site.
   *
 	 * @return string
 	 */
 	static function this_domain() {
 	  if ( ! self::$_this_domain ) {
 	    $parts = explode( '/', site_url() );
      self::$_this_domain = $parts[2];
     }
     return self::$_this_domain;
  }

  /**
 	 * Returns the directory in which WordPress is installed, or '/' if root.
   *
   * Preceded with a '/' but no trailing '/'
 	 *
 	 * @return string
 	 */
 	static function installed_dir() {
 	  if ( ! self::$_installed_dir ) {
 	    $site_url = site_url();
      if ( 2 == substr_count( $site_url, '/' ) ) {
        self::$_installed_dir = '/';
      } else {
        $regex = '^https?://' . preg_quote( self::this_domain() ) . '(/.*)/?$';
        self::$_installed_dir = preg_replace( "#{$regex}#", '$1', site_url() );
      }
    }
    return self::$_installed_dir;
  }

  /**
   * Returns the current URL.
   *
   * @return bool
   */
  static function this_url() {
 	  if ( ! self::$_this_url ) {
       $installed_dir = self::installed_dir();
       $requested_path = substr( $_SERVER['REQUEST_URI'], strlen( $installed_dir ) );
       self::$_this_url = site_url( $requested_path );
    }
    return self::$_this_url;
 	}
}

