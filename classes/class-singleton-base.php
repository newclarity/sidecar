<?php
/**
 *
 */
abstract class Sidecar_Singleton_Base {
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
      Sidecar::show_error( $message , get_called_class() );
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
  function this() {
    return self::$_instances[get_called_class()];
  }

  /**
   * Clean syntax to access the value on an instance variable for a Singleton class
   *
   * @param $instance_var_name
   *
   * @return mixed
   */
  static function get( $instance_var_name ) {
    $instance = self::_get_instance();
    return isset( $instance->$instance_var_name ) ? $instance->$instance_var_name : null;
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
      $result = call_user_func_array( array( self::_get_instance(), $method_name ), $args );
    }
    return isset( $result ) ? $result : null;
  }

  /**
   *
   */
  private static function _get_instance() {
    if ( isset( self::$_instances[$class = get_called_class()] ) ) {
      $instance = self::$_instances[$class];
    } else {
      $instance = new $class();
    }
    return $instance;
  }

  /**
 	 * Adds a filter hook for an object
 	 *
 	 * 	$this->add_filter( 'wp_title' );
 	 * 	$this->add_filter( 'wp_title', 11 );
 	 * 	$this->add_filter( 'wp_title', 'special_func' );
 	 * 	$this->add_filter( 'wp_title', 'special_func', 11 );
 	 * 	$this->add_filter( 'wp_title', array( __CLASS__, 'class_func' ) );
 	 * 	$this->add_filter( 'wp_title', array( __CLASS__, 'class_func' ), 11 );
 	 *
 	 * @param string $action_name
 	 * @param bool|int|string|array $callable_or_priority
 	 * @param int $priority
 	 * @return mixed
 	 */
 	function add_filter( $action_name, $callable_or_priority = false, $priority = 10 ) {
 		if ( ! $callable_or_priority ) {
 			$value = add_filter( $action_name, array( $this, $action_name ), $priority, 99 );
 		} else if ( is_numeric( $callable_or_priority ) ) {
 			$value = add_filter( $action_name, array( $this, $action_name ), $callable_or_priority, 99 );
 		} else if ( is_string( $callable_or_priority ) ) {
 			$value = add_filter( $action_name, array( $this, $callable_or_priority ), $priority, 99 );
 		} else if ( is_array( $callable_or_priority ) ) {
 			$value = add_filter( $action_name, $callable_or_priority, $priority, 99 );
 		}
 		return $value;
 	}
 	/**
 	 * Adds an action hook for an object
 	 *
 	 * 	$this->add_action( 'init' );
 	 * 	$this->add_action( 'init', 11 );
 	 * 	$this->add_action( 'init', 'special_func' );
 	 * 	$this->add_action( 'init', 'special_func', 11 );
 	 * 	$this->add_action( 'init', array( __CLASS__, 'class_func' ) );
 	 * 	$this->add_action( 'init', array( __CLASS__, 'class_func' ), 11 );
 	 *
 	 * @param string $action_name
 	 * @param bool|int|string|array $callable_or_priority
 	 * @param int $priority
 	 * @return mixed
 	 */
 	function add_action( $action_name, $callable_or_priority = false, $priority = 10 ) {
 		$this->add_filter( $action_name, $callable_or_priority, $priority );
 	}

}
