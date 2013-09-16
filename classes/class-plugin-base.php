<?php
/**
 *
 */
class Sidecar_Plugin_Base extends Sidecar_Singleton_Base {
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
   * ID of plugin used by WordPress in get_option('active_plugins')
   *
   * @var string
   */
  var $plugin_id;

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
//  /**
//   * @var string Cron recurrance
//   */
//  var $cron_recurrance = 'hourly';
//  /**
//   * @var string Key used for cron for this plugin
//   */
//  var $cron_key;

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
   * @var array Array of Meta Links for the WordPress plugin page.
   */
  protected $_meta_links = array();

  /**
   * @var bool|array|RESTian_Client
   */
  protected $_api = false;

  /**
   * @var Sidecar_Admin_Page
   */
  protected $_current_admin_page;

  /**
   * @var Sidecar_Form
   */
  protected $_current_form;

  /**
   * @var bool
   */
  protected $_initialized = false;

  /**
   * @var Sidecar_Settings
   */
  protected $_settings;

  /**
   * @var
   */
  protected $_default_settings_values;

  /**
   * @var string
   */
  var $option_name;

  /**
   * @var string
   */
  var $needs_ajax = false;

  /**
   * @var bool
   */
  var $needs_settings = true;

  /**
   * @var bool
   */
  protected $_admin_initialized = false;




  /**
   * @param $class_name
   * @param $filepath
   */
  function set_api_loader( $class_name, $filepath ) {
    $this->_api = array(
      'class_name' => $class_name,
      'filepath' => $filepath,
    );
  }

  /**
   * @return bool
   */
  function has_api() {
    return false !== $this->get_api();
  }

  /**
   * Returns a RESTian_Client object if set_api_loader() has previously been called with correct info, false otherwise.
   * @return bool|RESTian_Client
   */
  function get_api() {
    if ( ! $this->_api instanceof RESTian_Client ) {
      /**
       * @todo fix this to work on Windows
       */
      $filepath= '/' == $this->_api['filepath'][0] ? $this->_api['filepath'] : "{$this->plugin_path}/{$this->_api['filepath']}";

      if ( is_file( $filepath) ) {
        require_once( $filepath );
        if ( class_exists( $this->_api['class_name'] ) ) {
          $class_name = $this->_api['class_name'];
          // @var RESTian_Client $this->_api
          $this->_api = new $class_name( $this );
        }
        /**
         * @todo Verify we still need this
         */
        $this->_api->initialize_client();
      }
    }
    return $this->_api;
  }

  /**
   * @param RESTian_Client $api
   */
  function set_api( $api ) {
    if ( empty( $this->_forms ) ) {
      /**
       * What about plugins with an API but no need for forms?  Is that possible since forms=settings in Sidecar?
       */
      $error_message = __( '$plugin->set_api($api) cannot be called before forms have been added. Please call $plugin->add_form() in $plugin->initialize_plugin() at least once prior to calling set_api().', 'sidecar' );
      trigger_error( $error_message );
      exit;
    }
    $api->caller = $this;
    $api->initialize_client();
    $this->_api = $api;
    if ( $this->_admin_initialized ) {
      /**
       * If Admin has been initialized then set grant.
       * If not, wait to set until after initialization
       * otherwise we get into a bad spiral of not-defined-yet.
       */
      $this->_api->set_grant( $this->get_grant() );
    }
  }

  function is_saving_widget() {
    global $pagenow;
    return isset( $_POST['action'] ) && isset( $_POST['id_base'] )
      && 'admin-ajax.php' == $pagenow && 'save-widget' == $_POST['action'];
  }
  /**
   * @param array $args
   */
  function on_load( $args = array() ) {

    /**
     * If we are saving a widget then of course we need ajax,
     * and I'm pretty sure we'll need to add a lot of checks here
     * as new AJAX use-cases are discovered.
     */
    if ( ! $this->needs_ajax && $this->is_saving_widget() ) {
      $this->needs_ajax = true;
    }
    /*
     * If running an AJAX callback and either $this->needs_ajax or $args['needs_ajax'] is false then bypass the plugin.
     *
     * To enable AJAX support in a plugin either:
     *
     *  1. Create a __construct in plugin class, set $this->needs_ajax=true then call parent::_construct(), or
     *
     *  2. Pass array( 'needs_ajax' => true ) to plugin's required constructor at end of plugin file,
     *     i.e. new MyPlugin( array( 'needs_ajax' => true ) );
     *
     */
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX && ( ! $this->needs_ajax || ( isset( $args['needs_ajax'] ) && ! $args['needs_ajax'] ) ) )
      return;

