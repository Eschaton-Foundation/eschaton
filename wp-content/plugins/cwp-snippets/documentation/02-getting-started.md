# Getting Started

This guide will walk you through the process of installing and activating CWP Snippets, and creating your first snippet.

## Installation and Activation

1.  **Download:** Download the plugin zip file from the cwpsnippets.com download page.
2.  **Upload:** In your WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3.  **Select File:** Choose the downloaded zip file and click **Install Now**.
4.  **Activate:** Once installed, click the **Activate Plugin** button.

Upon activation, a new "Snippets" menu will appear in your WordPress admin sidebar.

## Your First Snippet

Let's create a simple "Hello World" snippet to see how easy it is to get started.

1.  **Navigate:** Go to **Snippets > Add New** in your WordPress admin.
2.  **Title:** Enter a title for your snippet. Let's use `Hello World`. The plugin will use this title to generate the shortcode.
3.  **Code:** In the code editor, type the following PHP code:
    ```php
    <?php
    echo "Hello, World!";
    ?>
    ```
4.  **Snippet Type:** For this example, set the type to **Snippet**.
5.  **Create Snippet:** Click the **Create Snippet** button to save it.

After saving, the page will reload in edit mode. Now you can preview your work or use the shortcode.

### Previewing Your Snippet

Before using a shortcode, it's a good practice to preview it.

1.  **Find the Preview Button:** On the snippet edit screen, click the "Preview" button.
2.  **Review Output:** A new browser tab will open showing the raw output of your snippet. This is a safe way to test your code.
3.  **Admin-Only:** Remember, this preview is only visible to administrators. It is a development tool and will not be visible to your site visitors.

### Using the Shortcode

Once you are happy with the preview, you can place your snippet on your site.

1.  **Find the Shortcode:** On the snippet edit screen, you will see a read-only "Shortcode" field. For a title of "Hello World" and type "Snippet", it will be `[cwp-snip-hello-world]`.
2.  **Copy Shortcode:** Copy this value.
3.  **Edit Page:** Go to the page or post where you want to display the snippet.
4.  **Paste Shortcode:** Paste the copied shortcode into the content editor.
5.  **Update & View:** Save your changes and view the page. You should see "Hello, World!" displayed.

Congratulations! You've installed CWP Snippets and created your first snippet. In the next section, we'll explore the plugin's features in more detail.
