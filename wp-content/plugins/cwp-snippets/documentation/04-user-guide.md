# User Guide

This guide provides a comprehensive overview of the CWP Snippets admin interface and its features.

---

## The Admin Interface

The CWP Snippets functionality is managed through a main menu item and several sub-pages.

*   **Snippets:** This is the main page for the plugin, accessed directly from the WordPress admin menu. On this page, you can:
    *   View, filter, and search for all your existing snippets.
    *   Create new snippets using the "Add New" button.
    *   Import or export snippets using the controls at the top of the page.
    *   Perform bulk actions like activating, deactivating, or deleting.

*   **Settings:** A sub-page for configuring the connection to your **live, production** FileMaker database.

*   **Demo Setup:** A sub-page for entering the connection credentials for the included FileMaker demo database.

*   **Documentation:** A sub-page that displays this help documentation.

*   **Add-ons:** A sub-page to discover and manage extensions that add more functionality to CWP Snippets.

---

## Managing Snippets

Creating and managing snippets is the core of the plugin.

### Creating a Snippet

1.  **Choose Snippet Type:** On the main **Snippets** page, select the type of snippet you wish to create (e.g., "Snippet", "Function", "Style", "Script", "Template", or "Sample") by clicking its respective filter button.
2.  **Add New:** Click the "Add New" button. The new snippet's type will be pre-selected based on your choice in the previous step.
3.  **Title:** Give your snippet a descriptive name. This title, along with the snippet's type, will be used to automatically generate its unique shortcode.
4.  **Code Editor:** This is where you write your PHP, HTML, or JavaScript code. If your snippet includes CSS, click the "CSS" button to switch to the dedicated CSS editor.
5.  **Create Snippet:** Click the **Create Snippet** button to save your new snippet. If you are editing an existing snippet, this button will be labeled **Update Snippet**.

**Important Note on Shortcodes:** The shortcode for your snippet is automatically generated based on its type and title. If you change either the snippet's type or its title, the shortcode will also change. Ensure you update any pages or posts where you have used the old shortcode.

### The Snippet List

The main snippets page displays a list of all your snippets. The columns provide key information at a glance:

*   **Status:** Shows whether a snippet is active (green) or inactive (gray). Only active snippets will execute.
*   **Name:** The name you have given the snippet.
*   **Type:** The snippet's type (e.g., Snippet, Function, Style).
*   **Shortcode:** For snippet types that can be embedded, this column shows the shortcode to use.
*   **Modified:** The date the snippet was last modified.
*   **No Cache:** This toggle controls caching for the page where the snippet is used. When enabled (green), it will prevent server-side and browser caching. This is critical for snippets that display live data from a database (like FileMaker) to ensure visitors always see the most up-to-date information.
*   **Actions:** Provides quick links to Edit, Duplicate, Preview, or Delete the snippet.

---

## Snippet Types

CWP Snippets organizes code into four main types. For organizational purposes, the "Snippet" type is further broken down into three categories.

*   **Snippets:** This is the most common type for displaying content on your site. They are blocks of PHP and HTML code that execute when their corresponding shortcode is used.
    *   **Snippets (Category):** For your own custom code.
    *   **Templates (Category):** A developer's reference library of raw function calls to the FileMaker API.
    *   **Samples (Category):** Fully-formed, styled examples (like a Contact List) that demonstrate what the plugin can do.

*   **Functions (PHP) ( Pro ):** Use this for PHP code that should run globally on your site, similar to your theme's `functions.php` file. They are for hooks, filters, and defining globally available functions. They do not have shortcodes.

*   **Styles (CSS) ( Pro ):** For adding custom CSS to your site. The CSS is automatically applied globally, similar to your theme's `style.css` file.

*   **Scripts (JavaScript) ( Pro ):** For adding custom JavaScript to your site.

---

## Using Shortcodes

Shortcodes are the primary way to display the output of your `Snippet`, `Sample`, and `Template` type snippets.

*   **Automatic Generation:** A snippet's shortcode is automatically generated based on its type and title. For example, if your snippet's type is "Snippet" and its title is "Show Recent Posts", the shortcode will be `[cwp-snip-show-recent-posts]`. If the type were "Template", it would be `[cwp-tmpl-show-recent-posts]`.
*   **Finding the Shortcode:** The exact shortcode can be found in the read-only "Shortcode" field on the snippet's edit screen, or directly on the main Snippets list page.
*   **Usage:** Simply copy this shortcode and paste it into any page, post, or widget to display the snippet's output.
*   **Advanced Usage:** For details on how to pass data to your snippets using attributes, please see the **Shortcode Reference** guide.

---

## Importing and Exporting ( Pro )

Located under **Snippets > Import/Export**, this feature is useful for:

*   **Adding Add-ons:** Easily import new functionalities and features from the CWP Snippets Add-ons library at `cwpsnippets.com`.
*   **Backups:** Regularly export your snippets to keep them safe.
*   **Migration:** Move your snippets from a development site to a live site.
*   **Sharing:** Share your snippets with other users.

To export, select the snippets you want and click the "Export" button in the bulk actions menu. To import, choose the JSON file from your computer and click "Import Snippets".