<?php
/**
 *
 */
abstract class Sidecar_Singleton_Base extends Sidecar_Base {
  /**
   * @var Sidecar_Singleton_Base
   */
  private static $_instances = array();

  /**
   *
   */
  function __construct() {
    $this_class = get_class( $this );

    if ( isset( self::$_instances[$this_class] ) ) {
      $message = __( '%s is a singleton class and cannot be instantiated more than once.', 'sidecar' );
      Sidecar::show_error( $message , $this->plugin_class );
      exit;
    }

    self::$_instances[$this_class] = &$this;

    if ( method_exists( $this, 'on_load' ) ) {
  			$this->on_load();
  		}
  }
  /**
   * @return Sidecar_Singleton_Base
   */
  function me() {
    return isset( $this ) ? $this : self::$_instances[get_called_class()];
  }

  /**
   * Clean syntax to access the value on an instance variable for a Singleton class
   *
   * @param $instance_var_name
   *
   * @return mixed
   */
  static function get( $instance_var_name ) {
    $me = self::$_instances[get_called_class()];
    return isset( $me->$instance_var_name ) ? $me->$instance_var_name : null;
  }

  /**
   * Clean syntax to call a method the value on an instance variable for a Singleton class
   *
   * @param $method_name
   *
   * @return mixed
   */
  static function call( $method_name ) {
    if ( method_exists( get_called_class(), $method_name ) ) {
      $args = func_get_args();
      array_shift( $args );
      $me = self::$_instances[get_called_class()];
      $result = call_user_func_array( array( $me, $method_name ), $args );
    }
    return isset( $result ) ? $result : null;
  }

}
