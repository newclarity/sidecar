<?php

class Surrogate_Shortcode {
	private static $_current_class;
	private static $self;
	private static $_valid_shortcodes = array();
	private static $_valid_attributes = array();
	var $name;
	var $attributes;
	var $do_args; // For do_shortcode( $shortcode->do_args )

	static function on_load() {
		add_action( 'init', array( __CLASS__, 'init' ) );
	}
	static function init() {
	}
	/**
	 * Return the list of attributes that are valid for this shortcode.
	 *
	 * @return mixed
	 */
	static function get_valid_attributes() {
		return $_valid_attributes;
	}
	function __construct( $shortcode_name ) {
		$this->name = $shortcode_name;
		/**
		 * The first time we instantiate
		 */
		if ( ! isset( $_valid_attributes[$shortcode_name] ) ) {
			$_valid_attributes[$shortcode_name] = array();
			do_action( 'init_shortcode_attributes' );
		}
	}
	static function set_current_class( $shortcode_class_name ) {
		self::$_current_class = $shortcode_class_name;
	}
	static function get_current_class() {
		return self::$_current_class;
	}
	static function register( $shortcode_name, $class_name ) {
		self::$_valid_shortcodes[$shortcode_name] = $class_name;
		add_shortcode( $shortcode_name, array( __CLASS__, 'do_shortcode' ) );
	}
	static function do_shortcode() {
		// TODO: Flesh this out.
	}
	static function add_attribute( $attribute_name, $args ) {
		$shortcode_classes = array_flip( self::$_valid_shortcodes );
		$shortcode_name = $shortcode_classes[self::$_current_class];
		$args['setting'] = isset( $args['setting'] ) ? $args['setting'] : $attribute_name;
		$args['example_value'] = isset( $args['example_value'] ) ? $args['example_value'] : ( isset( $args['example'] ) ? false : '12345' );
		$args['example'] = isset( $args['example'] ) ? $args['example'] : "[{$shortcode_name} {$attribute_name}=\"{{$args['setting']}}\"] where {{$args['setting']}} = {{$args['example_value']}}";
		self::$_valid_attributes[$shortcode_name][$attribute_name] = (object)$args;
	}
	static function is_registered( $shortcode_name ) {
		return isset( self::$_valid_shortcodes[$shortcode_name] );
	}
	/**
	 * @param string $shortcode_name
	 * @return Surrogate_Shortcode
	 */
	static function create( $shortcode_name ) {
		$class_name = self::$_valid_shortcodes[$shortcode_name];
		$new = class_exists( $class_name ) ? new $class_name( $shortcode_name ) : false;
		if ( $new && ! is_a( $new, __CLASS__ ) )
			$new = false;
		return $new;
	}
	static function match_attributes( $content ) {
		$matches = array();
		$pattern = get_shortcode_regex();
		preg_match_all( "#{$pattern}#s", $content, $matched_shortcodes, PREG_SET_ORDER );
		// TODO: Handle multiple shortcodes of the same type
		if ( is_array( $shortcodes ) && count( $shortcodes ) ) {
			foreach( $matched_shortcodes as $matched_shortcode ) {
				if ( self::is_registered( $matched_shortcode[2] ) ) {
					$shortcode = self::create( $matched_shortcode[2] );
					$shortcode->attributes = shortcode_parse_atts( $matched_shortcode[3] );
					$shortcode->do_args = $matched_shortcode;	 // For do_shortcode( $shortcode->do_args )
					$matches[$matched_shortcode[2]][] = $shortcode;
				}
			}
		}
		return $matches;
	}

}
Surrogate_Shortcode::on_load();


function has_shortcode( $shortcode_name ) {
	return Surrogate_Shortcode::is_registered( $shortcode_name );
}

