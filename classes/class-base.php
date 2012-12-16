<?php
/**
 * Base for all Sidecar objects
 */
abstract class Sidecar_Base {
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

