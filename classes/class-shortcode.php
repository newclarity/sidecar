<?php
/**
 *
 */
class Sidecar_Shortcode {

  /**
   * @var Sidecar_Plugin_Base
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

  /**
   * @var bool
   */
  var $initialized = false;

  /**
   * @var string 'yes'/'no' vs. true/false as get_post_meta() returns '' for false and not found.
   *
   */
  var $used = 'no';

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

    $this->HAS_SHORTCODE_KEY  = "_sidecar_has_{$this->shortcode_name}_shortcode";

	}

  /**
   * @param array $attributes
   * @param string $content
   *
   * @return null|string
   */
  function do_shortcode( $attributes, $content = null ) {
    if ( empty( $attributes ) && ! is_array( $attributes ) )
      $attributes = array();
    $this->used = 'yes';
    return $this->plugin->do_shortcode( $this, $attributes, $content );
  }

  /**
   * This allows Sidecar_Plugin_Base to add a hook for this shortcode to monitor the content for shortcodes.
   */
  function add_the_content_filter() {
    add_filter( 'the_content', array( $this, 'the_content' ), 12 ); // AFTER WordPress' do_shortcode()
  }

  /**
  * @param string $content
  * @return string
  */
  function the_content( $content ) {
    global $post;
    if ( '' === get_post_meta( $post->ID, $this->HAS_SHORTCODE_KEY, true ) ) {
      /**
       * This is the first time the shortcode has ever been seen for this post.
       * Save a post_meta key so that next time we'll know this post uses this shortcode
       */
      $this->update_has_shortcode( $post->ID, $this->used );
    }
    remove_filter( 'the_content', array( $this, 'the_content' ), 12 );
    return $content;
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
      'api_var' => $attribute_name,
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

  /**
   * @param string $attribute_name
   *
   * @return array
   */
  function has_attribute( $attribute_name ) {
    return isset( $this->_attributes[$attribute_name] );
  }

  /**
   * Test to see if a given post needs externals by checking to see if shortcode was ever run.
   *
   * @param int $post_id
   *
   * @return bool
   */
  function get_maybe_has_shortcode( $post_id ) {
    $has_shortcode = get_post_meta( $post_id, $this->HAS_SHORTCODE_KEY, true );
    /**
     * The following test is a more compact way to test this:
     *
     *    ( true !== $has_shortcodes && false !== $has_shortcodes )
     *
     */
    if ( '' == $has_shortcode ) {
      /**
       * Set to true because it's unknown if we need the script (or style) so better safe then sorry
       */
      $has_shortcode = 'yes';
    }
    return 'yes' == $has_shortcode;
  }

  /**
   * Remove the has shortcode flag. Used by Sidecar_Plugin_Base to clear on a Post Save.
   *
   * @param int $post_id
   */
  function delete_has_shortcode( $post_id ) {
    delete_post_meta( $post_id, $this->HAS_SHORTCODE_KEY  );
  }

  /**
   * Test to see if a given post has this shortcode.
   *
   * @param int $post_id
   * @param bool $has_shortcodes
   *
   * @return bool
   */
  function update_has_shortcode( $post_id, $has_shortcodes ) {
    update_post_meta( $post_id, $this->HAS_SHORTCODE_KEY, $has_shortcodes );
  }

}
