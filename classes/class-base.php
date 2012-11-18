<?php
/**
 *
 */
class Sidecar_Base {
  /**
   * @var array
   */
  protected static $_me = array();
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
   * @var array Array of Image file names defined for handle use by plugin.
   */
  protected $_images = array();
  /**
   * @var bool|array
   */
  protected $_shortcodes = false;
  /**
   * @var bool|array
   */
  protected $_forms = false;
  /**
   * @var bool|array
   */
  protected $_admin_pages = array();

  /**
   * @var Sidecar_Admin_Page
   */
  var $current_page;

  /**
   * @var Sidecar_Form
   */
  var $current_form;

  /**
   * @var bool|RESTian_Client
   */
  var $api = false;
  /**
   * @var bool|string
   */
  var $api_loader = false;
  /**
   * @var bool|string
   */
  var $api_class = false;
  /**
   * @var bool
   */
  protected $_initialized = false;
  /**
   * @var array
   */
  protected $_settings = array();
  /**
   * @var string
   */
  var $option_name;


  /**
   * @param array $args
   */
  function __construct( $args = array() ) {
    $this->plugin_class = get_class( $this );
    if ( isset( self::$_me[$this->plugin_class] ) ) {
      $message = __( '%s is a singleton class and cannot be instantiated more than once.', 'sidecar' );
      Sidecar::show_error( $message , $this->plugin_class );
    }
    self::$_me[$this->plugin_class] = &$this;

    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value )
      if ( property_exists(  $this, $property ) )
        $this->$property = $value;

    add_action( 'init', array( $this, 'init' ) );
    add_action( 'wp_loaded', array( $this, 'wp_loaded' ) );
    add_action( 'wp_print_styles', array( $this, 'wp_print_styles' ) );

    $this->plugin_class_base = preg_replace( '#^(.*?)_Plugin$#', '$1', $this->plugin_class );

    if ( ! $this->plugin_name )
      $this->plugin_name = strtolower( $this->plugin_class_base );

    if ( ! $this->option_name )
      $this->option_name = "{$this->plugin_name}_settings";

    if ( ! $this->cron_key )
      $this->cron_key = "{$this->plugin_name}_cron";

    register_activation_hook( $this->plugin_file, array( $this, 'activate' ) );
    register_deactivation_hook( $this->plugin_file, array( $this, 'deactivate' ) );

    /**
     * Ask subclass to initialize plugin which includes admin pages
     */
    $this->initialize_plugin();

