=== CWP Snippets ===
Contributors: rcates00
Tags: filemaker, code snippets, php, css, javascript
Requires at least: 5.2
Tested up to: 6.8
Requires PHP: 7.2
Stable tag: 1.6.9
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A plugin designed to enhance WordPress by providing seamless connectivity and integration with FileMaker databases.

== Description ==

CWP Snippets extends WordPress functionality by providing robust connectivity and integration with FileMaker databases. It allows developers and administrators to manage and execute custom code snippets that can retrieve, display, and manipulate data from FileMaker, enriching WordPress sites with dynamic data-driven content.

**Key Features Include:**
- **FileMaker Integration**: Connect and interact directly with FileMaker databases.
- **Snippet Management**: Easily add, edit, and manage custom PHP, HTML, CSS, and JavaScript snippets.
- **Dynamic Shortcode Execution**: Utilize shortcodes to embed custom logic within posts and pages.
- **Secure Preview Capability**: Preview how snippets will run, restricted to admin users for safety.
- **Advanced Code Editing**: Integrated CodeMirror editor provides syntax highlighting and error detection.
- **Code Validation**: Ensures uniqueness and correct syntax to prevent issues during execution.

This plugin is ideal for developers needing to integrate FileMaker data into WordPress, providing tools necessary for robust data manipulation and presentation.

== Installation ==

1. Upload the `cwp-snippets` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the newly available 'CWP Snippets' section to begin adding and managing your custom code snippets.

== Documentation ==

For detailed instructions, tutorials, and references, please see the built-in documentation.

1.  **In-Plugin Help:** The full documentation is available in the "Help" tab within the plugin itself. Navigate to "CWP Snippets" -> "Help" in your WordPress admin dashboard.
2.  **Online Documentation:** For public access, the same documentation is available on our website at https://cwpsnippets.com/docs

== Frequently Asked Questions ==

= Can I use this plugin without FileMaker? =

While the plugin is designed for use with FileMaker, it can also be used to manage general code snippets in your WordPress site.

= Is there any limit to the number of snippets I can create? =

No, you can create an unlimited number of snippets depending on your needs.

== Screenshots ==

1. The main interface for managing snippets.
2. Code editor with syntax highlighting.
3. Settings for connecting to a FileMaker database.

== Changelog ==

= 1.6.9 =
* Fix: Made code changes to prepare for submission to WordPress Repository

= 1.6.8 =
* Enhancement: Updated template and sample snippets.
* Enhancement: Updated Demo Database.
* NOTE: The demo database has been updated. If you use it, please replace your hosted file with the new version from the plugin's 'assets/demo' directory.
* Enhancement: Added syntax highlighting (CodeMirror) to code examples in the plugin's internal documentation for improved readability.
* Enhancement: Expanded and updated internal documentation content.

= 1.6.7 =
* Feature - Added a 'No Cache' option for snippets to prevent page caching on a per-snippet basis.
* Fix - Text erorrs fixed in Demo instructions.
* Fix - Inmproved the display of Demo instructions.
* Fix - Set the critical error notice to dismiss when the offending snippet is resaved.

= 1.6.6 =
* Fix - Addressed a fatal error on new installations related to a missing PHP Parser library.
* Fix - Improved robustness of the editor's undo/redo history by capping its size to prevent browser storage from becoming full.
* Feature - Added Ctrl+S (Cmd+S on Mac) keyboard shortcut to save snippets directly from the editor.

= 1.6.5 =
* **Enhancement:** Added Documentation.

= 1.6.4 =
* **Major Feature: Runtime Error Protection.** Implemented a robust "Runtime Catch & Deactivate" system. If an active function snippet causes a fatal error, the plugin will now automatically deactivate it to prevent further crashes and display a clear notification in the admin area for review.
* **Major Feature: Interactive Import Dialog.** The import process has been completely overhauled. It now features an interactive dialog that shows all snippets in the file, identifies conflicts, and allows you to select exactly which snippets to add or update.
* **Major Feature: Bundled Snippet Update System.** Replaced the "Generate" buttons with a new "Reload" and "Update" system for bundled snippets. You will now be notified when new versions of the bundled Samples, Templates, etc., are available. All updates and reloads use the new interactive import dialog, giving you full control.
* **Enhancement:** Changed the default location for new snippets from 'frontend' to 'everywhere' for better out-of-the-box usability.
* **Enhancement:** The import process now correctly uses the snippet's type from the import file, rather than the type of the page you are currently viewing.
* **Fix:** Corrected various UI inconsistencies, including button styling and success message formatting.

= 1.6.3 =
* Added support for add-ons and an add-on coming soon page.

= 1.6.2 =
* Added robust PHP error and syntax handling by bundling Nikic's PHP-Parser Library.
* Added custom CWP Debug Log functionality.

= 1.6.1 =
* Fixed an Ajax handler bug.

= 1.5.9 =
* Improved error handling. Added Debug Log Viewer. Fixed importing where backslashes were being lost.

= 1.5.8 =
* Added the ability to run front-end snippets in edit mode in admin and added improved error handling for critical errors.

= 1.5.7 =
* Added support for add-on snippets to extend the functionality of the core plugin.

= 1.5.6 =
* Added License management, activation, and Transients Token Management.

= 1.5.4 =
* Added Pro Feature Lock.

= 1.5.3 =
* Fixed a bug with undo/redo functionality.

= 1.5.2 =
* Fixed a bug preventing updating functions.

= 1.5.1 =
* Added Functions, and Styles.

= 1.3.0 =
* Added attributes to shortcodes.

= 1.0.0 =
* Initial release.