<?php

/**
 * Class Sidecar_Settings_Base
 */
class Sidecar_Settings_Base extends ArrayObject {

  /**
   * @var Sidecar_Plugin_Base|Sidecar_Settings_Base
   */
  protected $_parent;

  /**
   * @var bool
   */
  protected $_is_dirty = false;

  /**
   * @param Sidecar_Plugin_Base|Sidecar_Settings_Base $parent
   */
  function __construct( $parent ) {
    $this->_parent = $parent;
  }

  /**
   * Register a setting
   * @param string $setting_name
   */
  function register_setting( $setting_name ) {
    $this->offsetSet( $setting_name, false );
  }

  /**
   * @param string $setting_name
   *
   * @return bool
   */
  function has_setting( $setting_name ) {
    return $this->offsetExists( $setting_name );
  }

  /**
   * @param string $setting_name
   *
   * @return bool|mixed|Sidecar_Settings_Base
   */
  function get_setting( $setting_name ) {
    return $this->offsetGet( $setting_name );
  }

  /**
   * @param string $setting_name
   * @param mixed $setting_value
   *
   */
  function set_setting( $setting_name, $setting_value ) {
    $this->offsetSet( $setting_name, $setting_value );
  }

  /**
   * @return array
   */
  function get_values() {
    return $this->getArrayCopy();
  }

  /**
   * @param string $setting_name
   * @return array
   */
  function get_value( $setting_name ) {
    return $this->offsetGet( $setting_name );
  }

  /**
   * @return array
   */
  function get_values_deep() {
    $values = array();
    /**
     * @var mixed|Sidecar_Settings_Base $value
     */
    foreach( $this->getArrayCopy() as $name => $value ) {
      $values[$name] = method_exists( $value, 'get_values' ) ? $value->get_values() : $value;
    }
    return $values;
  }

  /**
   * @param $is_dirty
   */
  function set_dirty( $is_dirty ) {
    $this->_parent->set_dirty( $is_dirty );
  }

  /**
   * @param array $settings_values
   */
  function set_values( $settings_values ) {

    if ( ! is_array( $settings_values ) )
      if ( empty( $settings_values ) ) {
        $settings_values = array();
        $this->set_dirty( true );
      } else {
        $settings_values = (array)$settings_values;
      }

    if ( $this->getArrayCopy() !== $settings_values ) {
      $this->exchangeArray( $settings_values );
      $this->set_dirty( true );
    }

  }

  /**
   * @param string $setting_name
   * @param mixed $setting_value
   * @param bool $set_dirty
   * @return array
   */
  function update_settings_value( $setting_name, $setting_value, $set_dirty = true ) {
    $this->offsetSet( $setting_name, $setting_value );
    if ( $set_dirty )
      $this->set_dirty( true );
  }

  /**
   */
  function update_settings() {
    $this->set_dirty( true );
  }

  /**
   * @return bool
   */
  function is_dirty() {
    return $this->_is_dirty;
  }

  /**
   * @todo verify this is needed in base class
   * Get a representation of the encryption status
   * @return bool
   */
  function is_encrypted() {
    return $this->_parent->is_encrypted();
  }

  /**
   * @todo verify this is needed in base class
   * @param bool $is_encrypted
   */
  function set_encrypted( $is_encrypted ) {
    $this->_parent->set_encrypted( $is_encrypted );
  }

  /**
   * @param int|string $offset
   *
   * @return Sidecar_Settings_Base|string|null
   */
  function offsetGet( $offset ) {
    return $this->offsetExists( $offset ) ? parent::offsetGet( $offset ) : false;
	}

//  /**
//   * @return ArrayIterator
//   */
//  function getIterator() {
//		return new ArrayIterator( $this->_settings_values );
//	}
//
//  /**
//   * @param int|string $offset
//   *
//   * @return bool
//   */
//  function offsetExists( $offset ) {
//		return isset( $this->_settings_values[$offset] );
//	}
//
//  /**
//   * @param int|string $offset
//   * @param Sidecar_Settings_Base|string|null $value
//   */
//  function offsetSet( $offset , $value ) {
//    if ( is_null( $offset ) ) {
//        $this->_settings_values[] = $value;
//    } else {
//        $this->_settings_values[$offset] = $value;
//    }
//  }
//
//  /**
//   * @param int|string $offset
//   */
//  function offsetUnset( $offset ) {
//		unset( $this->_settings_values[$offset] );
//	}
}
