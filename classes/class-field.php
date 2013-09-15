<?php
/**
 *
 */
class Sidecar_Field {

  /**
   * @var Sidecar_Plugin_Base
   */
  var $plugin;

  /**
   * @var Sidecar_Form
   */
  var $form;

  /**
   * @var array
   */
  var $section;

  /**
   * @var string
   */
  var $field_name;

  /**
   * @var string
   */
  var $field_slug;

  /**
   * @var string
   */
  var $field_label;

  /**
   * @var string
   */
  var $field_type;

  /**
   * @var string
   */
  var $field_help;

  /**
   * @var int
   */
  var $field_size;

  /**
   * @var int|array
   */
  var $field_validator;

  /**
   * @var string
   */
  var $field_default;

  /**
   * @var array
   */
  var $field_options;

  /**
   * @var bool
   */
  var $field_required = false;

  /**
   * @var bool|callable
   */
  var $field_handler = false;

  /**
   * Indicates this field value if used for an API.
   * Defaults to $this->field_name, can be see to another name
   * (i.e. field_name = 'content_type', api_var = 'type'
   * but if set to false it will cause the API to ignore this field.
   * @var null|bool|string
   */
  var $api_var;

  /**
   * @var array
   */
  var $_extra = array();

  /**
   * @var array
   */
  var $field_allow_html = false;

  /**
   * @param string $field_name
   * @param array $args
   */
  function __construct( $field_name, $args = array() ) {
    $this->field_name = $field_name;
    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value ) {
      if ( property_exists( $this, $property ) ) {
        $this->$property = $value;
      } else if ( property_exists( $this, $property = "field_{$property}" ) ) {
        $this->$property = $value;
      } else {
        $this->_extra[$property] = $value;
      }
    }
    if ( ! $this->field_type )
      $this->field_type = 'password' == $this->field_name ? 'password' : 'text';

    if ( 'hidden' == $this->field_type )
      $this->field_label = false;
    else if ( ! $this->field_label )
      $this->field_label = ucwords( $this->field_name );

    if ( ! $this->field_size )
      $this->field_size = preg_match( '#(text|password)#', $this->field_type ) ? 40 : false;

    if ( ! $this->field_slug )
      $this->field_slug = str_replace( array( '_', ' ' ), '-', $this->field_name );

    if ( is_null( $this->api_var ) )
      $this->api_var = $this->field_name;

  }

  /**
   * @return string
   */
  function get_html() {
    /**
     * @todo: Get options with all expected elements initialized
     */
    $form = $this->form;
    $settings = $form->get_settings();
    $value = isset( $settings[$this->field_name] ) ? esc_attr( $settings[$this->field_name] ) : false;
    $input_name = "{$this->plugin->option_name}[{$form->settings_key}][{$this->field_name}]";
    /**
     * Sets HTML id like the following:
     * @example
     *  HTML name = my_plugin_settings[_form-name}[field-name]
     *  HTML id =>  my-plugin-settings--form-name-field-name
     */
    $input_id = str_replace( array( '[_', '_', '][', '[', ']' ), array( '--', '-', '-', '-', '' ), $input_name );
    $size_html = $this->field_size ? " size=\"{$this->field_size}\"" : '';
    $css_base = $this->plugin->css_base;
    $help_html = $this->field_help ? "\n<br />\n<span class=\"{$css_base}-field-help\">{$this->field_help}</span>" : false;

    if ( 'radio' == $this->field_type ) {
      $html = array( "<ul id=\"{$input_id}-radio-field-options\" class=\"radio-field-options\">" );
      $this_value = $settings[$this->field_name];
      foreach( $this->field_options as $value => $label ) {
        $checked = ( ! empty( $this_value ) && $value == $this_value ) ? 'checked="checked" ' : false;
        $value = esc_attr( $value );
        $html[] =<<<HTML
<li><input type="radio" id="{$input_id}" class="{$css_base}-field" name="{$input_name}" value="{$value}" {$checked}/>
<label for={$input_id}">{$label}</label></li>
HTML;
      }
      $html = implode( "\n", $html ) . "</ul>{$help_html}";
    } else if ( 'select' == $this->field_type ) {
      $html = array( "<select id=\"{$input_id}-select-field-options\" name=\"{$input_name}\" class=\"select-field-options\">" );
      $this_value = $settings[$this->field_name];
      foreach( $this->field_options as $value => $label ) {
        $selected = ( ! empty( $this_value ) && $value == $this_value ) ? ' selected="selected"' : false;
        $value = esc_attr( $value );
        $html[] =<<<HTML
<option value="{$value}"{$selected}>{$label}</option>
HTML;
      }
      $html = implode( "\n", $html ) . "</select>{$help_html}";
    } else if ( 'checkbox' == $this->field_type ) {
      $checked = ! empty( $settings[$this->field_name] ) ? 'checked="checked" ' : false;
      $html =<<<HTML
<input type="checkbox" id="{$input_id}" class="{$css_base}-field" name="{$input_name}" value="1" {$checked}/>
<label for="{$input_id}">{$this->field_label}</label>
HTML;
    } else if ( 'hidden' == $this->field_type ) {
      $html =<<<HTML
<input type="hidden" id="{$input_id}" name="{$input_name}" value="{$value}" />
HTML;
    } else if ( 'textarea' == $this->field_type ) {
//      $value = htmlentities( $value );
      if ( $rows = $this->get_extra( 'rows' ) )
        $rows = " rows=\"{$rows}\"";
      if ( $cols = $this->get_extra( 'cols' ) )
        $cols = " cols=\"{$cols}\"";
      $html =<<<HTML
<textarea id="{$input_id}" name="{$input_name}"{$rows}{$cols}>{$value}</textarea>{$help_html}
HTML;
    } else {
      $html =<<<HTML
<input type="{$this->field_type}" id="{$input_id}" name="{$input_name}" value="{$value}" class="{$css_base}-field"{$size_html}/>{$help_html}
HTML;
  }
    $field_wrapper_id = $this->get_wrapper_id();
    $html =<<<HTML
<div id="{$field_wrapper_id}" class="{$this->field_type}">{$html}</div>
HTML;
    return $html;
  }

  /**
   * @param string|$property_name
   *
   * @return null|int|string
   */
  function get_extra( $property_name ) {
    $value = null;
    if ( isset( $this->_extra[$prefixed_name = "field_{$property_name}"] ) ) {
      $value = $this->_extra[$prefixed_name];
    } else if ( isset( $this->_extra[$property_name] ) ) {
      $value = $this->_extra[$property_name];
    }
    return $value;
  }

  /**
   * @return string
   */
  function get_wrapper_id() {
    return "field-{$this->field_slug}-input";
  }
}
