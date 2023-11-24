=== CHIPS Custom Formats ===
Contributors: CHIPS
Tags: tinyMCE, WYSIWYG
 
Requires at least: 3.2.1
Tested up to: 4.2

== Description ==

Creates an options page for adding custom formats to the WordPress WYSIWYG editor.

== Installation ==

It can be installed in two ways, firstly by downloading the plugin from the Wordpress directory website or by via the Wordpress admin page for adding a new plugin

Downloading from Wordpress Website

1. Download the plugin from the wordpress plugin directory
2. Unzip the plugin
3. Upload `/chips-custom-formats/` directory to the `/wp-content/plugins/` directory
4. Activate the plugin through the 'Plugins' menu in WordPress
5. Create a custom-login.css in your theme directory
6. Use the chips-custom-formats action hooks
* wp_custom_login_header_before
* wp_custom_login_header_after
* wp_custom_login_footer_before
* wp_custom_login_footer_after

Using the Wordpress Admin page for installing

1. Go to the admin page and select the 'Plugins' menu, using the 'Add new' menu item
2. Search for 'WP Custom Login'
3. Select install for the plugin 'WP Custom Login' by Ninos Ego
4. Activate the plugin through the 'Installing Plugin' page in WordPress
5. Create a chips-custom-formats.css in your theme directory
6. Use the chips-custom-formats action hooks
* wp_custom_login_header_before
* wp_custom_login_header_after
* wp_custom_login_footer_before
* wp_custom_login_footer_after


== Screenshots ==


== Changelog ==

= 1.0.0 =
Initial build