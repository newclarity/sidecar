<?php
/**
 *
 */
class Surrogate_Shortcode {
  /**
   * @var Surrogate_Plugin
   */
  var $plugin;
  /**
   * @var string
   */
  var $shortcode_name;
  /**
   * @var array
   */
	protected $_attributes = array();
//  /**
//   * @var array
//   */
//  var $do_args; // For do_shortcode( $shortcode->do_args )
  /**
   * @var bool
   */
  var $initialized = false;

  /**
   * @param       $shortcode_name
   * @param array $args
   */
  function __construct( $shortcode_name, $args = array() ) {
		$this->shortcode_name = $shortcode_name;

    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value )
      if ( property_exists(  $this, $property ) )
        $this->$property = $value;

	}

  /**
   * @param array $attributes
   * @param string $content
   *
   * @return null|string
   */
  function do_shortcode( $attributes, $content = null ) {
    return $this->plugin->do_shortcode( $this, $attributes, $content );
	}

  /**
   * @param $attribute_name
   * @param $args
   */
  function add_attribute( $attribute_name, $args ) {
    $args = wp_parse_args( $args, array(
      'default' => false,
      'sample' => isset( $args['example'] ) ? false : '12345',
      'example' => false,
      'help' => false,
      'setting' => $attribute_name,
    ));
    if ( ! $args['example'] )
      $args['example'] = <<<TEXT
[{$this->shortcode_name} {$attribute_name}="{$args['sample']}"]
TEXT;
    $this->_attributes[$attribute_name] = (object)$args;
  	}
    /**
     * @return array
     */
    function get_attributes() {
      return $this->_attributes;
    }
//  /**
//   * @param $content
//   *
//   * @return array
//   */
//  function match_attributes( $content ) {
//    $matches = false;
//    if ( count( $this->_attributes ) ) {
//      $matches = array();
//      $pattern = get_shortcode_regex();
//      preg_match_all( "#{$pattern}#s", $content, $matched_shortcodes, PREG_SET_ORDER );
//      // TODO: Handle multiple shortcodes of the same type
//			foreach( $matched_shortcodes as $matched_shortcode ) {
//				if ( isset( $this->_attributes[$matched_shortcode[2]] ) ) {
//					$attributes = shortcode_parse_atts( $matched_shortcode[3] );
//					$do_args = $matched_shortcode;	 // For do_shortcode( $shortcode->do_args )
//					$matches[$matched_shortcode[2]][] = array( $attributes, $do_args);
//				}
//			}
//		}
//		return $matches;
//	}

}
