<?php
/**
 * Surrogate for WordPress
 * @author: Mike Schinkel <mike@newclarity.net>
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

require( SURROGATE_DIR . '/classes/class-plugin-base.php' );
require( SURROGATE_DIR . '/classes/class-admin-page.php' );
require( SURROGATE_DIR . '/classes/class-admin-tab.php' );
require( SURROGATE_DIR . '/classes/class-admin-form.php' );
require( SURROGATE_DIR . '/classes/class-admin-field.php' );
require( SURROGATE_DIR . '/classes/class-settings.php' );
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
