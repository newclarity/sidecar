<?php
/**
 *
 */
class Surrogate_Plugin_Base {
  /**
   * @var array
   */
  private static $_me = array();
  /**
   * @var string
   */
  var $plugin_class;
  /**
   * @var string
   */
  var $plugin_class_base;
  /**
   * @var string
   */
  var $plugin_name;
  /**
   * Dashed version of $this->plugin_name
   *
   * @var string
   */
  var $plugin_slug;
  /**
   * @var string
   */
  var $plugin_version;
  /**
   * @var string
   */
  var $plugin_title;

  /**
   * @var string
   */
  var $plugin_label;

  /**
   * @var string
   */
  var $css_base;

  /**
   * @var string
   */
  var $plugin_file;
//  /**
//   * @var string
//   */
//  var $plugin_file_base;
  /**
   * @var string
   */
  var $plugin_path;
  /**
   * @var string Minimum PHP version, defaults to min version for WordPress
   */
  var $min_php = '5.2.4';
  /**
   * @var string Minimum WordPress version, defaults to first version requiring PHP 5.2.4.
   */
  var $min_wp = '3.2';
  /**
   * @var string Cron recurrance
   */
  var $cron_recurrance = 'hourly';
  /**
   * @var string Key used for cron for this plugin
   */
  var $cron_key;
  /**
   * @var array Array of URLs defined for handle use by plugin.
   */
  protected $_urls = array();
  /**
   * @var bool|Surrogate_Settings
   */
  protected $_settings = false;
  /**
   * @var bool|array
   */
  protected $_admin_forms = false;
  protected $_admin_pages = array();
  protected $_shortcodes = array();

  /**
   * @var Surrogate_Admin_Page
   */
  var $current_page;

  /**
   * @var Surrogate_Admin_Form
   */
  var $current_form;

  /**
   * @var Surrogate_Settings
   */
  var $current_settings;

  /**
   * @param array $args
   */
  function __construct( $args = array() ) {
    $this->plugin_class = get_class( $this );
    if ( isset( self::$_me[$this->plugin_class] ) ) {
      $message = __( '%s is a singleton class and cannot be instantiated more than once.', 'surrogate' );
      Surrogate::show_error( $message , $this->plugin_class );
    }
    self::$_me[$this->plugin_class] = &$this;

    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value )
      if ( property_exists(  $this, $property ) )
        $this->$property = $value;

    add_action( 'init', array( $this, 'init' ) );

    $this->plugin_class_base = preg_replace( '#^(.*?)_Plugin$#', '$1', $this->plugin_class );

    if ( ! $this->plugin_name )
      $this->plugin_name = strtolower( $this->plugin_class_base );

    if ( ! $this->cron_key )
      $this->cron_key = "{$this->plugin_name}_cron";

    register_activation_hook( $this->plugin_file, array( $this, 'activate' ) );
    register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate' ) );