    $this->plugin_class = get_class( $this );

    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value )
      if ( property_exists(  $this, $property ) )
        $this->$property = $value;

    add_action( 'init', array( $this, '_init' ) );
    add_action( 'wp_loaded', array( $this, '_wp_loaded' ) );
    add_action( 'wp_print_styles', array( $this, '_wp_print_styles' ) );
    add_action( 'save_post', array( $this, '_save_post' ) );

    $this->plugin_class_base = preg_replace( '#^(.*?)_Plugin$#', '$1', $this->plugin_class );

    if ( ! $this->plugin_name )
      $this->plugin_name = strtolower( $this->plugin_class_base );

    if ( ! $this->option_name )
      $this->option_name = "{$this->plugin_name}_settings";

//    if ( ! $this->cron_key )
//      $this->cron_key = "{$this->plugin_name}_cron";

    if ( $this->is_plugin_page_action() ) {
      global $plugin;
      if ( ! isset( $plugin ) ) {
        /*
         * This plugin is being activated
         */
        $this->plugin_id = filter_input( INPUT_GET, 'plugin', FILTER_SANITIZE_STRING );
        $this->plugin_file = WP_PLUGIN_DIR . '/' . $this->plugin_id;
      } else if ( file_exists( $plugin ) ) {
        /**
         * This is evidently the case during activation using Imperative
         */
        $this->plugin_file = $plugin;
        $this->plugin_id = basename( dirname( $plugin ) ) . '/' . basename( $plugin );
      } else {
        /*
         * Another plugin is being activated
         */
        $this->plugin_file = WP_PLUGIN_DIR . '/' . $plugin;
        $this->plugin_id = $plugin;
      }
      add_action( 'plugins_loaded', array( $this, '_plugins_loaded' ) );
      add_action( "activate_{$this->plugin_id}", array( $this, '_activate_plugin' ), 0 );
      register_activation_hook( $this->plugin_id, array( $this, '_activate' ) );
    } else if ( ! WP_Library_Manager::$loading_plugin_loaders && $this->is_verified_plugin_deletion() ) {
      if ( preg_match( '#^uninstall_(.*?)$#', current_filter(), $match ) ) {
        $this->plugin_file = WP_PLUGIN_DIR . "/{$match[1]}";
      } else {
        /*
         * @todo My god this is a hack! I really need help from WordPress core here.
         * @todo Blame: http://core.trac.wordpress.org/ticket/22802#comment:41
         */
        $backtrace = debug_backtrace();
        foreach( $backtrace as $index => $call ) {
          if ( preg_match( '#/wp-admin/includes/plugin.php$#', $call['file'] ) ) {
            $this->plugin_file = $backtrace[$index-1]['file'];
            break;
          }
        }
      }
      $this->plugin_id = basename( dirname( $this->plugin_file ) ) . '/' . basename( $this->plugin_file );
    } else if ( false !== WP_Library_Manager::$uninstalling_plugin && preg_match( '#' . preg_quote( WP_Library_Manager::$uninstalling_plugin ) . '$#', $GLOBALS['plugin'] ) ) {
      /**
       * We are uninstalling a plugin, and the plugin we are uninstalling matches the global $plugin value
       * which means we ar loading the plugin we want to uninstall (vs. loading a different plugin on `same page load.)
       */
      global $plugin;
      $this->plugin_file = $plugin;
      $this->plugin_id = WP_Library_Manager::$uninstalling_plugin;
    } else {
      /**
       * Grab the plugin file name from one the global values set when the plugin is included.
       * @see: http://wordpress.stackexchange.com/questions/15202/plugins-in-symlinked-directories
       * @see: http://wordpress.stackexchange.com/a/15204/89
       */
      global $mu_plugin, $network_plugin, $plugin;
      if ( isset( $mu_plugin ) ) {
        $this->plugin_file = $mu_plugin;
      } else if ( isset( $network_plugin ) ) {
        $this->plugin_file = $network_plugin;
      } else if ( isset( $plugin ) ) {
        $this->plugin_file = $plugin;
      } else {
        trigger_error( sprintf( __( 'Plugin %s only works when loaded by WordPress.' ), $this->plugin_name ) );
        exit;
      }
      $this->plugin_id = basename( dirname( $this->plugin_file ) ) . '/' . basename( $this->plugin_file );
      require_once(ABSPATH . 'wp-admin/includes/plugin.php');
      if ( ! is_plugin_active( $this->plugin_id ) ) {
        trigger_error( sprintf( __( 'Plugin %s is not an active plugin or is not installed in a subdirectory of %s.' ),
          $this->plugin_name,
          WP_PLUGIN_DIR
        ));
        exit;
      }
      register_deactivation_hook( $this->plugin_id, array( $this, 'deactivate' ) );
    }

    register_uninstall_hook( $this->plugin_id, array( $this->plugin_class, 'uninstall' ) );

    /**
     * Ask subclass to initialize plugin which includes admin pages
     */
    $this->initialize_plugin();

  }

  /**
   * Delete the flag indicating that a post needs external files (CSS styles and JS scripts) for each
   * shortcode we have in case the newly saved post now has changed the use of the shortcodes.
   */
  function _save_post( $post_id ) {
    $this->initialize_shortcodes();
    $shortcodes = $this->get_shortcodes();
    if ( is_array( $shortcodes ) ) {
      /**
       * @var Sidecar_Shortcode $shortcode
       */
      foreach( $shortcodes as $shortcode )
        $shortcode->delete_has_shortcode( $post_id );
      /**
       * Now load the post asynchronously via HTTP to pre-set the meta value for $this->HAS_SHORTCODE_KEY.
       */
      wp_remote_request( get_permalink( $post_id ), array( 'blocking' => false ) );
    }
  }

  /**
   * Used to check if we are on a plugin page that is asking about deletion.
   *
   * @return bool
   */
  function is_confirm_plugin_deletion() {
    return $this->is_plugin_deletion()
      && ! isset( $_POST['verify-delete'] );
  }

  /**
   * Used to check if we are on a plugin page that is deleting (a) plugin(s).
   *
   * @return bool
   */
  function is_verified_plugin_deletion() {
    return $this->is_plugin_deletion()
      && isset( $_POST['verify-delete'] ) &&  '1' == $_POST['verify-delete'];
  }

  /**
   * Used to check if we are a plugin page askin about deletion or processing deletion request.
   *
   * @return bool
   */
  function is_plugin_deletion() {
    return $this->is_plugin_page()
      && isset( $_GET['action'] ) &&  'delete-selected' == $_GET['action']
      && isset( $_REQUEST['checked'] ) && count( $_REQUEST['checked'] );
  }

  /**
   *
   */
  static function uninstall() {

    /**
     * @var Sidecar_Plugin_Base $plugin
     */
    $plugin = self::this();

    /**
     * Initialize it so we can ensure all properties are set in case $plugin->uninstall_plugin() needs them.
     */
    $plugin->initialize();

    /**
     * Delete settings
     */
    $plugin->delete_settings();

    /*
     * Call subclass' uninstall if applicable.
     */
    $plugin->uninstall_plugin();

  //    /**
  //     * Delete cron tasks
  //     */
  //    $next_run = wp_next_scheduled( $plugin->cron_key );
  //    wp_unschedule_event( $next_run, $plugin->cron_key );

  }

  /**
   * Used to check if we are in an activation callback on the Plugins page.
   *
   * @return bool
   */
  function is_plugin_page() {
    global $pagenow;
    return 'plugins.php' == $pagenow;
  }

  /**
   * Used to check if we are in an activation callback on the Plugins page.
   *
   * @return bool
   */
  function is_plugin_page_action() {
    return $this->is_plugin_page()
      && isset( $_GET['action'] )
      && isset( $_GET['plugin'] );
  }

