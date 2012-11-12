<?php
/**
 *
 */
class Surrogate_Admin_Form {
  /**
   * @var Surrogate_Plugin
   */
  var $plugin;
  /**
   * @var Surrogate_Admin_Page
   */
  var $admin_page;
  /**
   * @var string
   */
  var $form_name;
  /**
   * @var string
   */
  var $option_name;
  /**
   * @var array
   */
  protected $_default_settings = array();
  /**
   * @var array
   */
  protected $_settings;
  /**
   * @var array
   */
  var $_sections = array();
  /**
   * @var array
   */
  var $_buttons = array();

  /**
   * @param string $form_name
   * @param array $args
   */
  function __construct( $form_name, $args = array() ) {
    $this->form_name = $form_name;
    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value ) {
      if ( property_exists( $this, $property ) ) {
        $this->$property = $value;
      } else if ( property_exists( $this, $property = "form_{$property}" ) ) {
        $this->$property = $value;
      }
    }
    $this->option_name = "{$this->plugin->plugin_name}_{$this->form_name}";

  }

  /**
   * @return array
   */
  function get_field_defaults() {
    $field_defaults = array();
    foreach( $this->get_sections() as $form_section ) {
      foreach( $form_section->fields as $field ) {
        $field_defaults[$field->field_name] = isset( $field->field_default ) ? $field->field_default : '';
      }
    }
    return $field_defaults;
  }
  /**
   * @return array
   */
  function get_sections() {
    return $this->_sections;
  }
  /**
   * @param string $section_name
   * @return bool
   */
  function get_section( $section_name ) {
    return $this->_sections[$section_name];
  }
  /**
   * @param string $section_name
   * @return bool
   */
  function has_section( $section_name ) {
    return isset( $this->_sections[$section_name] );
  }
  /**
   * @return bool
   */
  function has_sections() {
    return 0 < count( $this->_sections ) ;
  }

  /**
   * @param string $section_name
   * @return array
   */
  function get_field_names( $section_name ) {
    return array_keys( $this->_sections[$section_name]->fields );
  }
  /**
   * @param bool|string $section_name
   *
   * @return bool
   */
  function has_fields( $section_name = false ) {
    if ( $section_name && $this->has_section( $section_name ) ) {
      $has_fields = 0 < count( $this->_sections[$section_name]->fields );
    } else {
      $has_fields = false;
      foreach( $this->_sections as $section )
        if ( count( $section->fields ) ) {
          $has_fields = true;
          break;
        }
    }
    return $has_fields;
  }
  /**
   * @param bool|string $section_name
   *
   * @return array
   */
  function get_fields( $section_name = false ) {
    if ( $section_name && $this->has_section( $section_name ) ) {
      $fields = $this->_sections[$section_name]->fields;
    } else {
      $fields = array();
      foreach( $this->_sections as $section )
        if ( is_array( $section->fields ) ) {
          $fields = array_merge( $section->fields, $fields );
        }
    }
    return $fields;
  }
  /**
   * @return array
   */
  function the_form() {
    echo $this->get_form_html();
    return $this;
  }
  /**
   * @return string
   */
  function get_form_html() {
    ob_start();
    /**
     * Get the HTML for the hidden fields from the Settings API
     */
    settings_fields( $settings_group = $this->admin_page->get_settings_group_name() );

    /**
     * Hide hidden fields from Settings API by removing them from global $wp_settings_fields
     * Adding to internal $hidden_fields
     */
    global $wp_settings_fields;
    $hidden_fields = array();
    $settings = $this->get_settings( $this->form_name );
    foreach( $wp_settings_fields[$this->option_name] as $section_name => $section ) {
      foreach( $section as $field_name => $field ) {
        if ( 'hidden' == $field['args']['field']->field_type ) {
          $hidden_fields[] = $field['args']['field'];
          unset( $wp_settings_fields[$this->option_name][$section_name][$field_name] );
        }
      }
    }
    /**
     * Extract the hidden fields so they won't display
     * @var Surrogate_Admin_Field $hidden_field
     */
    $hidden_fields_html = array();
    foreach( $hidden_fields as $hidden_field ) {
      $hidden_fields_html[] = $hidden_field->get_field_html();
    }
    $hidden_fields_html = implode( "\n", $hidden_fields_html );

    /**
     * Output each of the sections.
     */
    do_settings_sections( $this->option_name );

    $form_fields_html = ob_get_clean();

    $options_url = admin_url( 'options.php' );

    if ( ! $this->admin_page->has_tabs() ) {
      $hidden_section_input_html = '';
    } else {
      $tab_slug = $this->admin_page->get_current_tab()->tab_slug;

      $hidden_section_input_html = <<<HTML
<input type="hidden" name="{$settings_group}[plugin]" value="{$this->plugin->plugin_name}" />
<input type="hidden" name="{$settings_group}[page]" value="{$this->admin_page->page_name}" />
<input type="hidden" name="{$settings_group}[tab]" value="{$tab_slug}" />
<input type="hidden" name="{$settings_group}[form]" value="{$this->form_name}" />
HTML;
    }
    $buttons_html = array();
    foreach( $this->_buttons as $button ) {
      $button_html = get_submit_button(
        $button->button_text,
        $button->button_type,
        $button->input_name,
        $button->button_wrap,
        $button->other_attributes
        );
      $buttons_html[] = preg_replace( '#^<p(.*)</p>#', '<span$1</span>', $button_html );
    }
    $buttons_html = implode( "\n", $buttons_html );
    $form = <<<HTML
<form action="{$options_url}" method="POST">
  {$hidden_section_input_html}
  <div class="form-fields">
    {$hidden_fields_html}
    {$form_fields_html}
  </div>
  <div class="form-buttons">
    {$buttons_html}
  </div>
</form>
HTML;
    return $form;
  }

  /**
   * @param string  $button_name
   * @param string  $button_text
   * @param array   $args

   *  string $type The type of button. One of: primary, secondary, delete
   *  string $name The HTML name of the submit button. Defaults to "submit". If no id attribute
   *               is given in $other_attributes below, $name will be used as the button's id.
   *  bool $wrap True if the output button should be wrapped in a paragraph tag,
   * 			   false otherwise. Defaults to true
   *  array|string $other_attributes Other attributes that should be output with the button,
   *                     mapping attributes to their values, such as array( 'tabindex' => '1' ).
   *                     These attributes will be output as attribute="value", such as tabindex="1".
   *                     Defaults to no other attributes. Other attributes can also be provided as a
   *                     string such as 'tabindex="1"', though the array format is typically cleaner.   */
  function add_button( $button_name, $button_text, $args = array() ) {
    $args['button_name'] =  $button_name;
    $args['button_text'] =  $button_text;

    $this->_buttons[$button_name] = (object)$args;
  }

  /**
   * @param Surrogate_Plugin $plugin
   */
  function initialize( $plugin ) {
    if ( ! $this->has_fields()  )
      Surrogate::show_error( 'Form %s for Plugin %s has no fields registered.', $this->form_name, $this->plugin->plugin_name );
    $settings = $this->get_settings( $this->form_name );
    register_setting( $this->admin_page->get_settings_group_name(), $this->option_name, array( $this->plugin, 'filter_postback' ) );
    $this->initialize_sections( $plugin );
    $this->initialize_buttons( $plugin );
  }

  /**
   * @param Surrogate_Plugin $plugin
   */
  function initialize_sections( $plugin ) {
    foreach( $this->get_sections() as $section_name => $section ) {
      if ( ! $section->section_handler )
        $section->section_handler = array( $plugin, 'the_form_section' );
      $settings =  $this->get_settings( $this->form_name );
      add_settings_section( $section_name, $section->section_title, $section->section_handler, $this->option_name, array(
        'section' => $section,
        'form' => $this,
        'plugin' => $plugin,
        'settings' => $settings,
        ));
      foreach( $section->fields as $field_name => $field ) {
        if ( ! $field->field_handler )
          $field->field_handler = array( $plugin, 'the_form_field' );
        add_settings_field( $field_name, $field->field_label, $field->field_handler, $this->option_name, $section_name, array(
          'field' => $field,
          'section' => $section,
          'form' => $this,
          'plugin' => $plugin,
          'settings' => $settings,
        ));
      }
    }
  }
  /**
   * @param Surrogate_Plugin $plugin
   */
  function initialize_buttons( $plugin ) {
    foreach( $this->_buttons as $button_name => $button ) {

      if ( ! isset( $button->button_type ) )
        $button->button_type = 'primary';

      if ( ! isset( $button->input_name ) )
        if ( 'primary' == $button->button_type ) {
          $button->input_name = 'submit';
        } else {
          $button->input_name = $this->admin_page->get_settings_group_name() . "[{$button_name}]";
        }

      if ( ! isset( $button->button_wrap ) )
        $button->button_wrap = true;

      if ( ! isset( $button->other_attributes ) )
        $button->other_attributes = false;

    }
  }

  /**
   * @param string  $field_name
   * @param array   $args
   *
   * @return mixed
   */
  function get_form_field( $field_name, $args = array() ) {

    if ( ! isset( $args['section_name'] ) ) {
      $section = end( $this->_sections );
      $args['section_name'] = $section->section_name;
    }

    return $this->_sections[$args['section_name']]->fields[$field_name];
  }

  /**
   * @param string  $section_name
   * @param array   $args
   * @return object
   */
  function add_section( $section_name, $args = array() ) {
    $args = (object)$args;

    $args->section_name = $section_name;
    $args->admin_form = $this;

    if ( ! isset( $args->fields ) )
      $args->fields = array();

    if ( ! isset( $args->section_title ) )
      $args->section_title = false;

    if ( ! isset( $args->section_handler ) )
      $args->section_handler = false;

    return $this->_sections[$section_name] = $args;
  }
  /**
   * @param string        $field_name
   * @param array         $args
   * @return object
   */
  function add_field( $field_name, $args = array() ) {
    if ( 0 == count( $this->_sections ) ) {
      /**
       * We don't have any sections, set them, to 'default' and register
       */
      $section_name = 'default';
      $this->add_section( $section_name );
    } else if ( ! isset( $args['section_name'] ) ) {
      /**
       * Get the name of the last section added to the sections array.
       */
      end( $this->_sections );
      $section_name = key( $this->_sections );
    } else {
      /**
       * If was passed, grab it.
       */
      $section_name = $args['section_name'];
    }
    /**
     * Luke, I am your father!
     * (or for those who don't get the reference, assign reference to $args['admin_form'] so it will know it's form.)
     */
    $args['admin_form'] = $this;
    $args['plugin'] = $this->plugin;
    return $this->_sections[$section_name]->fields[$field_name] = new Surrogate_Admin_Field( $field_name, $args );
  }
  /**
   * Get an array of new settings (empty string; '').
   *
   * Override in subclass to add more specific setting defaults.
   * @return array
   *
   * @todo Cache new settings in a setting so that loading from front end does...
   * @todo ...require setting the admin form and traversing through these fields.
   */
  function get_new_settings() {
    if ( 0 == count( $this->_default_settings ) ) {
      $this->_default_settings = $this->get_field_defaults();
    }
 		return $this->_default_settings;
 	}

  /**
   * @return array
   */
  function get_empty_settings() {
    $new_settings = $this->get_new_settings();
 		return array_fill_keys( array_keys( $new_settings ), '' );
 	}

  /**
   * @return array
   */
  function get_settings() {
    if ( ! isset( $this->_settings ) ) {
      $option = get_option( $this->option_name );
      if ( method_exists( $this->plugin, 'decrypt_settings' ) ) {
        /**
         * @todo auto-decrypt credentials
         */
        $option = call_user_func( array( $this->plugin, 'decrypt_settings' ), $option, $this );
      }
      $this->_settings = array_merge( $this->get_new_settings(), $option );
    }
    return $this->_settings;
  }

  /**
   * @param string $setting_name
   *
   * @return bool
   */
  function has_setting( $setting_name ) {
    if ( ! isset( $this->_settings ) )
      /*
       * This will initialize settings
       */
      $this->get_settings();
    return isset( $this->_settings[$setting_name] );
  }

  /**
   * @param string $setting_name
   *
   * @return mixed
   */
  function get_setting( $setting_name ) {
    $value = false;
    if ( $this->has_setting( $setting_name ) )
      $value = $this->_settings[$setting_name];
    return $value;
 	}

  /**
   * @param array $new_settings
   */
  function set_settings( $new_settings ) {
 	  $this->_settings = $new_settings;
  }
}
