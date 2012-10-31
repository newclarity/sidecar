<?php
/**
 *
 */
class Surrogate_Settings {
  /**
   * @var array
   */
  protected $_defined_settings = array();
  /**
   * @var array
   */
  protected $_settings;
  /**
   * @var Surrogate_Plugin_Base
   */
  var $plugin;
  /**
   * @var string
   */
  var $settings_name;

  /**
   * @param string $setting_name
   * @param array $args
   */
  function __construct( $setting_name, $args = array() ) {
    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value )
      if ( property_exists(  $this, $property ) )
        $this->$property = $value;

    $this->setting_name = $setting_name;

  }

  /**
   * @param       $setting_name
   * @param array $args
   */
  function add_setting( $setting_name, $args = array() ) {
 		$this->_defined_settings[$setting_name] = (object)$args;
 	}
  /**
   * Get an array of new settings (empty string; '').
   *
   * Override in subclass to add more specific setting defaults.
   * @return array
   */
  function get_new_settings() {
    $new_settings = array();
    foreach( $this->_defined_settings as $name => $setting ) {
      $new_settings[$name] = isset( $setting['default'] ) ? $setting['default'] : '';
    }
 		return $new_settings;
 	}

  /**
   * @return array
   */
  function get_settings() {
    if ( ! isset( $this->_settings ) )
      $this->_settings = array_merge( $this->get_new_settings(), get_option( $this->settings_name ) );
    return $this->_settings;
 	}

}