    /**
     * Ask subclass to initialize plugin which includes admin pages
     */
    $this->initialize_plugin();
  }

  /**
   * @return bool
   */
  function has_admin_pages() {
    return 0 < count( $this->_admin_pages );
  }
  /**
   * @param string $form_name
   *
   * @return bool
   */
  function has_admin_form( $form_name ) {
    return isset( $this->_admin_forms[$form_name] );
  }

  function initialize() {

    if ( ! $this->plugin_title )
      /*
       * Hope we can get something better in the subclass' $this->initialize()...
       */
      $this->plugin_title = ucwords( str_replace( '_', ' ', $this->plugin_name ) );

    if ( ! $this->plugin_label )
      /*
       * Used for menu title for default
       */
      $this->plugin_label = $this->plugin_title;

    if ( ! $this->plugin_file )
      Surrogate::show_error( '%s->plugin_file must be set in an %s->initialize_admin() method', get_class( $this), get_class( $this) );

//    $this->plugin_file_base = preg_replace( '#^(.*)-plugin\.php$#', '$1', $this->plugin_file );

    if ( ! $this->plugin_path )
      $this->plugin_path = dirname( $this->plugin_file );

    if ( ! $this->plugin_slug )
      $this->plugin_slug = str_replace( '_', '-', $this->plugin_name );

    if ( ! $this->css_base )
      $this->css_base = $this->plugin_slug;


  }

  function init() {
    add_action( 'cron_schedules', array( $this, 'cron_schedules' ) );
    add_action( 'cron', array( $this, 'cron' ) );
    /**
     * @todo Figure out how to load this only if needed
     */
    load_plugin_textdomain( $this->plugin_slug, false, '/' . basename( dirname( $this->plugin_file ) ) . '/languages' );

    if ( is_admin() ) {
      /**
       * @todo Can we initialize only what's needed if it is not using one of the pages
       */
      if (true) {
        /**
         * Now set all the defaults
         */
        $this->initialize();

        /**
         * Call the subclass and ask it to initialize itself
         */
        $this->initialize_admin();

        if ( $this->plugin_version )
          $this->plugin_title .= sprintf( ' v%s', $this->plugin_version );

        /**
         *
         * @todo Find a way to avoid initializing if not needed.
         *
         */
        if ( 0 == count( $this->_admin_pages ) )
          $this->add_default_admin_page();

        if ( 0 == count( $this->_shortcodes ) )
          $this->add_default_shortcode();

      }
    }
  }
  /**
   * @throws Exception
   */
  function initialize_plugin() {
    throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an initialize_plugin() method.' );
  }
  /**
   * @throws Exception
   */
  function initialize_admin() {
    throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an initialize_admin() method.' );
  }
  /**
   * @param Surrogate_Admin_Page $admin_page
   * @throws Exception
   */
  function initialize_admin_page( $admin_page ) {
    throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an initialize_admin_page() method.' );

  }


  /**
   * @param array $args
   *
   * @return mixed
   */
  function add_default_admin_page( $args = array() ) {
    /**
     * If we subclass, we'll need these:
     *
     * "{$this->plugin_class_base}_Admin_Page",    // Default admin page class
     * "{$this->plugin_file_base}-admin-page.php",  // Default admin page class filepath
     */
    return $this->add_admin_page( "settings", 'Surrogate_Admin_Page', null, $args );
  }
  /**
   * @param array $args
   *
   * @return mixed
   */
  function add_default_button( $args = array() ) {
    /**
     * @var Surrogate_Admin_Form
     */
    $form = isset( $args['form'] ) ? $args['form'] : end( $this->_admin_forms );
    return $form->add_button( 'save', __( 'Save Settings', 'surrogate' ), $args );
  }
  /**
   * @param string $button_name
   * @param string $button_text
   * @param array $args
   *
   * @return mixed
   */
  function add_button( $button_name, $button_text, $args = array() ) {
    /**
     * @var Surrogate_Admin_Form
     */
    $form = isset( $args['form'] ) ? $args['form'] : end( $this->_admin_forms );
    return $form->add_button( $button_name, $button_text, $args );
  }

  /**
   * @param       $page_name
   * @param       $admin_page_class
   * @param       $admin_page_filepath
   * @param array $args
   *
   * @return mixed
   */
  function add_admin_page( $page_name, $admin_page_class, $admin_page_filepath, $args = array() ) {
    if ( ! empty( $admin_page_filepath ) ) {
      if ( WP_DEBUG && ! file_exists( $admin_page_filepath ) ) {
        $message = __( 'There is no file %s for the default admin page class used by %s.', 'surrogate' );
        Surrogate::show_error( $message, $admin_page_filepath, $this->plugin_class );
      }
      require( $admin_page_filepath );
    }

    if ( WP_DEBUG && ! class_exists( $admin_page_class ) ) {
      $message = __( 'There is no default admin page class %s has been defined for %s.', 'surrogate' );
      Surrogate::show_error( $message, $admin_page_class, $this->plugin_class );
    }

    /**
     * Give the admin page access back to this plugin.
     */
    $args['plugin'] = $this;
    $args['plugin_slug'] = "{$this->plugin_slug}-{$page_name}";
    /**
     * @var Surrogate_Admin_Page $admin_page
     */
    return $this->_admin_pages[$page_name] = new $admin_page_class( $page_name, $args );
  }

  /**
   * @param string $page_name
   *
   * @return Surrogate_Admin_Page
   */
  function get_admin_page( $page_name ) {
    return $this->_admin_pages[$page_name];
  }
  function add_default_shortcode() {
    $this->add_shortcode( $this->plugin_slug );
  }

  /**
   * @param       $shortcode_name
   * @param array $args
   */
  function add_shortcode( $shortcode_name, $args = array() ) {
  }

