<?php
/**
 *
 */
class Sidecar_Admin_Tab {
  /**
   * @var Sidecar_Plugin_Base
   */
  var $plugin;
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
  /**
   * @return bool
   */
  function has_forms() {
    return is_array( $this->forms );
  }

  /**
   * Determines if this tab has one of more form(s) that require an API.
   *
   * @return bool
   */
  function requires_api() {
    $requires_api = false;
    /**
     * @var string|array|Sidecar_Form $form
     */
    foreach( $this->forms as $index => $form ) {
      if ( is_string( $form ) )
        $form = $this->plugin->get_form( $form );
      if ( is_array( $form ) )
        $this->forms[$index] = $this->plugin->promote_form( $form );
      if ( $form->requires_api ) {
        $requires_api = true;
        break;
      }
    }
    return $requires_api;
  }

}
