<?php
/**
 *
 */
class Sidecar_Admin_Page {
  /**
   * @var Sidecar_Base
   */
  var $plugin;
  /**
   * @var null|array Array contains Sidecar_Form objects
   */
  protected $_forms = array();
  /**
   * @var array
   */
  protected $_tabs = array();
  /**
   * @var string
   */
  protected $_page_url;
  /**
   * @var bool
   */
  protected $_is_page_url;
  /**
   * @var Sidecar_Admin_Tab
   */
  protected $_authentication_tab;
  /**
   * @var string
   */
  protected $_settings_group_name;
  /**
   * @var bool
   */
  protected $_initialized = false;
  /**
   * @var string
   */
  var $parent_slug = 'options-general.php';
  /**
   * @var string
   */
  var $page_name;
  /**
   * @var string
   */
  var $page_slug;
  /**
   * @var string
   */
  var $page_title;
  /**
   * @var string
   */
  var $menu_title;
  /**
   * @var string
   */
  var $menu_page;
  /**
   * @var string
   */
  var $capability_required = 'manage_options';
  /**
   * @var string One of the built in icons (below), or a custom icon starting with 'http://' or 'https://'
   * @example:
   *    admin, appearance,
   *    comments,
   *    dashboard,
   *    edit, edit-comments, edit-pages,
   *    index,
   *    link, links, link-category, link-manager,
   *    media, ms-admin,
   *    options-general,
   *    page, plugins, post, profile,
   *    settings, site, sitescreen,
   *    themes, tools,
   *    upload, user-edit, users
   */
  var $icon = 'options-general';  // Default

  /**
   * @var string
   */
  protected $_auth_form = false;


  /**
   * @param $page_name
   * @param array $args
   */
  function __construct( $page_name, $args = array() ) {
    $this->page_name = $page_name;

    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value )
      if ( property_exists(  $this, $property ) )
        $this->$property = $value;

    /**
     * Check $this->plugin first so we don't couple these if we don't have to.
     */
    if ( $this->plugin instanceof Sidecar_Base ) {
      if ( ! $this->page_title )
        $this->page_title = $this->plugin->plugin_label;

      if ( ! $this->menu_title )
        $this->menu_title = $this->plugin->plugin_label;

      if ( ! $this->page_slug )
        $this->page_slug = "{$this->plugin->plugin_slug}-{$page_name}";
    }