//  /**
//   * @return mixed
//   */
//  protected function _get_subclass_filename() {
//    $subclass = new ReflectionObject( $this );
//    return $subclass->getFileName();
//  }

  /**
   * @param $schedules
   *
   * @return mixed
   */
  function cron_schedules( $schedules ) {
 		$schedules['fifteenseconds'] = array( 'interval' => 15, 'display' => __( 'Once Every Fifteen Seconds' ) );
 		return $schedules;
 	}

  /**
   * @return bool
   */
  function cron() {
    return true;
  }

  function deactivate() {
 		/**
 		 * Unschedule cron
 		 */
 		$next_run = wp_next_scheduled( $this->cron_key );
 		wp_unschedule_event( $next_run, $this->cron_key );
 	}

  /**
   * @param       $url_name
   * @param       $url_template
   * @param array $args
   */
  function register_url( $url_name, $url_template, $args = array() ) {
    $args['url_name'] = $url_name;
    $args['url_template'] = $url_template;
    if ( ! isset( $args['url_vars'] ) ) {
      preg_match_all( '#([^{]+)\{([^}]+)\}#', $url_template, $matches );
      $args['url_vars'] = $matches[2];
    }
    $this->_urls[$url_name] = $args;
  }

  /**
   * @param string $url_name
   *
   * @return bool
   */
  function has_url( $url_name ) {
    return isset( $this->_urls[$url_name] );
  }
  /**
   * Get by name a previously registered URL with optional variable value replacement
   *
   * @param             $url_name
   * @param mixed|array $values
 	 * @example:
   *
   *    $this->register_url( 'my_named_url', 'http://example.com/?item={item_id}&color={color}' );
   *    $this->get_url( 'my_named_url', 1234, 'red' );
	 *    $this->get_url( 'my_named_url', array( 2345, 'red' ) );
	 *    $this->get_url( 'my_named_url', array( 'color' => 'red', 'item_id' => 3456 ) );
   *
   * @return string
   */
  function get_url( $url_name, $values = array() ) {
    if ( ! is_array( $values ) ) {
      /**
       * $values passed as additional function parameters instead of as single array of parameters.
       */
      $values = func_get_args();
      array_shift( $values );
    }
    $url = $this->_urls[$url_name]['url_template'];
    if ( is_array( $values ) ) {
      $vars = $this->_urls[$url_name]['url_vars'];
      if ( is_numeric( key($values) ) ) {
        /**
         * The $values array contains name/value pairs.
         */
        foreach( $values as $name => $value ) {
          $url = str_replace( "{{$vars[0]}}", $value, $url );
        }
      } else {
        /**
         * The $values array just contains values in same order as the vars specified in the URL.
         */
        foreach( $vars as $name ) {
          $url = str_replace( "{{$name}}", $values[$name], $url );
        }
      }
    }
    return $url;
 	}

  /**
   * @return bool
   */
  function is_authenticated() {
 		return false;
 	}

  /**
   * Echo the current or specified form.
   *
   * @param bool|Surrogate_Admin_Form $form
   *
   * @return Surrogate_Admin_Form
   */
  function the_form( $form = false ) {
    if ( ! $form )
      $form = $this->current_form;
    return $form->the_form();
 	}

  /**
   * @param   array   $admin_form
   * @return  Surrogate_Admin_Form
   */
  function promote_admin_form( $admin_form ) {
    /**
     * @var array $form
     */
    $form_name = $admin_form['form_name'];
    $admin_form['plugin'] = $this;

    if ( ! isset( $admin_form['admin_page'] ) )
      $admin_form['admin_page'] = end( $this->_admin_pages );

    /**
     * @var array|Surrogate_Admin_Form $admin_form
     */
    $admin_form = $this->_admin_forms[$form_name] = new Surrogate_Admin_Form( $form_name, $admin_form );

    $this->current_form = $admin_form;
    return $admin_form;
  }
  /**
   * @param array $args
   */
  function the_form_section( $args ) {
    if ( ! empty( $args['section']->section_text ) )
      echo $args['section']->section_text;
  }

  /**
   * @param $args
   *
   * @return Surrogate_Admin_Field
   */
  function get_form_field( $args ) {
    /**
     * @var Surrogate_Admin_Form $form
     */
    $form = $this->get_admin_form( $args['form']->form_name );
    $field = $form->get_form_field( $args['field']->field_name, array( 'section_name' => $args['section']->section_name ) );
    return $field;
  }
  /**
   * @param array $args
   */
  function get_form_field_html( $args ) {
    /**
     * @var Surrogate_Admin_Field $field
     */
    $field = $this->get_form_field( $args );
    return $field->get_field_html();
  }

  /**
   * @param array $args
   */
  function the_form_field( $args ) {
    echo $this->get_form_field_html( $args );
  }

  /**
   * @param string $setting_name
   * @return bool
   */
  function has_setting( $setting_name ) {
    return isset( $this->_settings[$setting_name] );
  }
  /**
   * @param   string|Surrogate_Admin_Form $admin_form
   * @return  array|Surrogate_Admin_Form
   */
  function get_admin_form( $admin_form ) {
    /*
     * Could be a string or already Surrogate_Admin_Form.
     */
    $admin_form = is_string( $admin_form ) && isset( $this->_admin_forms[$admin_form] ) ? $this->_admin_forms[$admin_form] : $admin_form;
    if ( is_array( $admin_form ) ) {
      /**
       * Convert the form from array to object.
       */
      $admin_form = $this->promote_admin_form( $admin_form );
      $this->initialize_admin_form( $admin_form );
      $admin_form->initialize( $this );
    }
    return $admin_form;
  }
  function initialize_admin_form() {
    throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an initialize() method.' );
  }
  /**
   * @param string  $form_name
   * @param array   $args
   */
  function add_admin_form( $form_name, $args = array() ) {
    $args['form_name'] = $form_name;
    $this->_admin_forms[$form_name] = $args;
  }
  /**
   * @param       $form_name
   * @param array $args
   */

  /**
   * @param string  $form_name
   * @param array   $args
   * @return Surrogate_Admin_Field
   */
  function add_field( $form_name, $args = array() ) {
    /**
     * @var Surrogate_Admin_Form
     */
    $form = isset( $args['form'] ) ? $args['form'] : end( $this->_admin_forms );
    return $form->add_field( $form_name, $args );
  }
  /**
   * @param       $form_name
   * @param array $args
   */
  /**
   * @param string $settings_name
   * @param array  $args
   */
  function register_settings( $settings_name, $args = array() ) {
    $this->_settings[$settings_name] = new Surrogate_Settings( $settings_name, $args );
  }
  /**
   * @param string $settings_name
   * @return Surrogate_Settings
   */
  function get_settings( $settings_name ) {
    return $this->_settings[$settings_name];
  }
  /**
   * @param array $input
   *
   * @return array
   */
  function filter_postback( $input ) {
    if ( ! current_user_can( 'manage_options' ) ) {
      /**
       * TODO: Verify someone without proper options can actually get here.
       */
      wp_die( __( 'Sorry, you do not have sufficient priviledges.' ) );
    }
    /**
     * Get the array that contains names of 'plugin', 'page', 'tab', 'form' and 'settings'
     * as well as special 'clear' and 'reset' for clearing and resetting the form respectively.
     */
    $control = $_POST[$_POST['option_page']];
    $page = $this->current_page = $this->get_admin_page( $control['page'] );
    $form = $this->current_form = $this->get_admin_form( $control['form'] );
    $settings = $this->current_settings = $this->get_settings( $control['settings'] );
    if ( isset( $control['clear'] ) ) {
      $input = $settings->get_empty_settings();
      $message = __( 'Form values cleared.%s%sNOTE:%s Your browser may still be displaying values from its cache but this plugin has indeed cleared these values.%s', 'surrogate' );
      add_settings_error( $page->get_settings_group_name(), "surrogate-clear", sprintf( $message, "<br/><br/>&nbsp;&nbsp;&nbsp;", '<em>', '</em>', '<br/><br/>' ), 'updated' );
    } else if ( isset( $control['reset'] ) ) {
      $input = $this->current_settings->get_new_settings();
      add_settings_error( $page->get_settings_group_name(), 'surrogate-reset', __( 'Defaults reset.', 'surrogate' ), 'updated' );
    } else if ( method_exists( $this, 'validate_settings' ) ) {
      $input = array_map( 'rtrim', (array)$input );
      add_filter( $action_key = "pre_update_option_{$this->current_settings->option_name}", array( $this, 'pre_update_option' ), 10, 2 );
      /**
       * @todo How to signal a failed validation?
       */
      $input = call_user_func( array( $this, 'validate_settings' ), $input, $this->current_settings );
      /**
       * @var Surrogate_Admin_Field $field
       */
      foreach( $form->get_fields() as $field_name => $field ) {
        $validation_options = false;
   			/**
   			 * Default to FILTER_SANITIZE_STRING if ['validator'] not set.
   			 */
   			if ( $field->field_options ) {
          $validated_value = isset( $field->field_options[$input[$field_name]] ) ? $input[$field_name] : false;
        } else if ( isset( $field->field_validator['filter'] ) ) {
           $validated_value = filter_var( $input[$field_name], $field->field_validator['filter'] );
           if ( isset( $field->field_validator['options'] ) ) {
            $validation_options = $field->field_validator['options'];
           }
        } else {
          $validator = $field->field_validator ? $field->field_validator : FILTER_SANITIZE_STRING;
          $validated_value = filter_var( $input[$field_name], $validator );
        }
        if ( method_exists( $this, $method = "sanitize_setting_{$field_name}" ) ) {
          $validated_value = call_user_func( array( $this, $method ), $validated_value, $field, $settings );
        }
        if ( $validation_options || $validated_value != $input[$field_name] ) {
          if ( ! $validation_options ) {
            add_settings_error( $page->get_settings_group_name(), 'surrogate-value', sprintf(
              __( 'You need to enter a valid value for "%s."', 'surrogate' ), $field->field_label
            ));
          } else {
            if ( isset( $validation_options['min'] ) && $validation_options['min'] > intval( $input[$field_name] ) ) {
              add_settings_error( $page->get_settings_group_name(), 'surrogate-min', sprintf(
                __( 'You need to enter a value greater than or equal to %d for "%s."', 'surrogate' ),
                  $validation_options['min'],
                  $field->field_label
              ));
            }
            if ( isset( $validation_options['max'] ) && $validation_options['max'] < intval( $input[$field_name] ) ) {
              add_settings_error( $page->get_settings_group_name(), 'surrogate-max', sprintf(
                __( 'You need to enter a value less than or equal to %d for "%s."', 'surrogate' ),
                  $validation_options['max'],
                  $field->field_label
              ));
              $continue = true;
            }
          }
        }
      }
    }
    return $input;
  }


  /**
   * @param array $newvalue
   * @param array $oldvalue
   * @return array
   */
  function pre_update_option( $newvalue, $oldvalue ) {
    if ( method_exists( $this, 'encrypt_settings' ) ) {
      /**
       * @todo auto-encrypt credentials
       */
      $newvalue = call_user_func( array( $this, 'encrypt_settings' ), $newvalue, $this->current_settings );
    }
    remove_filter( current_filter(), array( $this, 'pre_update_option' ) );
    return $newvalue;
  }
  /**

   * @todo Decide if trigger_error() is the best for error messages
   */
  function activate() {
    global $wp_version;
    if ( version_compare( $wp_version, $this->min_wp, '<' ) ) {
      deactivate_plugins( basename( $this->plugin_file ) );
      $msg = __( 'Your site needs to be running WordPress %s or later in order to use %s.', 'surrogate' );
      trigger_error( sprintf( $msg, $this->min_wp, $this->plugin_title ), E_USER_ERROR );
    } if ( version_compare( PHP_VERSION, $this->min_php, '<' ) ) {
      deactivate_plugins( basename( $this->plugin_file ) );
      $msg = __( 'Your site needs to be running PHP %s or later in order to use %s.', 'surrogate' );
      trigger_error( sprintf( $msg, $this->min_php, $this->plugin_title ), E_USER_ERROR );
    } else {
      if ( ! wp_next_scheduled( $this->cron_key ) ) {
        wp_schedule_event( time(), $this->cron_recurrance, $this->cron_key );
      }
//      $this->initialize_all_settings();
    }
  }

  /**
   * @param $property_name
   * @return bool|string
   */
  function __get( $property_name ) {
    $value = false;
    if ( preg_match( '#^(.+?)_url$#', $property_name, $match ) && $this->has_url( $match[1] ) ) {
      /**
       * Allows magic property syntax for any registered URL
       * @example: $this->foobar_url calls $this-get_url( 'foobar' )
       * Enables embedding in a HEREDOC or other doublequoted string
       * without requiring an intermediate variable.
       */
      $value = call_user_func( array( $this, "get_url" ), $match[1] );
    } else {
      Surrogate::show_error( 'No property named %s on %s class.', $property_name, get_class( $this ) );
    }
    return $value;
  }
}





