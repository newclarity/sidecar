<?php
/*
 * Plugin Name: Surrogate for WordPress
 * Plugin URI: http://github.com/newclarity/surrogate
 * Description:
 * Version: 0.1
 * Author: NewClarity, MikeSchinkel
 * Author URI: http://newclarity.net
 * Text Domain: surrogate
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
define( 'SURROGATE_FILE', __FILE__ );
define( 'SURROGATE_DIR', dirname( __FILE__ ) );
define( 'SURROGATE_PATH', plugin_dir_path( __FILE__ ) );

define( 'SURROGATE_VER', 'beta' );
define( 'SURROGATE_MIN_PHP', '5.2.4' );
define( 'SURROGATE_MIN_WP', '3.2' );

/**
 * TODO: Change this to use a class auto-loader (maybe)
 */

require( SURROGATE_DIR . '/classes/class-plugin.php' );
require( SURROGATE_DIR . '/classes/class-admin-page.php' );
require( SURROGATE_DIR . '/classes/class-admin-tab.php' );
require( SURROGATE_DIR . '/classes/class-admin-form.php' );
require( SURROGATE_DIR . '/classes/class-admin-field.php' );
require( SURROGATE_DIR . '/classes/class-shortcode.php' );

/**
 *
 */
class Surrogate {

  /**
   * @param string $message
   * @param array $args
   */
  static function show_error( $message, $args ) {
    $args = func_get_args();
    echo '<div class="error"><p><strong>ERROR</strong>[Surrogate]: ' . call_user_func_array( 'sprintf', $args ) . '</p></div>';
  }
}
