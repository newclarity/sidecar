<?php
/**
 *
 */
class Sidecar_Admin_Tab extends Sidecar_Base {
  /**
   * @var string
   */
  var $tab_slug;
  /**
   * @var string
   */
  var $tab_text;
  /**
   * @var bool|string
   */
  var $page_title = false;
  /**
   * @var bool|callable
   */
  var $tab_handler = false;
  /**
   * @var bool|string
   */
  var $before_page_title = false;
  /**
   * @var bool|string
   */
  var $before_content = false;
  /**
   * @var bool|string
   */
  var $after_content = false;
  /**
   * @var string|Sidecar_Form
   */
  var $forms = array();
  /**
   * @return bool
   */
  function has_forms() {
    return is_array( $this->forms );
  }

  /**
   * @param string $tab_slug
   * @param string $tab_text
   * @param array $args
   */
  function __construct( $tab_slug, $tab_text, $args = array() ) {
    $this->tab_slug = $tab_slug;
    $this->tab_text = $tab_text;

    /**
     * Copy properties in from $args, if they exist.
     */
    foreach( $args as $property => $value ) {
      if ( property_exists( $this, $property ) ) {
        $this->$property = $value;
      } else if ( property_exists( $this, $property = "tab_{$property}" ) ) {
        $this->$property = $value;
      }
    }

    if ( ! $this->page_title )
      $this->page_title = $tab_text;

    if ( ! $this->forms && isset( $args['form'] ) ) {
      /**
       * Is 'form' passed in (singluar) grab it, otherwise grab the tab slug.
       * Later convert the current tab's form to an object.
       * Note: If form is passed as only 'true' then get the tab slug,
       */

      if ( true === $args['form'] )
        $args['form'] = $tab_slug;

      $this->forms = array( $args['form'] );
     // $this->forms = array( isset( $args['form'] ) ? $args['form'] : $tab_slug );
    }

  }
}
