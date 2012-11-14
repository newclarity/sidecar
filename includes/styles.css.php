<?php
/**
 * Designed to allow caching
 *
 * @author Mike Schinkel <mike@newclarity.net>
 *
 */
/**
 * @param string $css_dir
 */
function output_css( $css_dir ) {
  $files = headers( array(
    'css_dir' => $css_dir,
    'color_scheme' => filter_input( INPUT_GET, 'color', FILTER_SANITIZE_STRING ),
    'cache_seconds' =>  filter_input( INPUT_GET, 'cache', FILTER_SANITIZE_NUMBER_INT ),
   ));
  body( $files );
}
/**
 * @param array $args
 *
 * @return array Files
 */
function headers( $args ) {
  header( "Content-type: text/css" );

  if ( empty( $args['color_scheme'] ) )
    $args['color_scheme'] = 'grey';
  if ( empty( $args['cache_seconds'] ) )
    $args['cache_seconds'] = 3600;

  $files = array();
  $files[0] = "{$args['css_dir']}/styles.css";
  $files[1] = "{$args['css_dir']}/styles-{$args['color_scheme']}.css";

  $file_time = filemtime( __FILE__ );
  foreach( $files as $file )
    if ( $file_time < ( $new_file_time = filemtime( $file ) ) )
      $file_time = $new_file_time;

  $time_string = format_gmt_string( $file_time );
  $timeout = format_gmt_string( time() + (int)$args['cache_seconds'] );
  $etag = (string)$file_time;

  $modified = true;
  $matched = false;

  if ( ! empty( $_SERVER['HTTP_IF_NONE_MATCH'] ) )
    $matched = $etag == trim( $_SERVER['HTTP_IF_NONE_MATCH'], '"' );

  if ( ! empty( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) )
    $modified = $time_string != $_SERVER['HTTP_IF_MODIFIED_SINCE'];

  if ( $matched || ! $modified ) {
    header('HTTP/1.1 304 Not Modified');
    exit();
  } else {
    header( "Cache-Control: public, max-age={$args['cache_seconds']}" );
    header( "Last-Modified: {$time_string}" );
    header( "ETag: \"{$file_time}\"" );
    header( "Expires: {$timeout}" );
    header( "Connection: close" );
  }
  return $files;
}
/**
 * Loads contents of CSS files and then echos them out.
 *
 * @param array $files
 */
function body( $files ) {
  foreach( $files as $file ) {
    if ( file_exists( $file ) ) {
      echo file_get_contents( $file );
    }
  }
}
/**
 * @param datetime $datetime
 * @return string
 */
function format_gmt_string( $datetime ) {
  return gmdate( 'D, d M Y H:i:s ', $datetime ) . 'GMT';
}

