<?php
/**
 *
 */
class Surrogate_Settings {
  /**
   * @var Surrogate_Plugin_Base
   */
  var $plugin;
  /**
   * @var Surrogate_Admin_Form
   */
  var $admin_form;
  /**
   * @var string
   */
  var $option_name;
  /**
   * @var string
   */
  var $settings_name;
  /**
   * @var array
   */
  protected $_default_settings = array();
  /**
   * @var array
   */
  protected $_settings;

  /**
   * @param string $settings_name
   * @param array $args
   */
  function __construct( $settings_name, $args = array() ) {
    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value )
      if ( property_exists(  $this, $property ) )
        $this->$property = $value;

    $this->settings_name = $settings_name;

    if ( ! isset( $this->plugin ) )
      $this->plugin = $this->admin_form->plugin;

    $this->option_name = "{$this->plugin->plugin_name}_{$this->settings_name}";

  }
  /**
   * Get an array of new settings (empty string; '').
   *
   * Override in subclass to add more specific setting defaults.
   * @return array
   */
  function get_new_settings() {
    if ( 0 == count( $this->_default_settings ) ) {
      foreach( $this->admin_form->get_sections() as $form_section ) {
        foreach( $form_section->fields as $field ) {
          $this->_default_settings[$field->field_name] = isset( $field->field_default ) ? $field->field_default : '';
        }
      }
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
        $this->plugin->current_settings = $this;
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
