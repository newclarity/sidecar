<?php

class Sidecar_Form_Settings extends Sidecar_Settings_Base {

  /**
   * @var array List of names for required fields.
   */
  private $_required_fields = array();

  /**
   * Register a setting
   * @param string $setting_name
   * @param bool|mixed $value
   */
  function register_setting( $setting_name, $value = false ) {
    if ( ! $this->offsetExists( $setting_name ) )
      $this->offsetSet( $setting_name, $value );
  }

  /**
   * @return array
   */
  function get_empty_field_values() {
    return array_fill_keys( array_keys( (array)$this ), false );
  }

  /**
   * Set the list of required settings names for this form.
   * @param bool|array $required_fields
   */
  function set_required_fields( $required_fields = false ) {
    $this->_required_fields = $required_fields;
  }

  /**
   * @return bool
   */
  function has_required_fields() {
    $has_required_fields = true;
    /** @var Sidecar_Form $form */
    foreach( $this->_required_fields as $setting_name ) {
      if ( ! $this[$setting_name] ) {
        $has_required_fields = false;
        break;
      }
    }
    return $has_required_fields;
  }


}