//  /**
//   * Used to check if we are activating a plugin.
//   *
//   * @return bool
//   */
//  function is_plugin_activation() {
//    return $this->is_plugin_page_action()
//      && 'activate' == $_GET['action'];
//  }

  /**
   * This is used for the "activate_{$this->plugin_id}" hook
   * when $this->is_plugin_page_action().
   */
  function _activate_plugin() {
    $this->initialize();
  }

  /**
   * This is used for the "activate_{$this->plugin_id}" hook
   * when $this->is_plugin_page_action().
   */
  function _plugins_loaded() {
    $this->initialize();
  }

  /**
   * @return array|bool
   */
  function get_forms() {
    foreach( $this->_forms as $form_name => $form )
      if ( is_array( $form ) )
        $this->_forms[$form_name] = $this->promote_form( $form );
    return $this->_forms;
  }

  /**
   * @return bool
   */
  function has_forms() {
    return is_array( $this->_forms ) && count( $this->_forms );
  }

  /**
   *
   */
  function _wp_print_styles() {
 	  $localfile = 'css/style.css';
    $args = apply_filters( "sidecar_print_{$this->plugin_name}_styles", array(
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

    if ( ! $this->plugin_slug )
      $this->plugin_slug = str_replace( '_', '-', $this->plugin_name );

    if ( ! $this->css_base )
      $this->css_base = $this->plugin_slug;

    $this->_settings = new Sidecar_Settings( $this->option_name, $this );

    $this->_settings->initialize_settings( $this->get_default_settings_values() );

  }

  /**
   * @return array
   */
  function get_default_settings_values() {
    if ( ! isset( $this->_default_settings_values ) ) {
      $default_settings_values = array();
      foreach( $this->get_forms() as $form ) {
        /**
         * @var Sidecar_Form $form
         */
        $default_settings_values[$form->form_name] = $form->get_default_settings_values();
      }
      $this->_default_settings_values = $default_settings_values;
    }
    return $this->_default_settings_values;
  }

  /**
   * @param array $settings_values
   */
  function update_settings_values( $settings_values ) {
    $this->get_settings()->update_settings_values( $settings_values );
  }

  /**
   * @param object $settings_option
   * @param bool $set_dirty
   */
  function update_settings_option( $settings_option, $set_dirty = true ) {
    $this->get_settings()->update_settings_option( $settings_option, $set_dirty );
  }

  /**
   * @param string|Sidecar_Form $form
   * @param string $setting_name
   * @return array
   */
  function get_form_settings_value( $form, $setting_name ) {
    if ( ! $form instanceof Sidecar_Form )
      $form = $this->get_form( $form );
    return $this->get_setting( $form )->get_setting( $setting_name );

  }

  /**
   * @param string|Sidecar_Form $form
   * @param string $setting_name
   * @param string $value
   * @return array
   */
  function update_form_settings_value( $form, $setting_name, $value ) {
    if ( ! $form instanceof Sidecar_Form )
      $form = $this->get_form( $form );
    return $form->update_settings_value( $setting_name, $value );
  }

  /**
   * @return bool
   */
  function has_required_settings() {
    $has_required_settings = true;
    if ( ! $this->_initialized )
      $this->initialize();
    if ( $this->has_forms() ) {
      $settings = $this->get_settings();
      /** @var Sidecar_Form $form */
      foreach( $this->get_forms() as $form_name => $form ) {
        $form_settings = $settings->get_setting( $form->form_name );
        if ( ! $form_settings->has_required_settings( $form->get_required_field_names() ) ) {
          $has_required_settings = false;
          break;
        }
      }
    }
    if ( method_exists( $this, 'filter_has_required_settings' ) ) {
      $has_required_settings = $this->filter_has_required_settings( $has_required_settings, $settings );
    }
    return $has_required_settings;
  }


  /**
   * @param Sidecar_Settings $settings
   */
  function set_settings( $settings ) {
    $this->_settings = $settings;
  }

  /**
   * @return Sidecar_Settings
   */
  function get_settings() {
    if ( ! $this->_initialized )
      $this->initialize();
    return $this->_settings;
  }
  /**
   * @param string|Sidecar_Form $form
   * @return mixed
   */
  function get_form_settings( $form ) {
    if ( ! $form instanceof Sidecar_Form )
      $form = $this->get_form( $form );
    return $this->get_settings()->get_setting( $form->form_name );
  }

  /**
   * Delete the persisted settings on disk.
   *
   * @return bool
   */
  function delete_settings() {
    $this->get_settings()->delete_settings();
  }

  /**
   *
   */
  function _wp_loaded() {
    if ( is_admin() ) {
      add_action( 'admin_notices',       array( $this, '_admin_notices' ) );
      if ( $this->is_plugin_page() ) {
        add_filter( 'plugin_action_links', array( $this, '_plugin_action_links' ), 10, 2 );
        add_filter( 'plugin_row_meta',     array( $this, '_plugin_meta_links' ), 10, 2 );
      }
    } else {
      $shortcodes = method_exists( $this->plugin_class, 'initialize_shortcodes' );
      $template = method_exists( $this, 'initialize_template' );
      if ( $shortcodes || $template ) {
        // @todo Should we always initialize or only when we need it?
        $this->initialize();
        if ( $shortcodes )
          $this->initialize_shortcodes();
        if ( $template )
          $this->initialize_template();
        add_filter( 'the_content', array( $this, '_the_content' ), -1000 );
      }
    }
  }
  /**
   */
  function _admin_notices() {
    if ( $this->needs_settings && ! $this->has_required_settings() && $this->is_plugin_page() && ! $this->is_confirm_plugin_deletion() ) {
      $icon_html = $this->has_url( 'logo_icon' ) ? "<span class=\"sidecar-logo-icon\"></span><img src=\"{$this->logo_icon_url}\" /></span>" : '';
      $message = sprintf( __( 'The <em>%s</em> plugin is now activated. Please configure it\'s <a href="%s"><strong>settings</strong></a>.', 'sidecar' ),
      $this->plugin_title,
      $this->get_settings_url()
    );
    $html = <<<HTML
<div id="message" class="error settings-error">
    <p>{$icon_html}{$message}</p>
</div>
HTML;
    echo $html;
    }
  }

  /**
   * @param $links
   * @param $file
   *
   * @return array
   */
  function _plugin_action_links( $links, $file ){
    if ( $file == $this->plugin_id  ) {
      $url = $this->get_settings_url();
      $link_text = __( 'Settings', 'sidecar' );
      $links[] = "<a href=\"{$url}\">{$link_text}</a>";
    }
    return $links;
  }

  /**
   * @return bool|string|void
   */
  function get_settings_url() {
    $settings_url = false;
    if ( $settings_page = $this->get_admin_page( 'settings' ) ) {
      $settings_page->initialize();
      $settings_url = $this->get_admin_page( 'settings' )->get_page_url( null );
    }
    if ( method_exists( $this, 'filter_settings_url' ) )
      $settings_url = $this->filter_settings_url( $settings_url );
    return $settings_url;
  }
  /**
   * @param array $links
   * @param string $file
   *
   * @return array
   */
  function _plugin_meta_links( $links, $file ){
    if ( $file == $this->plugin_id ) {
      foreach( $this->_meta_links as $link_text => $link ) {
        $title = isset( $link['title'] ) ? " title=\"{$link['title']}\"" : '';
        $links[] = "<a target=\"_blank\" href=\"{$link['url']}\"{$title}>{$link_text}</a>";
      }
    }
    return $links;
  }

  /**
   * @param $content
   */
  function _the_content( $content ) {
    $shortcodes = $this->get_shortcodes();
    if ( is_array( $shortcodes ) )
      /**
       * @var Sidecar_Shortcode $shortcode
       */
      foreach( $shortcodes as $shortcode_name => $shortcode ) {
        if ( method_exists( $this, 'initialize_shortcode' ) ) {
          $this->initialize_shortcode( $shortcode );
        }
        add_shortcode( $shortcode_name, array( $shortcode, 'do_shortcode' ) );
        /**
         * Now get each shortcode to monitor for it's own use.
         */
        $shortcode->add_the_content_filter();
      }
    return $content;
  }

  function _init() {
//    add_action( 'cron_schedules', array( $this, '_cron_schedules' ) );
//    add_action( 'cron', array( $this, '_cron' ) );
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
        $this->_initialize_admin();

//        if ( $this->plugin_version )
//          $this->plugin_title .= sprintf( ' v%s', $this->plugin_version );

      }
    }
  }
  /**
   * @param string $to 'api_vars' or 'fields'
   * @param Sidecar_Form $form
   * @param array $fields
   * @return array
   */
  function transform_form_fields_to( $to, $form, $fields ) {
    $to = rtrim( $to, 's' );
    $field_objects = $form->get_fields();
    foreach( $fields as $field_name => $value ) {
      if ( isset( $field_objects[$field_name] ) ) {
        if ( $field_name != ( $new_name = $field_objects[$field_name]->$to ) ) {
          if ( $new_name )  // If false then it's not an API var
            $fields[$new_name] = $value;
          unset( $fields[$field_name] );
        }
      }
    }
    return $fields;
  }

  /**
   * @param string $to 'api_vars' or 'settings'
   * @param Sidecar_Shortcode $shortcode
   * @param array $attributes
   * @return array
   */
  function transform_shortcode_attributes_to( $to, $shortcode, $attributes ) {
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
   *
   */
  function uninstall_plugin() {
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
    $this->_admin_pages[$page_name] = new Sidecar_Admin_Page( $page_name, $args );
  }
  /**
   * @param string $link_text
   * @param string $url
   * @param array $args
   */
  function add_meta_link( $link_text, $url, $args = array() ) {
    $args['link_text'] = $link_text;
    $args['url'] = isset( $this->_urls[$url] ) ? $this->_urls[$url]['url_template'] : $url;
    $this->_meta_links[$link_text] = $args;
  }

  /**
   * @param string $page_name
   *
   * @return Sidecar_Admin_Page
   */
  function get_admin_page( $page_name ) {
    $page_slug = preg_replace( "#^{$this->plugin_slug}-(.*)$#", '$1', $page_name );
    return isset( $this->_admin_pages[$page_slug] ) ? $this->_admin_pages[$page_slug] : false;
  }

  /**
   *
   */
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
//
//  /**
//   * @param $schedules
//   *
//   * @return mixed
//   */
//  function _cron_schedules( $schedules ) {
// 		$schedules['fifteenseconds'] = array( 'interval' => 15, 'display' => __( 'Once Every Fifteen Seconds' ) );
// 		return $schedules;
// 	}

//  /**
//   * @return bool
//   */
//  function _cron() {
//    return true;
//  }

//  function _deactivate() {
// 		/**
// 		 * Unschedule cron
// 		 */
// 		$next_run = wp_next_scheduled( $this->cron_key );
// 		wp_unschedule_event( $next_run, $this->cron_key );
// 	}

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
   * @param $url_name
   *
   * @return bool
   */
  function get_link_text( $url_name ) {
    return isset( $this->_urls[$url_name]['link_text'] ) ? $this->_urls[$url_name]['link_text'] : false;
 	}

  /**
   * @param $url_name
   *
   * @return bool
   */
  function get_link_class( $url_name ) {
    return isset( $this->_urls[$url_name]['link_class'] ) ? " class=\"{$this->_urls[$url_name]['link_class']}\"" : false;
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
      $form = $this->get_current_form();
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

    $this->set_current_form( $form );
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
  function get_form_field( $field_name, $form_name ) {
    /**
     * @var Sidecar_Form $form
     */
    $form = $this->get_form( $form_name );
    return $form ? $form->get_field( $field_name ) : false;
  }
  /**
   * @param array $args
   */
  function get_form_field_html( $field_name, $form_name ) {
    /**
     * @var Sidecar_Field $field
     */
    $field = $this->get_form_field( $field_name, $form_name );
    return $field->get_html();
  }

  /**
   * @param array $args
   */
  function the_form_field( $field_name, $form_name ) {
    echo $this->get_form_field_html( $field_name, $form_name );
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
    return is_object( $form ) ? $form : false;
  }
  /**
   * @param string  $form_name
   * @param array   $args
   */
  function add_form( $form_name, $args = array() ) {
    $args['form_name'] = $form_name;
    if ( ! isset( $args['requires_api'] ) && 'account' == $form_name )
      $args['requires_api'] = true;
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
   * @return bool
   */
  function needs_grant() {
    return $this->has_api();
  }
  /**
   * Determines if the currently stored settings contain a grant to access the API.
   *
   * @return bool
   */
  function has_grant() {
    $has_grant = false;
    if ( $this->needs_grant() ) {
      $has_grant = $this->get_api()->is_grant( $this->get_auth_form()->get_settings_values() );
    }
    return $has_grant;
  }

  /**
   * Get grant from the currently stored account settings
   *
   * @return array
   */
  function get_grant() {
    /**
     * @var RESTian_Auth_Provider_Base $auth_provider
     */
    $auth_provider = $this->get_api()->get_auth_provider();
    return $auth_provider->extract_grant( $this->get_auth_form()->get_settings_values() );
  }

  /**
   * Get credentials from the currently stored account settings
   *
   * @return array
   */
  function get_credentials() {
    /**
     * @var RESTian_Auth_Provider_Base $auth_provider
     */
    $auth_provider = $this->get_api()->get_auth_provider();
    return $auth_provider->extract_credentials( $this->get_auth_form()->get_settings_values() );
  }

  /**
   * @return Sidecar_Admin_Page
   */
  function get_current_admin_page() {
    if ( ! isset( $this->_current_admin_page ) ) {
      if ( ! isset( $_GET['page'] ) || ! is_admin() ) {
        $this->_current_admin_page = false;
      } else {
        $tab = isset( $_GET['tab'] ) ? $_GET['tab'] : $this->get_admin_page($_GET['page'])->get_default_tab()->tab_slug;
        /**
         * If we have a $_GET['page'] then is should be "{$plugin_slug}-{$page_slug}"
         * Scan through the array to find it.
         */
        foreach( array_keys( $this->_admin_pages ) as $admin_page_slug ) {
          if ( "{$this->plugin_slug}-{$admin_page_slug}" == $_GET['page'] ) {
            $this->_current_admin_page = $this->get_admin_page( $admin_page_slug );
            break;
          }
        }
      }
    }
    return $this->_current_admin_page;
  }

  /**
   * @param Sidecar_Admin_Page $current_admin_page
   */
  function set_current_admin_page( $current_admin_page ) {
    $this->_current_admin_page = $current_admin_page;
  }

  /**
   * @return Sidecar_Form
   */
  function get_current_form() {
    return $this->_current_form;
  }

  /**
   * @param Sidecar_Admin_Page $current_form
   */
  function set_current_form( $current_form ) {
    $this->_current_form = $current_form;
  }

  /**
   * Capture values from form but cause update_option() to be bypassed. We'll update in the shutdown hook.
   *
   * @param array $new_value
   * @param array $old_value
   * @return array
   */
  function _pre_update_option( $new_value, $old_value ) {
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
    if ( ! isset( $new_value['state'] ) ) {
      $return_value = $new_value;
    } else {
      $return_value = $old_value;
      $form_name = $new_value['state']['form'];
      $old_value[$form_name] = $new_value[$form_name];
      $old_value['state'] = $new_value['state'];
      /**
       * Set the 'decrytped' value to 'true' for the form that is being submitted.
       */
      $old_value['state']['decrypted'][$new_value['state']['form']] = true;

      /*
       * @todo Need to fix this update_settings_option() to save the correct info.
       * @todo Also need to provide an extensibility method.
       */
      $this->update_settings_option( (object)array(

      ));
    }

    return $return_value;
  }
  /**

   * @todo Decide if trigger_error() is the best for error messages
   */
  function _activate() {
    if ( ! $this->_initialized )
      $this->initialize();

    if ( method_exists( $this, 'activate' ) )
      $this->activate();

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
      // @todo Add simplified support for cron when we see a use-case for it.
      //if ( ! wp_next_scheduled( $this->cron_key ) ) {
      //  wp_schedule_event( time(), $this->cron_recurrance, $this->cron_key );
      //}
      /*
       * If we have existing settings and we are either upgrading or reactivating we
       * previously had a _credentials element then reauthenticate and record that change.
       */
      if ( $this->has_api() ) {
        $api = $this->get_api();
        /**
         * @var RESTian_Auth_Provider_Base $auth_provider
         */
        $auth_provider = $api->get_auth_provider();
        $auth_form = $this->get_auth_form();
        if ( ! $auth_form )
          wp_die( __( 'There is no auth form configured. Call $admin_page->set_auth_form( $form_name ) inside initialize_admin_page( $admin_page ).', 'sidecar' ) );

        $account_settings = $auth_form->get_settings();
        $credentials = $auth_provider->extract_credentials( $account_settings->get_settings_values() );
        $credentials = array_merge( $auth_provider->get_new_credentials(), $credentials );
        $credentials = $auth_provider->prepare_credentials( $credentials );

        /**
         * 'account' contains credentials and grant merged
         */
        if ( ! $auth_provider->is_credentials( $credentials ) ) {
          /**
           * Allow the auth provider to establish defaults in the grant if needed.
           * This is an unusual need, but Lexity.com needed it.
           */
          $grant = $auth_provider->prepare_grant( $auth_provider->get_new_grant(), $credentials );

        } else {
          /**
           * Attempt to authenticate with available credentials
           */
          $response = $api->authenticate( $credentials );
          /**
           * If authenticated get the updated grant otherwise get an empty grant
           */
          $grant = $response->authenticated ? $response->grant : $auth_provider->get_new_grant();

          /**
           * Allow the auth provider to establish defaults in the grant if needed.
           * This is an unusual need, but Lexity.com needed it.
           */
          $grant = $auth_provider->prepare_grant( $grant, $credentials );

        }
        /**
         * Merge credentials and grant back into $settings['_account']
         */
        $account_settings->update_settings_values( array_merge( $credentials, $grant ) );

      }

      $settings = $this->get_settings();
      $settings->installed_version = $this->plugin_version;
      $settings->update_settings();

    }
  }

  /**
   *
   */
  function _initialize_admin() {
    if ( ! $this->_admin_initialized ) {
      if ( ! method_exists( $this, 'initialize_admin' ) ) {
        trigger_error( __( 'Plugin must define a $plugin->initialize_admin() method..', 'sidecar' ) );
        exit;
      }
      $this->initialize_admin();

      $this->_admin_initialized = true;

      if ( $this->_api )
        $this->_api->maybe_set_grant( $this->get_grant() );

    }
  }
  /**
   * @return Sidecar_Form
   */
  function get_auth_form() {
    $this->_initialize_admin();
    $auth_form = false;
    /**
     * @var Sidecar_Admin_Page $page
     */
    foreach( $this->_admin_pages as $page ) {
      $this->initialize_admin_page( $page );
      if ( $test = $page->get_auth_form() ) {
        $auth_form = $test;
        break;
      }
    }
    return $auth_form;
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
      $value = $this->get_url( $match[1] );
    } else if ( preg_match( '#^(.*?)_link$#', $property_name, $match ) && $this->has_url( $match[1] ) ) {
      /**
       * Same kind of this as _url above.
       */
      $url = $this->get_url( $match[1] );
      $link_text = $this->get_link_text( $match[1] );
      $class = $this->get_link_class( $match[1] );
      $value = "<a target=\"_blank\"{$class} href=\"{$url}\">{$link_text}</A>";
    } else {
      Sidecar::show_error( 'No property named %s on %s class.', $property_name, get_class( $this ) );
    }
    return $value;
  }
}
