<?php

/**
 * Class Sidecar_Settings
 */
class Sidecar_Settings {

  /**
   * @var string
   */
  var $option_name;

  /**
   * @var array
   */
  private $_settings_values = array();

  /**
   * @var array
   */
  private $_state;

  /**
   * @var bool
   */
  private $_is_decrypted;

  /**
   * @var Sidecar_Plugin_Base|Sidecar_Settings
   */
  private $_parent;

  /**
   * @var bool
   */
  protected $_is_dirty = false;

  /**
   * @var array List of names for required fields.
   */
  private $_required_settings = array();

  /**
   * @var string
   */
  var $installed_version;

  /**
   * @var bool
   */
  var $configured = false;

  /**
   * @param string $option_name
   * @param Sidecar_Plugin_Base|Sidecar_Settings $parent
   */
  function __construct( $option_name, $parent ) {
    $this->option_name = $option_name;
    $this->_parent = $parent;
    $this->load_settings();
    if ( method_exists( $this->_parent, 'filter_settings' ) )
      $this->_settings = $this->_parent->filter_settings( $this->_settings, $args );

    /**
     * If this' parent is not Sidecar_Settings then autosave on shutdown.
     */
    if ( ! is_a( $this->_parent, __CLASS__ ) )
      add_action( 'shutdown', array( $this, 'shutdown' ) );

  }

  /**
   * Autosave dirty settings on shutdown
   */
  function shutdown() {
    if ( $this->_is_dirty ) {
      /**
       * @var Sidecar_Settings|mixed $value
       */
      foreach( $this->_settings_values as $setting_name => $setting_value ) {
        if ( is_a( $value, __CLASS__ ) && $value->is_decrypted() && method_exists( $this->_parent, 'encrypt_settings' ) ) {
          $this->_settings_values[$setting_name] = call_user_func(
            array( $this->_parent, 'encrypt_settings' ),
            $this->_settings[$setting_name],
            $this
          );
          $value->set_decrypted( false );
        }
      }
      $this->save_settings();
    }
  }

  /**
   * Get a string representing the encryption status
   * @param bool|string $setting_name
   * @return string
   */
  function is_decrypted( $setting_name = false ) {
    if ( $setting_name ) {
      $is_decrypted = $this->get_setting( $setting_name )->is_decrypted();
    } else if ( ! is_a( $this->_parent, __CLASS__ ) ) {
      $is_decrypted = $this->_is_decrypted;
    } else {
      $is_decrypted = true;
      /**
       * Can't cache this (sung to the tune of MC Hammer's U Can't Touch This...)
       *
       * @var Sidecar_Settings $value
       */
      foreach( $this->_settings_values as $name => $value ) {
        if ( is_a( $value, __CLASS__ ) && ! $value->is_decrypted() ) {
          $is_decrypted = false;
        }
      }
    }
    return $is_decrypted;
  }

  /**
   * @param bool $decrypted
   */
  function set_decrypted( $decrypted ) {
    if ( ! is_a( $this->_parent, __CLASS__ ) )
      $this->_is_decrypted = $decrypted;
  }

  /**
   * @todo Implement decryption
   * @param Sidecar_Settings $settings
   * @param Sidecar_Form $caller
   *
   * @return mixed
   */
  function decrypt_settings( $settings, $caller ) {
    if ( method_exists( $this->_parent, 'decrypt_settings' ) ) {
      $settings = call_user_func( array( $this->_parent, 'decrypt_settings' ), $settings, $caller, $this );
      $settings->set_decrypted( true );
   	}
    return $settings;
  }

  /**
   * @param string $setting_name
   *
   * @return bool
   */
  function has_setting( $setting_name ) {
    return isset( $this->_settings_values[$setting_name] );
  }

  /**
   * @param string $setting_name
   *
   * @return bool|mixed|Sidecar_Settings
   */
  function get_setting( $setting_name ) {

    if ( ! isset( $this->_settings_values[$setting_name] ) )
      $this->_settings_values[$setting_name] = new Sidecar_Settings( $setting_name, $this );

    if ( method_exists( $this->_parent, $method_name = "get_setting_{$setting_name}" ) )
      call_user_func( array( $this->_parent, $method_name ), $settings );

    if ( ! $plugin_settings->is_decrypted( $setting_name ) )
      $plugin_settings->decrypt_settings( $form_settings, $this );

    return $this->_settings_values[$setting_name];
  }

  /**
   * @return array
   */
  function get_settings_values() {
    return $this->_settings_values;
  }

