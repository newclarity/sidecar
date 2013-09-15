#Sidecar

Sidecar is the _"missing Plugin API for WordPress"_.  It's designed to handle the 80 percentile use-case very easily, yet still make it possible and not to hard to handle the 20 percentile use-case.

At any point in time Sidecar has implement a certain set of common needs but probably still has a backlog of other common needs.  Check out the [issue tracker](https://github.com/newclarity/sidecar/issues) to see if features it doesn't have are planned yet; if not, add them!

##Declarative vs. Procedural Programming
Sidecar is designed to allow [Declarative Programming](http://en.wikipedia.org/wiki/Declarative_programming) rather than [procedural programming](http://en.wikipedia.org/wiki/Procedural_programming) of those things that are commonly needed in a plugin.

In WordPress `register_post_type()` is an example of Declarative Programming whereas the WordPress action and filter hook system is an example of [procedural programming](http://en.wikipedia.org/wiki/Procedural_programming).

##Usage
Use [Imperative](https://github.com/newclarity/imperative) and then in you **main plugin file** you use the following pattern _(note: 'X.Y.Z' is version of [_RESTian_](https://github.com/newclarity/restian) and 'A.B.C' is version of [_Sidecar_](https://github.com/newclarity/sidecar)):_

	/*
	 * Plugin Name: Your Plugin Name Here...
	 * ...(other headers)...
	 */
	
	/* 
	 * Imperative is a PHP library loader/manager
	 */
	require( dirname( __FILE__ ) . '/libraries/imperative/imperative.php' );
	
	require_library( 'restian', 'X.Y.Z', __FILE__, 'libraries/restian/restian.php' );
	require_library( 'sidecar', 'A.B.C', __FILE__, 'libraries/sidecar/sidecar.php' );
	
	register_plugin_loader( __FILE__ );

Imperative loads _latest_ library but **only** if two libraries are compatible. For example:

- `'1.5.3'` is compatible with `'1.7'` because both are `'1.x.y'`.
	- Will load `'1.7'` but not `'1.5.3'`
- `'1.5.3'` is **not** compatible with `'2.1'` because not same major version, i.e. `'1' != '2'`
	- Will trigger user error message on plugin activation.


##Plugin Loader

The last line of the main plugin file calls `register_plugin_loader( __FILE__ );` which references `/loader.php` that gets called within the `'plugins_loaded'` hook. This file will be where things that can't get executed multiple times are placed, for example:

	<?php
	/*
	 * This code is run after Imperative validates 
	 * the required libraries are available and loaded.
	 */
	define( 'REVOSTOCK_GALLERY_DIR', dirname( __FILE__ ) );
	define( 'REVOSTOCK_GALLERY_VER', '1.2.0' );
	define( 'REVOSTOCK_GALLERY_MIN_PHP', '5.2.4' );
	define( 'REVOSTOCK_GALLERY_MIN_WP', '3.2' );
	
	require( REVOSTOCK_GALLERY_DIR . '/classes/class-api-client.php');
	require( REVOSTOCK_GALLERY_DIR . '/classes/class-transients.php');
	require( REVOSTOCK_GALLERY_DIR . '/classes/class-plugin.php');
	

##Classes in Sidecar

- `Sidecar_Plugin_Base` - Sidecar core functionality; extends `Sidecar_Singleton_Base`.
- `Sidecar_Admin_Page` - Provides instances of _"Admin Pages"_, represented by a URL base in the WordPress admin console. An Admin Page can be comprised of multiple tabs.
- `Sidecar_Admin_Tab` - Provides zero or more instances of _"Admin Tabs"_ for an Admin Page.
- `Sidecar_Form` - Provides zero or more instances of _"Forms"_ within an Admin Tab or a standalone Admin Page.
- `Sidecar_Field` - Provides one or more instances of _"Fields"_ within an Form. 
- `Sidecar_Singleton_Base` - Provides Singleton functionality.
- `Sidecar_Shortcode` - Support implementation of WordPress shortcodes.

###URL Base
For example:

	http://example.com/wp-admin/options-general.php?page={$plugin-slug}




##Plugin Class

The Plugin Class should extend `Sidecar_Plugin_Base` and is where _(almost)_ all the the configuration is done. Explicitly this means you do not extend `Sidecar_Admin_Page`, `Sidecar_Admin_Tab`, etc.

> _**NOTE**: If you are a programming purist this might seem wrong but Sidecar caters to "Accidental Programmers" instead of professional programmers. The latter are going to use Ruby on Rails anyway._ :) 

Start by creating a plugin class that extends `Sidecar_Plugin_Base`:

    class Your_Named_Plugin extends Sidecar_Plugin_Base {
    
    }

Next you add a series of `initialize_*()` methods _(not all are required for every plugin):_

    initialize_plugin()
    initialize_admin()
    initialize_admin_page( $admin_page )
    initialize_admin_tab( $admin_tab )
	initialize_form( $form )
	initialize_shortcodes()
	initialize_template()
	initialize_shortcode( $shortcode )
	
The reason for these different initialization methods is so that memory is only used if its going to be needed.  Why initialize a Form if you are not on the Admin Tab that displays is?
