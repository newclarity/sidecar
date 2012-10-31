<?php
/**
 *
 */
class Surrogate_Admin_Form {

  /**
   * @var Surrogate_Plugin_Base
   */
  var $plugin;
  /**
   * @var Surrogate_Admin_Page
   */
  var $admin_page;
  var $form_name;
  var $settings_name;
  var $_sections = array();
  var $_buttons = array();

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
    settings_fields( $settings_group = $this->plugin->get_settings_group_name() );

    /**
     * Hide hidden fields from Settings API by removing them from global $wp_settings_fields
     * Adding to internal $hidden_fields
     */
    global $wp_settings_fields;
    $hidden_fields = array();
    foreach( $wp_settings_fields[$this->settings_name] as $section_name => $section ) {
      foreach( $section as $field_name => $field ) {
        if ( 'hidden' == $field['args']['field']->field_type ) {
          $hidden_fields[] = $field['args']['field'];
          unset( $wp_settings_fields[$this->settings_name][$section_name][$field_name] );
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
    do_settings_sections( $this->settings_name );

    $form_fields_html = ob_get_clean();

    $options_url = admin_url( 'options.php' );

    if ( ! $this->admin_page->has_tabs() ) {
      $hidden_section_input_html = '';
    } else {
      $tab_slug = $this->admin_page->get_current_tab()->tab_slug;
      $hidden_section_input_html = "<input type=\"hidden\" name=\"{$settings_group}[section]\" value=\"{$tab_slug}\" />";
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
   * @param $plugin
   */
  function initialize( $plugin ) {
    $this->initialize_sections( $plugin );
    $this->initialize_buttons( $plugin );
  }

  /**
   * @param Surrogate_Plugin_Base $plugin
   */
  function initialize_sections( $plugin ) {
    if ( ! $this->settings_name ) {
      /**
       * @todo Create register settings here is one does not exist for this form.
       */
      $this->settings_name = $plugin->get_settings_name( $this->form_name );
    }
    foreach( $this->get_sections() as $section_name => $section ) {
      if ( ! $section->section_handler )
        $section->section_handler = array( $plugin, 'the_form_section' );
      //$page_slug = $section->admin_form->admin_page->page_slug;
      add_settings_section( $section_name, $section->section_title, $section->section_handler, $this->settings_name, array(
        'section' => $section,
        'form' => $this,
        'plugin' => $plugin,
        'settings_name' => $this->settings_name,
        ));
      foreach( $section->fields as $field_name => $field ) {
        if ( ! $field->field_handler )
          $field->field_handler = array( $plugin, 'the_form_field' );
        add_settings_field( $field_name, $field->field_label, $field->field_handler, $this->settings_name, $section_name, array(
          'field' => $field,
          'section' => $section,
          'form' => $this,
          'plugin' => $plugin,
          'settings_name' => $this->settings_name,
        ));
      }
    }
  }
  /**
   * @param Surrogate_Plugin_Base $plugin
   */
  function initialize_buttons( $plugin ) {
    foreach( $this->_buttons as $button_name => $button ) {

      if ( ! isset( $button->button_type ) )
        $button->button_type = 'primary';

      if ( ! isset( $button->input_name ) )
        if ( 'primary' == $button->button_type ) {
          $button->input_name = 'submit';
        } else {
          $button->input_name = $plugin->get_settings_group_name() . "[{$button_name}]";
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
}