    if ( false === strpos( $this->plugin_file, WP_CONTENT_DIR ) ) {
      /**
       * This plugin is being symlinked.
       * @see: http://wordpress.stackexchange.com/questions/15202/plugins-in-symlinked-directories
       * @see: http://wordpress.stackexchange.com/a/15204/89
       */
      global $mu_plugin, $network_plugin, $plugin;
      if ( isset( $mu_plugin ) ) {
        $this->plugin_file = $mu_plugin;
      } else if ( is_multisite()  && isset( $network_plugin ) ) {
        $this->plugin_file = $network_plugin;
      } else if ( isset( $plugin ) ) {
        $this->plugin_file = $plugin;
      }
    }


  }

  /**
   * @return array
   */
  function get_credentials() {
    return $this->has_form( 'credentials' ) ? $this->get_settings( 'credentials' ) : false;
  }

  /**
   * @param string|Sidecar_Form $form
   * @return array
   */
  function get_settings( $form ) {
    if ( is_string( $form ) && $this->has_form( $form ) )
      $form = $this->get_form( $form );
    if ( ! isset( $this->_settings[$form->settings_key] ) ) {
      $this->initialize_settings( $form );
    }
    if ( ! isset( $this->_settings['state']['decrypted'][$form->form_name] ) ) {
      if ( method_exists( $this, 'decrypt_settings' ) ) {
        $this->_settings[$form->settings_key] = call_user_func(
          array( $this, 'decrypt_settings' ),
          $this->_settings[$form->settings_key],
          $form, $this->_settings
        );
      }
      $this->_settings['state']['decrypted'][$form->form_name] = true;
    }
    return $this->_settings[$form->settings_key];
  }

  /**
   * @param string|Sidecar_Form $form
   * @return array
   */
  function initialize_settings( $form ) {
    if ( is_string( $form ) && $this->has_form( $form ) )
      $form = $this->get_form( $form );

    if ( 0 == count( $this->_settings ) ) {
      $this->_settings = get_option( $this->option_name );
      $this->_settings['state'] = array( 'decrypted' => array() );
    }

    if ( ! isset( $this->_settings[$form->settings_key] ) )
      $this->_settings[$form->settings_key] = array();

    if ( $form instanceof Sidecar_Form ) {
      $this->_settings[$form->settings_key] = array_merge(
        $form->get_new_settings(),
        $this->_settings[$form->settings_key]
      );
    }
  }

  /**
   * @param string|Sidecar_Form $form
   * @param string $setting_name
   * @return bool
   */
  function has_setting( $form, $setting_name ) {
    if ( ! $form instanceof Sidecar_Form )
      if ( is_string( $form ) && $this->has_form( $form ) ) {
        $form = $this->get_form( $form );
      }
    if ( ! isset( $this->_settings[$form->settings_key] ) ) {
      /*
       * Call to initialize settings
       */
      $this->initialize_settings( $form );
    }
    return isset( $this->_settings[$form->settings_key][$setting_name] );
  }
  /**
   * @param string|Sidecar_Form $form
   * @param string $setting_name
   * @return string
   */
  function get_setting( $form, $setting_name ) {
    $value = false;
    if ( $this->has_setting( $form, $setting_name ) )
      $value = $this->_settings[$form->settings_key][$setting_name];
    return $value;
  }
  /**
   * @param string|Sidecar_Form $form
   * @param string $setting_name
   * @param mixed $value
   */
  function set_setting( $form, $setting_name, $value ) {
    if ( ! $form instanceof Sidecar_Form )
      if ( is_string( $form ) && $this->has_form( $form ) ) {
        $form = $this->get_form( $form );
      }
    if ( $form instanceof Sidecar_Form && isset( $this->_settings[$form->settings_key] ) )
 	    $this->_settings[$form->settings_key][$setting_name] = $value;
  }
  /**
   * @return Sidecar_Base
   */
  static function me() {
    return self::$_me;
  }

  /**
   *
   */
  function wp_print_styles() {
 	  $localfile = 'css/style.css';
    $args = apply_filters( 'sidecar_print_styles', array(
      'name'  => "{$this->plugin_name}_style",
      'path'  => "{$this->plugin_path}/{$localfile}",
      'url'   => plugins_url( $localfile, $this->plugin_file ),
    ));
 	  if ( file_exists( $args['path'] ) )
 		  wp_enqueue_style( $args['name'], $args['url'] );
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
  function has_form( $form_name ) {
    return isset( $this->_forms[$form_name] );
  }

  function initialize() {
    if ( $this->_initialized )
      return;

    /*
     * Avoid potential infinite loop.
     */
    $this->_initialized = true;

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
      Sidecar::show_error( '%s->plugin_file must be set in an %s->initialize_admin() method', get_class( $this), get_class( $this) );

//    $this->plugin_file_base = preg_replace( '#^(.*)-plugin\.php$#', '$1', $this->plugin_file );

    if ( ! $this->plugin_path )
      $this->plugin_path = dirname( $this->plugin_file );

    if ( $this->api_loader ) {
      $this->api_loader = "{$this->plugin_path}/{$this->api_loader}";
    }

    if ( ! $this->plugin_slug )
      $this->plugin_slug = str_replace( '_', '-', $this->plugin_name );

    if ( ! $this->css_base )
      $this->css_base = $this->plugin_slug;

  }

  /**
   *
   */
  function wp_loaded() {
    if ( ! is_admin() ) {
      $shortcodes = method_exists( $this->plugin_class, 'initialize_shortcodes' );
      $template = method_exists( $this, 'initialize_template' );
      if ( $shortcodes || $template ) {
        // @todo Should we always initialize or only when we need it?
        $this->initialize();
        if ( $shortcodes )
          $this->initialize_shortcodes();
        if ( $template )
          $this->initialize_template();
        add_filter( 'the_content', array( $this, 'the_content' ), -1000 );
      }
    }
  }
  /**
   * @param $content
   */
  function the_content( $content ) {
    foreach( $this->get_shortcodes() as $shortcode_name => $shortcode ) {
      if ( method_exists( $this, 'initialize_shortcode' ) ) {
        $this->initialize_shortcode( $shortcode );
      }
      add_shortcode( $shortcode_name, array( $shortcode, 'do_shortcode' ) );
    }
    /*
     * We only need to do the first time.
     */
    remove_action( 'the_content', array( $this, 'the_content' ), -1000 );
    return $content;
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

      }
    }
  }
  /**
   * @param Sidecar_Shortcode $shortcode
   * @param array $attributes
   * @param string $to 'api_vars' or 'settings'
   * @return array
   */
  function transform_shortcode_attributes( $shortcode, $attributes, $to = 'api_vars' ) {
    $to = rtrim( $to, 's' );
    $attribute_objects = $shortcode->get_attributes();
    foreach( $attributes as $attribute_name => $attribute ) {
      if ( isset( $attribute_objects[$attribute_name] ) ) {
        if ( $attribute_name != ( $new_name = $attribute_objects[$attribute_name]->$to ) ) {
          $attributes[$new_name] = $attribute;
          unset( $attributes[$attribute_name] );
        }
      }
    }
    return $attributes;
  }
  /**
   * @param Sidecar_Form $form
   */
  function initialize_form( $form ) {
    // Only here to keep PhpStorm from complaining that it's not defined.
  }
  /**
   *
   */
  function initialize_shortcodes() {
    // Only here to keep PhpStorm from complaining that it's not defined.
  }
  /**
   *
   */
  function initialize_postback() {
    // Only here to keep PhpStorm from complaining that it's not defined.
  }

  /**
   *
   */
  function initialize_template() {
    // Only here to keep PhpStorm from complaining that it's not defined.
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
   * @param Sidecar_Admin_Page $admin_page
   * @throws Exception
   */
  function initialize_admin_page( $admin_page ) {
    throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an initialize_admin_page() method.' );
  }
  /**
   * @param Sidecar_Shortcode $shortcode
   * @throws Exception
   */
  function initialize_shortcode( $shortcode ) {
    throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an initialize_shortcode() method.' );
  }

  /**
   * @param Sidecar_Shortcode $shortcode
   * @param array() $attributes
   * @param string $content
   *
   * @throws Exception
   * @return string
   */
  function do_shortcode( $shortcode, $attributes, $content = null ) {
    if (1) // Only here to keep PhpStorm from flagging the return as an error.
      throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an do_shortcode() method.' );
    return '';
  }

  /**
   * @param array $args
   *
   * @return mixed
   */
  function add_default_button( $args = array() ) {
    /**
     * @var Sidecar_Form
     */
    $form = isset( $args['form'] ) ? $args['form'] : end( $this->_forms );
    return $form->add_button( 'save', __( 'Save Settings', 'sidecar' ), $args );
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
     * @var Sidecar_Form
     */
    $form = isset( $args['form'] ) ? $args['form'] : end( $this->_forms );
    return $form->add_button( $button_name, $button_text, $args );
  }

  /**
   * @param       $page_name
   * @param array $args
   *
   * @return mixed
   */
  function add_admin_page( $page_name, $args = array() ) {
    /**
     * Give the admin page access back to this plugin.
     */
    $args['plugin'] = $this;
    /**
     * @var Sidecar_Admin_Page $admin_page
     */
    return $this->_admin_pages[$page_name] = new Sidecar_Admin_Page( $page_name, $args );
  }

  /**
   * @param string $page_name
   *
   * @return Sidecar_Admin_Page
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
    $args['plugin'] = $this;
    $this->_shortcodes[ $shortcode_name ] = new Sidecar_Shortcode( $shortcode_name, $args );
  }

  /**
   * @return array|bool
   */
  function get_shortcodes() {
    return $this->_shortcodes;
  }
  /**
   * @param bool|string $shortcode_name
   *
   * @return Sidecar_Shortcode
   */
  function get_shortcode( $shortcode_name = false ) {
    $shortcode = false;

    if ( ! $shortcode_name )
      $shortcode_name = $this->plugin_slug; // This is the 'default' shortcode

    if ( ! isset( $this->_shortcodes[$shortcode_name] ) ) {
      trigger_error( sprintf( __( 'Need to call %s->initialize_shortcodes() before using %s->get_shortcode().' ), $this->plugin_class, $this->plugin_class ) );
    } else {
      /**
       * @var Sidecar_Shortcode $shortcode
       */
      $shortcode = $this->_shortcodes[$shortcode_name];
      if ( ! $shortcode->initialized ) {
        $this->initialize_shortcode($shortcode);
        $shortcode->initialized = true;
      }
    }

    return $shortcode;
  }

  /**
   * @param bool|string $shortcode_name
   *
   * @return bool|Sidecar_Shortcode
   */
  function get_shortcode_attributes( $shortcode_name = false ) {
    $shortcode = $this->get_shortcode($shortcode_name);
    return $shortcode ? $shortcode->get_attributes() : false;
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
   * @param string $url_name
   * @param string $url_template
   * @param array $args
   */
  function register_url( $url_name, $url_template, $args = array() ) {
    $args['url_name'] = $url_name;
    $args['url_template'] = $url_template;
    if ( ! isset( $args['url_vars'] ) )
      $args['url_vars'] = false;
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
      if ( ! $vars ) {
        preg_match_all( '#([^{]+)\{([^}]+)\}#', $url, $matches );
        $vars = $matches[2];
      }
      if ( is_numeric( key($values) ) ) {
        /**
         * The $values array contains name/value pairs.
         */
        foreach( $values as $name => $value ) {
          if ( isset( $vars[0] ) )
            $url = str_replace( "{{$vars[0]}}", $value, $url );
        }
      } else {
        /**
         * The $values array just contains values in same order as the vars specified in the URL.
         */
        foreach( $vars as $name ) {
          if ( isset( $values[$name] ) )
            $url = str_replace( "{{$name}}", $values[$name], $url );
        }
      }
    }
    return $url;
 	}

  /**
   * @param string $image_name
   * @param string $image_url
   * @param array $args
   */
  function register_image( $image_name, $image_url, $args = array() ) {
    $args['image_name'] = $image_name;
    $args['image_url'] = $image_url;
    if ( ! isset( $args['url_vars'] ) )
      $args['image_vars'] = false;
    $this->_images[$image_name] = $args;
  }

  /**
   * @param string $image_name
   *
   * @return bool
   */
  function has_image( $image_name ) {
    return isset( $this->_images[$image_name] );
  }

  /**
   * Get by name a previously registered image with optional variable value replacement
   *
   * @param             $image_name
   * @param mixed|array $values
 	 * @example:
   *
   *    $this->register_image( 'my_logo', 'my-logo.png' );
   *    echo $this->my_logo_image_url;
   *
   *    $this->register_image( 'my_icon', '{icon_type}.png' );
   *    echo $this->get_image_url( 'my_icon', 'pdf' );
   *    echo $this->get_image_url( 'my_icon', array('pdf') );
   *    echo $this->get_image_url( 'my_icon', array('icon_type' => 'pdf') );
   *
   * @return string
   */
  function get_image_url( $image_name, $values = array() ) {
    if ( ! is_array( $values ) ) {
      /**
       * $values passed as additional function parameters instead of as single array of parameters.
       */
      $values = func_get_args();
      array_shift( $values );
    }
    $image_url = $this->_images[$image_name]['image_url'];
    if ( is_array( $values ) ) {
      $vars = $this->_images[$image_name]['image_vars'];
      if ( ! $vars ) {
        preg_match_all( '#\{([^}]+)\}#', $image_url, $matches );
        $vars = $matches[1];
      }
      if ( is_numeric( key($values) ) ) {
        /**
         * The $values array contains name/value pairs.
         */
        foreach( $values as $value ) {
          $image_url = str_replace( "{{$vars[0]}}", $value, $image_url );
        }
      } else {
        /**
         * The $values array just contains values in same order as the vars specified in the image.
         */
        foreach( $vars as $name ) {
          $image_url = str_replace( "{{$name}}", $values[$name], $image_url );
        }
      }
    }
    if ( ! preg_match( '#^https?//#', $image_url ) ) {
      $image_url = plugins_url( "/images/{$image_url}", $this->plugin_file );
    }
    return $image_url;
 	}
//  /**
//   * @return bool
//   */
//  function is_authenticated() {
// 		return false;
// 	}

  /**
   * Echo the current or specified form.
   *
   * @param bool|Sidecar_Form $form
   *
   * @return Sidecar_Form
   */
  function the_form( $form = false ) {
    if ( ! $form )
      $form = $this->current_form;
    return $form->the_form();
 	}

  /**
   * @param   array   $form
   * @return  Sidecar_Form
   */
  function promote_form( $form ) {
    /**
     * @var array $form
     */
    $form_name = $form['form_name'];
    $form['plugin'] = $this;

    if ( ! isset( $form['admin_page'] ) )
      $form['admin_page'] = end( $this->_admin_pages );

    /**
     * @var array|Sidecar_Form $form
     */
    $form = $this->_forms[$form_name] = new Sidecar_Form( $form_name, $form );

    $this->current_form = $form;
    $this->initialize_form( $form );
    $form->initialize();
    return $form;
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
   * @return Sidecar_Field
   */
  function get_form_field( $args ) {
    /**
     * @var Sidecar_Form $form
     */
    $form = $this->get_form( $args['form']->form_name );
    $field = $form->get_form_field( $args['field']->field_name, array( 'section_name' => $args['section']->section_name ) );
    return $field;
  }
  /**
   * @param array $args
   */
  function get_form_field_html( $args ) {
    /**
     * @var Sidecar_Field $field
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
   * @param string $shortcode_name
   * @return bool
   */
  function has_shortcode( $shortcode_name ) {
    return isset( $this->_shortcodes[$shortcode_name] );
  }
  /**
   * @param   string|Sidecar_Form $form
   * @return  array|Sidecar_Form
   */
  function get_form( $form ) {
    /*
     * Could be a string or already Sidecar_Form.
     */
    $form = is_string( $form ) && isset( $this->_forms[$form] ) ? $this->_forms[$form] : $form;
    if ( is_array( $form ) )
      $form = $this->promote_form( $form );
    return $form;
  }
  /**
   * @param string  $form_name
   * @param array   $args
   */
  function add_form( $form_name, $args = array() ) {
    $args['form_name'] = $form_name;
    $this->_forms[$form_name] = $args;
  }
  /**
   * @param       $form_name
   * @param array $args
   */

  /**
   * @param string  $form_name
   * @param array   $args
   * @return Sidecar_Field
   */
  function add_field( $form_name, $args = array() ) {
    /**
     * @var Sidecar_Form
     */
    $form = isset( $args['form'] ) ? $args['form'] : end( $this->_forms );
    return $form->add_field( $form_name, $args );
  }

  /**
   * @param array $newvalue
   * @param array $oldvalue
   * @return array
   */
  function pre_update_option( $newvalue, $oldvalue ) {
    /**
     * This only going to be saving one form's worth of data yet the settings can have many forms, like:
     *
     *    $settings = array(
     *      '_form1' => array( ... ),
     *      '_form2' => array( ... ),
     *      '_form3' => array( ... ),
     *    );
     *
     * So the next 3 lines grab all the old values of the other forms and uses the new values for this form.
     */
    $settings_key = "_{$newvalue['state']['form']}";
    $oldvalue[$settings_key] = $newvalue[$settings_key];
    $newvalue = $oldvalue;
    if ( method_exists( $this, 'encrypt_settings' ) ) {
      /**
       * @todo auto-encrypt credentials
       */
      $newvalue[$settings_key] = call_user_func( array( $this, 'encrypt_settings' ), $newvalue[$settings_key], $this->current_form, $newvalue );
    }
    unset( $newvalue['state'] ); // We don't need to save this.
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
      $msg = __( 'Your site needs to be running WordPress %s or later in order to use %s.', 'sidecar' );
      trigger_error( sprintf( $msg, $this->min_wp, $this->plugin_title ), E_USER_ERROR );
    } if ( version_compare( PHP_VERSION, $this->min_php, '<' ) ) {
      deactivate_plugins( basename( $this->plugin_file ) );
      $msg = __( 'Your site needs to be running PHP %s or later in order to use %s.', 'sidecar' );
      trigger_error( sprintf( $msg, $this->min_php, $this->plugin_title ), E_USER_ERROR );
    } else {
      if ( ! wp_next_scheduled( $this->cron_key ) ) {
        wp_schedule_event( time(), $this->cron_recurrance, $this->cron_key );
      }
      $settings = get_option( $this->option_name );
      if ( ! is_array( $settings ) )
        $settings = array();
      /*
       * If we have existing settings and we are either upgrading or reactivating we
       * previously had a _credentials element then reauthenticate and record that change.
       */
      if ( isset( $settings['_credentials'] ) && $this->api_class && file_exists( $this->api_loader ) ) {
        require_once( $this->api_loader );
        if ( class_exists( $this->api_class ) ) {
          /**
           * @var RESTian_Client
           */
          $api = new ${$this->api_class}();
          $settings['_credentials']['authenticated'] = $this->api->authenticate( $settings['_credentials'] );
          $this->api = $api;
        }
      }
      $settings['installed_version'] = $this->plugin_version;
      update_option( $this->option_name, $settings );
    }
  }

  /**
   * @param $property_name
   * @return bool|string
   */
  function __get( $property_name ) {
    $value = false;
    if ( preg_match( '#^(.*?_(icon|image|photo))_url$#', $property_name, $match ) && $this->has_image( $match[1] ) ) {
      $value = call_user_func( array( $this, "get_image_url" ), $match[1] );
    } else if ( preg_match( '#^(.*?)_url$#', $property_name, $match ) && $this->has_url( $match[1] ) ) {
      /**
       * Allows magic property syntax for any registered URL
       * @example: $this->foobar_url calls $this-get_url( 'foobar' )
       * Enables embedding in a HEREDOC or other doublequoted string
       * without requiring an intermediate variable.
       */
      $value = call_user_func( array( $this, "get_url" ), $match[1] );
    } else {
      Sidecar::show_error( 'No property named %s on %s class.', $property_name, get_class( $this ) );
    }
    return $value;
  }
}





