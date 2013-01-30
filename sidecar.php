<?php
/*
 * Plugin Name: Sidecar for WordPress
 * Plugin URI: http://github.com/newclarity/sidecar
 * Description:
 * Version: 0.4.2
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

define( 'SIDECAR_VER', '0.4.2' );
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
class Sidecar {

  /**
   * @param string $message
   * @param array $args
   */
  static function show_error( $message, $args ) {
    $args = func_get_args();
    echo '<div class="error"><p><strong>ERROR</strong>[Sidecar]: ' . call_user_func_array( 'sprintf', $args ) . '</p></div>';
  }
}