    add_action( 'admin_menu', array( $this, 'admin_menu' ) );

  }

  /**
   * @throws Exception
   */
  function initialize() {
    $this->_call_plugin( 'initialize_admin_page', $this );
    $current_tab = $this->has_tabs() ? $this->get_current_tab() : false;
    if ( $current_tab && $current_tab->has_forms() ) {
      register_setting( $this->plugin->option_name, $this->plugin->option_name, array( $this, 'filter_postback' ) );
      $plugin = $this->plugin;
      /**
       * Sidecar_Form $form
       */
      foreach( $current_tab->forms as $form ) {
        if ( $plugin->has_form( $form ) ) {
          $form = $this->plugin->get_form( $form );
          $form->admin_page = $this;
          $form->initialize_sections( $this->plugin );
          $form->initialize_buttons( $this->plugin );
          $this->_forms[$form->form_name] = $form;
        }
      }
    }
    $this->_initialized = true;
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
    if ( method_exists( $this, 'initialize_postback' ) )
      $this->plugin->initialize_postback();

    /**
     * Get the array that contains names of 'plugin', 'page', 'tab', 'form' and 'settings'
     * as well as special 'clear' and 'reset' for clearing and resetting the form respectively.
     */
    $settings = $_POST[$_POST['option_page']];
    $page = $this->plugin->current_page = $this->plugin->get_admin_page( $settings['state']['page'] );
    $form = $this->plugin->current_form = $this->plugin->get_form( $settings['state']['form'] );
    $form_values = $input[$form->settings_key];
    /**
     * Check with the API to see if we are authenticated
     */
    $api = $this->plugin->api;
    if ( $api && ( $page->is_authentication_tab() || ! $page->has_tabs() ) ) {
      if ( ! $api->assumed_authenticated( $form_values ) ) {
        add_settings_error( $page->plugin->option_name, 'sidecar-no-credentials', __( 'You must enter both a username and a password', 'sidecar' ) );
      } else if ( $api->authenticate( $form_values ) ) {
        $form_values['authenticated'] = true;
        add_settings_error( $page->plugin->option_name, 'sidecar-updated', __( 'Authentication successful. Settings saved.', 'sidecar' ), 'updated' );
      } else {
        $form_values['authenticated'] = false;
        add_settings_error( $page->plugin->option_name, 'sidecar-login-failed', __( 'Authentication Failed. Please try again.', 'sidecar' ) );
      }
    }
    $this->plugin->api = $api;

    if ( isset( $settings['action']['clear'] ) ) {
      $form_values = $form->get_empty_settings();
      $message = __( 'Form values cleared.%s%sNOTE:%s Your browser may still be displaying values from its cache but this plugin has indeed cleared these values.%s', 'sidecar' );
      add_settings_error( $page->plugin->option_name, "sidecar-clear", sprintf( $message, "<br/><br/>&nbsp;&nbsp;&nbsp;", '<em>', '</em>', '<br/><br/>' ), 'updated' );
    } else if ( isset( $settings['action']['reset'] ) ) {
      $form_values = $this->plugin->current_form->get_new_settings();
      add_settings_error( $page->plugin->option_name, 'sidecar-reset', __( 'Defaults reset.', 'sidecar' ), 'updated' );
    } else if ( method_exists( $this->plugin, 'validate_settings' ) ) {
      $form_values = array_map( 'rtrim', (array)$form_values );
      add_filter( $action_key = "pre_update_option_{$this->plugin->option_name}", array( $this->plugin, 'pre_update_option' ), 10, 2 );
      /**
       * @todo How to signal a failed validation?
       */
      $form_values = call_user_func( array( $this->plugin, 'validate_settings' ), $form_values, $this->plugin->current_form );
      /**
       * @var Sidecar_Field $field
       */
      foreach( $form->get_fields() as $field_name => $field ) {
        $validation_options = false;
   			/**
   			 * Default to FILTER_SANITIZE_STRING if ['validator'] not set.
   			 */
   			if ( $field->field_options ) {
          $validated_value = isset( $field->field_options[$form_values[$field_name]] ) ? $form_values[$field_name] : false;
        } else if ( isset( $field->field_validator['filter'] ) ) {
           $validated_value = filter_var( $form_values[$field_name], $field->field_validator['filter'] );
           if ( isset( $field->field_validator['options'] ) ) {
            $validation_options = $field->field_validator['options'];
           }
        } else {
          $validator = $field->field_validator ? $field->field_validator : FILTER_SANITIZE_STRING;
          $validated_value = filter_var( $form_values[$field_name], $validator );
        }
        if ( method_exists( $this->plugin, $method = "sanitize_setting_{$field_name}" ) ) {
          $validated_value = call_user_func( array( $this->plugin, $method ), $validated_value, $field, $form );
        }
        if ( $validation_options || $validated_value != $form_values[$field_name] ) {
          if ( ! $validation_options ) {
            add_settings_error( $page->plugin->option_name, 'sidecar-value', sprintf(
              __( 'Please enter a valid value for "%s."', 'sidecar' ), $field->field_label
            ));
          } else {
            if ( isset( $validation_options['min'] ) && $validation_options['min'] > intval( $form_values[$field_name] ) ) {
              add_settings_error( $page->plugin->option_name, 'sidecar-min', sprintf(
                __( 'Please enter a value greater than or equal to %d for "%s."', 'sidecar' ),
                  $validation_options['min'],
                  $field->field_label
              ));
            }
            if ( isset( $validation_options['max'] ) && $validation_options['max'] < intval( $form_values[$field_name] ) ) {
              add_settings_error( $page->plugin->option_name, 'sidecar-max', sprintf(
                __( 'Please enter a value less than or equal to %d for "%s."', 'sidecar' ),
                  $validation_options['max'],
                  $field->field_label
              ));
              $continue = true;
            }
          }
        }
      }
    }
    $input[$form->settings_key] = $form_values;
    return $input;
  }


  /**
   * Test the credentials for the pluginsauth form
   * @return bool
   */
  function is_authenticated() {
    $credentials = $this->get_auth_credentials();
    return isset( $credentials['authenticated'] ) && $credentials['authenticated'];
  }
  /**
   * @return array
   */
  function get_auth_credentials() {
    return $this->get_auth_form()->get_settings();
  }
  /**
   * @return Sidecar_Form
   */
  function get_auth_form() {
    return $this->plugin->get_form( $this->_auth_form );
  }

  /**
   * @param string|Sidecar_Form $form
   */
  function set_auth_form( $form ) {
    if ( is_string( $form ) ) {
      $this->_auth_form = $form;
    } else if ( isset( $form->form_name ) ) {
      $this->_auth_form = $form->form_name;
    } else if ( WP_DEBUG ) {
      $message = __( '%s->set_auth_form() must be passed a string, an array with a \'form_name\' element or an object with a \'form_name\' property.', 'sidecar' );
      trigger_error( sprintf( $message, $this->plugin_class ) );
    }
  }

  /**
   * @param string $tab_slug
   * @param string $tab_text
   * @param array $args
   */
  function add_tab( $tab_slug, $tab_text, $args = array() ) {
    $this->_tabs[$tab_slug] = $tab = new Sidecar_Admin_Tab( $tab_slug, $tab_text, $args );
 	}

  /**
   * @return Sidecar_Admin_Tab
   */
  function get_default_tab() {
    return reset( $this->_tabs );
  }
  /**
   * @return bool
   */
  function is_authentication_tab() {
    return is_object( $this->get_authentication_tab() );
  }
  /**
   * @return Sidecar_Admin_Tab
   */
  function get_authentication_tab() {
    if ( ! $this->_authentication_tab ) {
      /**
       * @var Sidecar_Admin_Tab $tab
       */
      foreach( $this->_tabs as $tab ) {
        if ( in_array( $this->_auth_form, $tab->forms ) ) {
          $this->_authentication_tab = $tab;
        }
      }
    }
    return $this->_authentication_tab;
  }
  /**
   * Calls a method in the plugin
   *
   * Captures whatever additional parameters and passes them on.
   *
   * @param string $method
   *
   * @return bool|mixed
   */
  protected function _call_plugin( $method ) {
    $result = false;
    $args = func_get_args();
    array_shift( $args );
    if ( method_exists( $this->plugin, $method ) )
      $result = call_user_func_array( array( $this->plugin, $method ), $args );
    return $result;
  }
  /**
   *
   */
  function admin_menu() {

    if ( $this->is_postback_update() )
      return;

    if ( $this->is_page_url() ) {
      /**
       * Call the plugin's $this->initialize_admin_page() method, if it exists.
       */
      $this->initialize();

      $this->verify_current_tab();

    }

    /**
     * Add in menu option, if one doesn't already exist
     */
    $this->menu_page = add_submenu_page(
 		  $this->parent_slug,
      $this->page_title,
      $this->menu_title,
      $this->capability_required,
      $this->page_slug,
      array( $this, 'the_page' )
    );

    add_action( "admin_print_styles-{$this->menu_page}", array( $this, 'admin_print_styles' ));
 	}

  /**
   *
   */
  function admin_print_styles() {
    $plugin = $this->plugin;
 	  $localfile = 'css/admin-style.css';
 	  $filepath = "{$plugin->plugin_path}/{$localfile}";
 	  if ( file_exists( $filepath ) ) {
 		  wp_enqueue_style( "{$plugin->plugin_name}_admin_styles", plugins_url( $localfile, $plugin->plugin_file ) );
     }
 	}

  /**
   * @todo Call this from HEAD instead of from here.
   */
  function the_css() {
    $css = $this->_call_plugin( 'get_admin_page_css', $this );
    if ( ! empty( $css ) ) {
      $css_html =<<<HTML
<style type="text/css">
{$css}
</style>
HTML;
      echo $css_html;
    }
  }
  /**
 	 * Displays admin page
 	 */
 	function the_page() {
     /**
      * @todo Call this from HEAD instead of from here.
      */
    $this->the_css();
    $tab = $this->get_current_tab();
    $tab_class= $tab ? " tab-{$tab->tab_slug}" : false;
 	  echo "\n<div class=\"wrap{$tab_class}\">";
 	  $this->the_icon();
    $this->the_title_and_tabs( $tab );
    $this->the_page_content();
 	  echo "\n" . '</div>';
 	}

  /**
   * @param string $tab
   * @param string $content_type
   * @param array $args
   */
  function the_tab_specific_content( $tab, $content_type, $args = array() ) {
    $args = wp_parse_args( $args, array( 'wrap' => true ) );
    $content = false;
    if ( ! $tab || ! isset( $tab->{$content_type} ) )
      return;
    if ( is_string( $tab->{$content_type} ) ) {
      $content = $tab->{$content_type};
    } else {
      $handler = $tab->{$content_type};
      if ( ! is_callable( $tab->{$content_type} ) ) {
        $method = "the_{$this->page_name}_{$tab->tab_slug}_tab_{$content_type}";
        $handler = method_exists( $this->plugin, $method ) ? array( $this->plugin, $method ) : false;
      }
      if ( $handler ) {
        ob_start();
        call_user_func( $handler, $this, $tab );
        $content = ob_get_clean();
      }
    }
    if ( $content ) {
      if ( $args['wrap'] ) {
        $content_type_slug = str_replace( '_', '-', $content_type );
        $content =<<< HTML
<div id="tab-{$content_type_slug}">
{$content}
</div>
HTML;
      }
      echo $content;
    }
  }
  /**
   *
   */
  function the_page_content() {
    echo '<div id="admin-content">';
    /**
     * @var bool|Sidecar_Admin_Tab
     */
    $current_tab = $this->has_tabs() ? $this->get_current_tab() : false;

    $this->the_tab_specific_content( $current_tab, 'before_page_title', array( 'wrap' => false ) );

    if ( $current_tab && $current_tab->page_title ) {
      echo "<h1 class=\"admin-page-title\">";
      echo "\n" . $current_tab->page_title;
      echo "\n" . '</h1>';
    }

    $this->the_tab_specific_content( $current_tab, 'before_content' );

    if ( $current_tab && $current_tab->tab_handler ) {
      $handler =  $current_tab->tab_handler;
      if ( ! is_callable( $handler ) ) {
        $method = $handler;
        if ( is_array( $method ) ){
          $method = ( is_string( $handler[0] ) ? "{$handler[0]}::" : get_class( $handler[0] ) .'->' ) . $handler[1];
        }
        $message = __( '%s provided as %s for admin tab %s of admin page %s is not a valid callable.', 'sidecar' );
        Sidecar::show_error( $message,
          "<strong><code>{$method}()</code></strong>",
          "<strong><code>tab_handler</code></strong>",
          "<strong><em>\"{$current_tab->tab_slug}\"</em></strong>",
          "<strong><em>\"{$this->page_name}\"</em></strong>"
          );
      }
    } else {
      $handler = array( $this->plugin, "the_{$this->page_name}_admin_page" );
      if ( $current_tab ) {
        $tab_handler = array( $this->plugin, "the_{$this->page_name}_{$current_tab->tab_slug}_tab" );
        /**
         * Fallback to page handler if tab handler is not callable
         */
        $handler = is_callable( $tab_handler ) ? $tab_handler : $handler;
      }
      if ( ! is_callable( $handler ) && $this->plugin->has_form( $current_tab->tab_slug ) ) {
        /*
         * If we have no handler but do have a form with same name as the tab slug, show it.
         */
        $handler = array( $this->plugin->get_form( $current_tab->tab_slug ), 'the_form' );
      }
      if ( ! is_callable( $handler ) ) {
        if ( isset( $tab_handler ) )
          /**
           * If it was a tab then report the more specific function as the one we are missing
           */
          $handler = $tab_handler;
        $message = __( 'No method named %s defined yet for %s.', 'sidecar' );
        Sidecar::show_error( $message,
          "<strong><code>{$handler[1]}()</code></strong>",
          "<strong><code>{$this->plugin->plugin_class}</code></strong>"
          );
      }
    }
    if ( is_callable( $handler ) ) {
      $this->_call_plugin( 'initialize_tab', $current_tab, $this );
      call_user_func( $handler, $this );
    }

    $this->the_tab_specific_content( $current_tab, 'after_content' );
    echo '</div>';
  }
  /**
   * Displays the icon for the plugin page
   *
   * @todo Research to see if we need to support something other than icon32
   *
   */
   function the_icon() {
    if ( $this->icon ) {
      echo "\n" . '<div class="icon32"';
      if ( preg_match( '#^https?://#', $this->icon, $m ) ) {
        echo "\n" . '><img height="34" width="36" src=' . $this->icon . '>';
      } else {
        echo "\n" . ' id="icon-' . $this->icon . '"><br/>';
      }
      echo '</div>';
    }
   }
  /**
   *
   */
  function get_tabs() {
 	  return $this->_tabs;
 	}
  /**
   *
   */
  function has_tabs() {
 	  return 0 < count( $this->_tabs );
 	}

  /**
   * Display the row of tabs at the top of a page with the <h2> tab wrapper element
   */
  function the_title_and_tabs() {
    if ( $this->page_title || $this->has_tabs() ) {
      echo "\n" . '<h2 class="nav-tab-wrapper">';
      if ( $this->page_title )
        echo "\n" . $this->page_title;
      if ( $this->has_tabs() )
        echo "\n" . $this->get_tabs_html();
      echo "\n" . '</h2>';
    }
 	}

  /**
   * Returns the tabs as a block of HTML for display at the top of the admin page.
   *
   * @return string
   */
  function get_tabs_html() {
 	  return implode( "\n", $this->get_tab_links_html() );
 	}

  /**
   * Returns an array of HTML for each tabs for display at the top of the admin page.
   *
   */
  function get_tab_links_html() {
    $links_html = array();
    $current_tab = $this->get_current_tab();
    if ( $current_tab ) {
      foreach ( $this->get_tabs() as $tab_slug => $tab ) {
        $class = ( $tab_slug == $current_tab->tab_slug ) ? ' nav-tab-active' : '';
        $url = $this->get_tab_url( $tab_slug );
        $links_html[] =<<<HTML
  <a class="nav-tab{$class}" href="{$url}">{$tab->tab_text}</a>
HTML;
      }
    }
    return $links_html;
  }


  /**
   * @return string|void
   */
  function get_page_url() {
    return $this->get_tab_url();
 	}
  /**
   * @param bool|string|Sidecar_Admin_Tab $tab
   * @return string|void
   */
  function get_tab_url( $tab = false ) {
    if ( ! $this->_initialized ) {
      $message = __( '%s->get_page_url() cannot be called prior to %s->initialize_admin_page() being called.', 'sidecar' );
      Sidecar::show_error( $message, __CLASS__, $this->plugin->plugin_class );
    }

    if ( $tab instanceof Sidecar_Admin_Tab )
      $tab = $tab->tab_slug;
    if ( $this->has_tabs() ) {
      if ( isset( $this->_page_url[$tab] ) ) {
        $url = $this->_page_url[$tab];
      } else {
        if ( false === $tab )
          $tab = $this->get_default_tab()->tab_slug;
        $url = $this->_page_url[$tab] = $this->get_base_page_url() . "&tab={$tab}";
      }
      ;
    } else {
      if ( isset( $this->_page_url ) ) {
        $url = $this->_page_url;
      } else {
        $url = $this->_page_url = $this->get_base_page_url();
      }
    }
    return $url;
 	}

  /**
   * @return string|void
   */
  function get_base_page_url() {
    return admin_url( "{$this->parent_slug}?page={$this->page_slug}" );
  }

  /**
 	 * Check if the passed $tab variable matches the URL's tab parameter.
 	 *
 	 * @param string $tab
 	 * @return bool
 	 */
 	function is_current_tab( $tab ) {
 		/**
 		 * IF the the current page has a valid tab,
 		 * AND the URL's 'tab' parameter matches the function's $tab parameter
 		 * THEN *YES*, it's the tab specified
 		 */
 		return $this->is_current_tab_valid() && $tab == $_GET['tab'];
 	}

  /**
 	 * Check if the passed $tab variable matches the URL's tab parameter.
 	 *
 	 * @return Sidecar_Admin_Tab
 	 */
 	function get_current_tab() {
 	  static $current_tab;
 	  if ( ! isset( $current_tab ) ) {
      $tab_slug = false;
      if ( isset( $_GET['tab'] ) ) {
         $tab_slug = $_GET['tab'];
      } else if ( isset( $_POST['option_page'] ) && isset( $_POST[$_POST['option_page']]['state']['tab'] )) {
        /*
         * This is used during HTTP postback from an admin form.
         * The <input type="hidden" name="option_page"> added by settings_fields() and
         * referencing <input type="hidden" name="{option_page}[tab]"> generated by $form->get_form_html()
         */
        $tab_slug = $_POST[$_POST['option_page']]['state']['tab'];
      }
      $current_tab = $tab_slug && isset( $this->_tabs[$tab_slug] ) ? $this->_tabs[$tab_slug] : false;
    }
   return $current_tab;
  }

  /**
 	 * Check if the passed $tab variable matches the URL's tab parameter.
 	 *
 	 * @param string|Sidecar_Admin_Tab $tab
 	 * @return bool
 	 */
 	function has_tab( $tab ) {
 	  if ( isset( $tab->tab_slug ) ) {
 	    $tab = $tab->tab_slug;
     }
 		return isset( $this->_tabs[$tab] );
 	}

  /**
 	 *
 	 * @return int
 	 */
 	function tab_count() {
 		return count( $this->_tabs );
 	}

  /**
   * Validates to ensure that we have a URL tab parameter that is one of the valid tabs.
   *
   * @return bool
   */
  function is_current_tab_valid() {
    return $this->has_tab( $this->get_current_tab() ) && $this->is_page_url();
  }

  /**
 	 * Check to see if we are on the admin URL for this plugin.
 	 *
 	 * @return bool
 	 */
 	function is_page_url() {
 		if ( ! isset( $this->_is_page_url ) ) {
 			$this_url = site_url( $_SERVER['REQUEST_URI'] );
 			$base_url = $this->get_base_page_url();
      $this->_is_page_url = $base_url == substr( $this_url, 0, strlen( $base_url ) );
 		}
 		return $this->_is_page_url;
 	}

  /**
   * @return bool
   */
  function is_postback_update() {
    $is_postback_update = false;
    if ( isset( $_POST['action'] ) && 'update' == $_POST['action'] && '/wp-admin/options.php' == $_SERVER['REQUEST_URI'] ) {
      $this->initialize();
      $is_postback_update = isset( $_POST[$this->plugin->option_name] );
    }
    return $is_postback_update;
  }

  /**
 	 * Check to make sure we are on the right tab.
 	 *
 	 * Redirect if we are not on the right tab based on authentication status or invalid tab,
 	 * OR return if not even on this plugin's Admin URL
 	 * Register an error message for later display if not authenticated.
 	 *
 	 * @return bool Returns true if we are on a verified tab, false or URL redirect otherwise.
 	 */
 	function verify_current_tab() {

     /*
      * If we have no tabs trying to verify is a moot point. Punt.
      */
    if ( ! $this->has_tabs() )
      return true;

 		if ( ! $this->is_current_tab_valid() ) {
 			/**
 			 * If we don't have a valid tab, redirect to first tab if authenticated, or authentication tab if not.
 			 *
 			 * We redirect to avoid having multiple URLs mean the same thing. That's not optimal for bookmarking, caching, etc.
 			 */
 			if ( $this->is_authenticated() ) {
 				/**
 				 * If authenticated we redirect with a "301 - This URL has changed" status code so the browser can know
 				 * to go to 'usage' whenever is sees this URL and avoid the round trip next time.
 				 */
 				wp_safe_redirect( $this->get_tab_url( $this->get_default_tab()->tab_slug ), 301 );
 			} else if ( $auth_tab = $this->get_authentication_tab() ) {
 				/**
 				 * If not authenticated we redirect with a "302 - This URL has moved temporarily" status code
 				 * because normally we'd want to go to usage, so don't cause browser to thing this URL w/o a
 				 * valid tab should always go to 'account.'
 				 */
 				wp_safe_redirect( $this->get_tab_url( $auth_tab->tab_slug ), 302 );
 			}
 			/**
 			 * Stop processing PHP so we can send the HTTP Location header back the browser to trigger a redirect.
 			 */
 			exit;
 		} else if ( ! $this->is_authenticated() ) {
 			/**
 			 * If we are not authenticated...
 			 */
      $auth_tab = $this->get_authentication_tab();
 			if ( $auth_tab && $auth_tab->tab_slug != $_GET['tab'] ) {
 				/**
 				 * ...and we are NOT on the account tab then redirect to the 'account' tab.
 				 *
 				 * We redirect with a "302 - This URL has moved temporarily" because it's still a good URL and
 				 * we want the browser to be happy to return here later.
 				 */
 				wp_safe_redirect( $this->get_tab_url( $auth_tab ), 302 );
 				exit;
 			} else {
 				/**
 				 * ...and we ARE on the account tab then prepare a "Need to authenticate" message for later display.
 				 */
 				add_settings_error(
 					$this->plugin->plugin_slug, // @todo Switch to $this->form_name,
 					'need-info',
 					__( 'You must have an account to use this plugin.  Please enter your credentials.', 'sidecar' )
 				);
 			}
 		}
 		return true;
 	}
  /**
   * @param $property_name
   * @return bool|string
   */
  function __get( $property_name ) {
    $value = false;
    if ( preg_match( '#^(.+?)_tab_url$#', $property_name, $match ) && $this->has_tab( $match[1] ) ) {
      /**
       * Allows magic property syntax for any registered URL
       * @example: $this->foobar_url calls $this-get_url( 'foobar' )
       * Enables embedding in a HEREDOC or other doublequoted string
       * without requiring an intermediate variable.
       */
      $value = call_user_func( array( $this, "get_tab_url" ), $match[1] );
    } else {
      Sidecar::show_error( 'No property named %s on %s class.', $property_name, get_class( $this ) );
    }
    return $value;
  }

}
