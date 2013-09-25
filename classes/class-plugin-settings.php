<?php

class Sidecar_Plugin_Settings extends Sidecar_Settings_Base {

  /**
   * @var string
   */
  var $option_name;

  /**
   * @var string
   */
  var $installed_version;

  /**
   * @var bool
   */
  var $configured = false;

  /**
   * @var bool
   */
  protected $_is_encrypted;

  /**
   * @var object Mirrors $_parent so semantics are easier to understand while debugging.
   */
  var $_plugin;

  /**
   * @param Sidecar_Plugin_Base|Sidecar_Settings_Base $plugin
   * @param string $option_name
   */
  function __construct( $plugin, $option_name ) {
    parent::__construct( $plugin );
    $this->_plugin = $plugin;
    $this->option_name = $option_name;
    add_action( 'shutdown', array( $this, 'shutdown' ) );
  }

  /**
   * Register a form
   * @param string $form_name
   */
  function register_form_settings( $form_name ) {
    $this->register_setting( $form_name, 'Sidecar_Form_Settings' );
  }

  /**
   * Register a setting
   * @param string $setting_name
   * @param bool|string $setting_class
   */
  function register_setting( $setting_name, $setting_class = false ) {
    if ( class_exists( $setting_class ) )
      $this->offsetSet( $setting_name, new $setting_class( $this, $setting_name ) );
  }

  /**
   * @param string $setting_name
   *
   * @return Sidecar_Settings_Base|Sidecar_Form_Settings
   */
  function get_setting( $setting_name ) {
    if ( ! $this->offsetExists( $setting_name ) ) {
      $setting_value = false;
    } else {
      $setting_value = $this->offsetGet( $setting_name );
    }

    if ( method_exists( $this->_plugin, $method_name = "get_setting_{$setting_name}" ) ) {
      $setting_value = call_user_func( array( $this->_plugin, $method_name ), $setting_value );
    }

    return $setting_value;

  }

  /**
   * Autosave dirty settings on shutdown
   */
  function shutdown() {
    if ( $this->is_dirty() ) {
      $this->save_settings();
    }
  }

  /**
   * Removes settings from the wp_options table in the WordPress MySQL database.
   */
  function delete_settings() {
    delete_option( $plugin->option_name );
  }

  /**
   * Accepts an array of $form objects and assigns to the internal array.
   *
   * @param array $forms
   */
  function set_values( $forms ) {
    if ( is_array( $forms ) ) {
      $is_dirty = $forms !== $this->getArrayCopy();
      $this->exchangeArray( $forms );
      if ( $is_dirty )
        $this->set_dirty( true );
      }
    }

  /**
   * Accepts an array of name/value pairs and assigns the settings.
   *
   * @param array $forms_values
   */
  function set_values_deep( $forms_values ) {
    if ( is_array( $forms_values ) )
      foreach( $forms_values as $form_name => $form_values ) {
        $this->get_setting( $form_name )->set_values( $form_values );
      }
    $this->set_dirty( true );
  }

  /**
   * Updates the settings given the value stored in the wp_options table in the WordPress MySQL database.
   *
   * @param object $option
   * @param bool $set_dirty
   * @return array
   * @todo Change this to UNserialize
   */
  function set_option( $option, $set_dirty = true ) {

    $this->configured = isset( $option->configured ) ? $option->configured : false;

    $this->installed_version = isset( $option->installed_version ) ? $option->installed_version : 'unknown';

    $is_dirty = $this->is_dirty();

    if ( ! empty( $option->values ) && is_array( $option->values ) )
      $this->set_values_deep( $option->values );

    if ( $set_dirty )
      $this->set_dirty( true );
    else if ( ! $is_dirty )
      $this->set_dirty( false );
  }

  /**
   * Load settings from the wp_options table in the WordPress MySQL database.
   */
  function load_settings() {
    $option = get_option( $this->option_name );
    $this->set_option( $option, $set_dirty = false );
    if ( $this->is_encrypted() )
      $this->decrypt_settings();
    $this->set_dirty( false );
  }

  /**
   * Save settings to the wp_options table in the WordPress MySQL database.
   * @todo Change this to serialize
   */
  function get_option() {
    return (object)array(
      'installed_version' => $this->installed_version,
      'configured'        => $this->configured,
      'values'            => $this->get_values_deep(),
    );
  }

  /**
   * Save settings to the wp_options table in the WordPress MySQL database.
   */
  function save_settings() {
    if ( ! $this->is_encrypted() )
      $this->encrypt_settings();
    update_option( $this->option_name, $this->get_option() );
    $this->set_dirty( false );
  }

  /**
   * Get a string representing the encryption status
   * @return bool
   */
  function is_encrypted() {
    return $this->_is_encrypted;
  }

  /**
   * @param bool $is_encrypted
   */
  function set_encrypted( $is_encrypted ) {
    $this->_is_encrypted = $is_encrypted;
  }

  /**
   * Call decryption method if specified in plugin.
   *
   */
  function decrypt_settings() {
    if ( method_exists( $this->_plugin, 'decrypt_settings' ) ) {
      call_user_func( array( $this->_plugin, 'decrypt_settings' ), $this );
   	}
    $this->set_encrypted( false );
  }

  /**
   * Call decryption method if specified in plugin.
   *
   */
  function encrypt_settings() {
    if ( method_exists( $this->_plugin, 'encrypt_settings' ) ) {
      call_user_func( array( $this->_plugin, 'encrypt_settings' ), $this );
   	}
    $this->set_encrypted( true );
  }

  /**
   * @param $is_dirty
   */
  function set_dirty( $is_dirty ) {
    $this->_is_dirty = $is_dirty;
  }

}