  /**
   * Accepts an array of name/value pairs and assigns  the settings.
   *
   * @param array $settings
   */
  function initialize_settings( $settings ) {
    if ( is_array( $settings ) )
     foreach( $settings as $name => $value ) {
       /*
        * Objects are probably forms and non-objects probably form fields.
        */
       if ( is_array( $value ) ) {
         $child_settings = $this->get_setting( $name, $this );
         $child_settings->initialize_settings( $value );
         $this->_settings_values[$name] = $child_settings;
       } else if ( empty( $this->_settings_values[$name] ) ) {
          $this->_settings_values[$name] = $value;
        }
      }
  }

  /**
   * @param array $settings_values
   * @param bool $set_dirty
   */
  function update_settings_values( $settings_values, $set_dirty = true ) {
    $this->_settings_values = is_array( $settings_values ) ? $settings_values : array();
    if ( $set_dirty )
      $this->_is_dirty = true;
  }

  /**
   * @param string $setting_name
   * @param mixed $value
   * @param bool $set_dirty
   * @return array
   */
  function update_settings_value( $setting_name, $value, $set_dirty = true ) {
    $this->_settings_values[$setting_name] = $value;
    if ( $set_dirty )
      $this->_is_dirty = true;
  }

  /**
   * Update the settings based on an array of option values
   *
   * The keys in top level/global array are probably form names and the keys in the subject arrays are field names.
   * This is different from "Settings Values" in that the top level array contains form objects vs. arrays of values.
   *
   * @param $settings_array
   */
  function update_settings_array( $settings_array ) {
    foreach( $settings_array as $name => $value ) {
      $settings = $this->get_setting( $name );
      $settings->update_settings_values( $value );
      $settings_option->values[$name] = $settings;
    }
  }

  /**
   */
  function update_settings() {
    $this->_is_dirty = true;
  }

  /**
   * @param object $settings_option
   * @param bool $set_dirty
   * @return array
   */
  function update_settings_option( $settings_option, $set_dirty = true ) {
    if ( is_a( $this->_parent, __CLASS__ ) ) {
      $this->configured = $settings_option->configured;
      $this->installed_version = $settings_option->installed_version;
      $this->update_settings_array( $settings_option->values );
      if ( $set_dirty )
        $this->_is_dirty = true;
    }
  }

  /**
   * @param array $required_settings
   */
  function set_required_setting_names( $required_settings ) {
    $this->_required_settings = $required_settings;
  }

  /**
   * @param bool|array $required_setting_names
   *
   * @return bool
   */
  function has_required_settings( $required_setting_names = false ) {
    $has_required_settings = true;
    if ( $required_setting_names )
      $this->set_required_setting_names( $required_setting_names );

    /** @var Sidecar_Form $form */
    foreach( $this->_required_settings as $setting_name ) {
      if ( empty( $this->_settings_values[$setting_name] ) ) {
        $has_required_settings = false;
        break;
      }
    }
    if ( method_exists( $this, 'filter_has_required_settings' ) ) {
      $has_required_settings = $this->filter_has_required_settings( $has_required_settings, $this );
    }
    return $has_required_settings;
  }

  /**
   * @return array
   */
  function get_empty_settings_values() {
    return array_fill_keys( array_keys( $this->_settings_values ), '' );
  }

  /**
   * @return bool
   */
  function is_dirty() {
    return $this->_is_dirty;
  }

  /**
   * @return array
   */
  function get_settings_array() {
    if ( ! $this->_parent instanceof Sidecar_Plugin_Base ) {
      $array = $this->get_settings_values();
    } else {
      $array = array();
      /**
       * @var Sidecar_Settings $value
       */
      foreach( $this->_settings_values as $name => $value )
        $array[$name] = $value->get_settings_values();
    }
    return $array;
  }

  /**
   * Save settings if parent is Sidecar_Plugin_Base
   *
   */
  function save_settings() {
    if ( $this->_parent instanceof Sidecar_Plugin_Base ) {

      // @todo Sure default values for settings array here.

      update_option( $this->option_name, (object)array(
        'installed_version' => $this->installed_version,
        'configured'        => $this->configured,
        'values'            => $this->get_settings_array(),
      ));
    }
  }

  /**
   * Load settings if parent is Sidecar_Plugin_Base
   *
   */
  function load_settings() {
    if ( $this->_parent instanceof Sidecar_Plugin_Base ) {

      $option = get_option( $this->option_name );

      if ( ! empty( $option->values ) && is_array( $option->values ) )
        $this->initialize_settings( $option->values );
      if ( ! empty( $option->configured ) && is_bool( $option->configured ) )
        $this->configured = $option->configured;
      if ( ! empty( $option->installed_version ) )
        $this->installed_version = $option->installed_version;

    }
  }

  /**
   *
   */
  function delete_settings() {
    delete_option( $plugin->option_name );
  }

}
