<?php
/**
 *
 */
class Surrogate_Admin_Page {

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
   * @var Surrogate_Admin_Tab
   */
  protected $_authentication_tab;
  /**
   * @var bool
   */
  protected $_initialized = false;
  /**
   * @var Surrogate_Plugin_Base
   */
  var $plugin;
  /**
   * @var Surrogate_Settings
   */
  var $settings;
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
    if ( $this->plugin instanceof Surrogate_Plugin_Base ) {
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
   // throw new Exception( 'Class ' . get_class($this) . ' [subclass of ' . __CLASS__ . '] must define an initialize() method.' );
  }

  /**
   * @return bool
   */
  function is_authenticated() {
    return true;
  }

  /**
   * @param string $tab_slug
   * @param string $tab_text
   * @param array $args
   */
  function add_tab( $tab_slug, $tab_text, $args = array() ) {
    $this->_tabs[$tab_slug] = $tab = new Surrogate_Admin_Tab( $tab_slug, $tab_text, $args );
 	}

  /**
   * @return Surrogate_Admin_Tab
   */
  function get_default_tab() {
    return reset( $this->_tabs );
  }
  /**
   * @return Surrogate_Admin_Tab
   */
  function get_authentication_tab() {
    if ( ! $this->_authentication_tab ) {
      foreach( $this->_tabs as $tab )
        if ( $tab->auth_tab )
          $this->_authentication_tab = $tab;
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

    if ( $this->is_page_url() ) {
      /**
       * Call the plugin's $this->initialize_admin_page() method, if it exists.
       */
      $this->_call_plugin( 'initialize_admin_page', $this );

      /**
       * Call the subclass' $this->initialize() method, if it exists.
       */
      if ( method_exists( $this, 'initialize' ) )
        $this->initialize();

      $this->_initialized = true;

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
 	  if ( file_exists( $filepath ) )
 		  wp_enqueue_style( "{$plugin->plugin_name}_admin_style", plugins_url( $localfile, $plugin->plugin_file ) );
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
    $this->the_content();
 	  echo "\n" . '</div>';
 	}

  /**
   * @param $tab
   * @param $content_type
   */
  function the_tab_specific_content( $tab, $content_type ) {
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
      $content_type_slug = str_replace( '_', '-', $content_type );
      $html =<<< HTML
<div class="tab-{$content_type_slug}">
{$content}
</div>
HTML;
      echo $html;
    }
  }
  /**
   *
   */
  function the_content() {
    echo '<div id="admin-content">';
    /**
     * @var bool|Surrogate_Admin_Tab
     */
    $current_tab = $this->has_tabs() ? $this->get_current_tab() : false;

    if ( $current_tab && $current_tab->tab_header ) {
      echo "<h1 class=\"plugin-tab-page-title\">";
      echo "\n" . $current_tab->tab_header;
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
        $message = __( '%s provided as %s for admin tab %s of admin page %s is not a valid callable.', 'surrogate' );
        Surrogate::show_error( $message,
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
      if ( ! is_callable( $handler ) && $this->plugin->has_admin_form( $current_tab->tab_slug ) ) {
        /*
         * If we have no handler but do have a form with same name as the tab slug, show it.
         */
        $handler = array( $this->plugin->get_admin_form( $current_tab->tab_slug ), 'the_form' );
      }
      if ( ! is_callable( $handler ) ) {
        if ( isset( $tab_handler ) )
          /**
           * If it was a tab then report the more specific function as the one we are missing
           */
          $handler = $tab_handler;
        $message = __( 'No method named %s defined yet for %s.', 'surrogate' );
        Surrogate::show_error( $message,
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
        $url = $this->get_page_url( $tab_slug );
        $links_html[] =<<<HTML
  <a class="nav-tab{$class}" href="{$url}">{$tab->tab_text}</a>
HTML;
      }
    }
    return $links_html;
  }


  /**
   * @param bool|string|Surrogate_Admin_Tab $tab
   * @return string|void
   */
  function get_page_url( $tab = false ) {
    if ( ! $this->_initialized ) {
      $message = __( '%s->get_page_url() cannot be called prior to %s->initialize_admin_page() being called.', 'surrogate' );
      Surrogate::show_error( $message, __CLASS__, $this->plugin->plugin_class );
    }

    if ( $tab instanceof Surrogate_Admin_Tab )
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
 	 * @return Surrogate_Admin_Tab
 	 */
 	function get_current_tab() {
 		return isset( $_GET['tab'] ) && isset( $this->_tabs[$_GET['tab']] ) ? $this->_tabs[$_GET['tab']] : false;
 	}

  /**
 	 * Check if the passed $tab variable matches the URL's tab parameter.
 	 *
 	 * @param string $tab
 	 * @return bool
 	 */
 	function has_tab( $tab ) {
 	  if ( $tab instanceof Surrogate_Admin_Tab )
 	    $tab = $tab->tab_slug;
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
 		$url = $_SERVER['REQUEST_URI'];
 		return isset( $this->settings->settings_name ) && isset( $_POST[$this->settings->settings_name] ) &&
 			isset( $_POST['action'] ) && 'update' == $_POST['action'] &&
 			$url == substr( admin_url( 'options.php' ), - strlen( $url ) );
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

 		if ( $this->is_postback_update() )
 			return true;

 		if ( ! $this->is_page_url() )
 			return false;

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
 				wp_safe_redirect( $this->get_page_url( $this->get_default_tab()->tab_slug ), 301 );
 			} else if ( $auth_tab = $this->get_authentication_tab() ) {
 				/**
 				 * If not authenticated we redirect with a "302 - This URL has moved temporarily" status code
 				 * because normally we'd want to go to usage, so don't cause browser to thing this URL w/o a
 				 * valid tab should always go to 'account.'
 				 */
 				wp_safe_redirect( $this->get_page_url( $auth_tab->tab_slug ), 302 );
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
 				wp_safe_redirect( $this->get_page_url( $auth_tab ), 302 );
 				exit;
 			} else {
 				/**
 				 * ...and we ARE on the account tab then prepare a "Need to authenticate" message for later display.
 				 */
 				add_settings_error(
 					$this->plugin->plugin_slug, // @todo Switch to $this->settings->settings_name,
 					'need-info',
 					__( 'You must have an account to use this plugin.  Please enter your credentials.', 'surrogate' )
 				);
 			}
 		}
 		return true;
 	}
}