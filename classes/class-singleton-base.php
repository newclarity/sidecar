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
   * Bool to holds on to >=PHP 5.3 check
   */
  private static $_is_php53;

  /**
   *
   */
  function __construct() {
    if ( ! isset( self::$_is_php53 ) )
      self::$_is_php53 = version_compare( PHP_VERSION, '5.3.0', '>=' );

    $this_class = get_class( $this );

    if ( isset( self::$_instances[$this_class] ) ) {
      $message = __( '%s is a singleton class and cannot be instantiated more than once.', 'sidecar' );
      Sidecar::show_error( $message , self::_get_called_class() );
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
  static function this() {
    return self::$_instances[self::_get_called_class()];
  }

  /**
   * Clean syntax to access the value on an instance variable for a Singleton class
   *
   * @param $instance_var_name
   *
   * @return mixed
   */
  static function get( $instance_var_name ) {
    $instance = self::_get_instance( self::_get_called_class() );
    return isset( $instance->$instance_var_name ) ? $instance->$instance_var_name : null;
  }

  /**
   * Clean syntax to call a method on an instance variable for a Singleton class
   *
   * @param string $method_name
   *
   * @return mixed
   */
  static function call( $method_name ) {
    if ( method_exists( $called_class = self::_get_called_class(), $method_name ) ) {
      $args = func_get_args();
      array_shift( $args );
      $result = call_user_func_array( array( self::_get_instance( $called_class ), $method_name ), $args );
    }
    return isset( $result ) ? $result : null;
  }

  /**
   *
   */
  private static function _get_instance( $called_class ) {
    if ( ! isset( self::$_instances[$called_class] ) )
      self::$_instances[$called_class] = new $called_class();
    return self::$_instances[$called_class];
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
    $value = false;
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

  /**
   * Return name of calling class.
   *
   * Provides (hacky) support for PHP < 5.3.
   *
   * @return string
   */
  protected static function _get_called_class() {
    static $classes = array();
    if ( self::$_is_php53 && ( ! defined( 'SIDECAR_DEBUG' ) || ! SIDECAR_DEBUG ) ) {
      return get_called_class();
    } else {
      /**
       * Simulate get_called_class() for < PHP 5.3
       * @see http://stackoverflow.com/a/7904487/102699 for our inspiration
       */
      $backtrace = debug_backtrace( false );
      for( $level = 1; $level < count( $backtrace ); $level++ ) {
        $call = $backtrace[$level];
        $key = "{$call['file']}/{$call['function']}/{$call['line']}";
        if ( isset( $classes[$key] ) ) {
          break;
        } else {
          $class_names = implode( '|', array_keys( self::$_instances ) );
          $lines = file($call['file']);
          preg_match_all(
            "#({$class_names})::{$call['function']}(\s*|=|\()#",
            $lines[$call['line']-1],
            $matches
          );
          unset( $lines );
          if ( 0 == count( $matches[1] ) ) {
            continue;
          } if ( 1 < count( $matches[1] ) ) {
            $calls = implode( "::{$call['function']}() or ", $matches[1] ) . "{$call['function']}()";
            trigger_error( sprintf( __( 'Too many calls to static method ::%s() on line %d of %s; can only have one of: %s', 'sidecar' ),
              $call['function'], $call['line'], $call['file'], $calls
            ));
          } else {
            $classes[$key] = $matches[1][0];
            break;
          }
        }
      }
      return $classes[$key];
    }
  }

//  static function instantiate() {
//    $class_name = self::_get_hooked_class();
//    self::$_instances[$class_name] = new $class_name();
//  }

  /**
   * @return bool
   */
  private static function _get_hooked_class() {
    $hooked_class = false;
    $backtrace = debug_backtrace( false );
    for( $index = 2; $index < count( $backtrace ); $index++ ) {
      if ( 'call_user_func_array' == $backtrace[$index]['function'] ) {
        $hooked_class = $backtrace[$index]['args'][0][0];
        break;
      }
    }
    return $hooked_class;
  }

}

