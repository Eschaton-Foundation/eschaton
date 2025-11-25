# Core Concepts

Understanding these core concepts is key to using CWP Snippets effectively, especially when integrating with FileMaker.

---

## Snippet Scope & Execution

Not all snippets run in the same way or at the same time. The **Snippet Type** you choose determines how your code is handled by WordPress and the plugin.

*   **Snippets:** These are the most common type for displaying content on your site. They are blocks of PHP and HTML code that execute when their corresponding shortcode is used. They are ideal for generating dynamic content directly within your WordPress pages or posts.

*   **Templates (Category):** These are a developer's reference library of raw function calls to the FileMaker Data API. They are PHP code snippets designed to demonstrate how to interact directly with FileMaker (e.g., `getRecords`, `createRecord`). Previewing a Template shows the raw data returned from FileMaker.

*   **Samples (Category):** These are fully-formed, working examples that demonstrate how to build complete, styled applications using the plugin. They combine PHP logic, HTML structure, and CSS styling to present FileMaker data in a user-friendly way. Previewing a Sample shows a complete webpage.

*   **Functions (PHP) ( Pro ):** This code is executed on the server during the WordPress page load process. It runs within the main WordPress application scope, meaning you can use any WordPress function (like `get_current_user()`) or hook into any action or filter. This is powerful but requires valid PHP. A fatal error in a PHP snippet can bring down your site.

*   **Styles (CSS) ( Pro ):** The code you write here is saved as a CSS stylesheet. The plugin automatically includes it in the HTML `<head>` of your site on every page load, behaving just like your theme's `style.css` file.

*   **Scripts (JavaScript) ( Pro ):** This code is executed in the user's web browser after the page has loaded. It is sandboxed from the server and cannot directly access PHP or WordPress functions. Its purpose is to make your web pages interactive.

---

## User Session Identification (for Developers)

The plugin provides a dedicated cookie, `cwp_session_id`, for developers to use as a unique session identifier in their custom snippets.

*   **Purpose:** `cwp_session_id` offers a stable, unique identifier for the current user's browser session, which can be leveraged in your custom PHP snippets for various purposes, such as tracking user activity or personalizing content.
*   **Generation:** This ID is a UUID v4 (Universally Unique Identifier) generated using `wp_generate_uuid4()`. It is only set if the user's browser accepts cookies. There is no fallback mechanism if cookies are disabled; in such cases, the `cwp_session_id` cookie will simply not be present.
*   **Accessing the ID:** You can access this session ID in your PHP snippets using the standard `$_COOKIE` superglobal:
    ```php
    <?php
    if (isset($_COOKIE['cwp_session_id'])) {
        $user_session_id = sanitize_text_field(wp_unslash($_COOKIE['cwp_session_id']));
        echo "Your unique session ID is: " . esc_html($user_session_id);
    } else {
        echo "Session ID not available (cookies may be disabled).";
    }
    ?>
    ```

## FileMaker Integration Overview

CWP Snippets integrates with FileMaker through the `fmCWP` PHP class. This class provides methods for interacting with the FileMaker Data API (e.g., `getRecord`, `findRecords`, `createRecord`).

*   **Configuration:** Connection details for your FileMaker database are configured in the WordPress admin under **Snippets > Demo Setup** (for the included demo database) and **Snippets > Settings** (for your live database).
*   **Usage in Snippets:** You instantiate the `fmCWP` class within your PHP snippets, passing it the connection constants. You then call its methods to perform operations on your FileMaker database.
*   **Data Handling:** The data returned from FileMaker is available as PHP arrays within your snippet, allowing you to process, format, and display it as needed using standard PHP and HTML.