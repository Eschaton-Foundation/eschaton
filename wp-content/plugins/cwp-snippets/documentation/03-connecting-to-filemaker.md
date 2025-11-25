# Guide: Getting Started with FileMaker Integration

This guide provides a complete walkthrough for connecting the CWP Snippets plugin to a FileMaker database, using the included demo file as a starting point.

---

### Section 1: The Demo Database & Server Setup

The plugin comes with a FileMaker demo file that is used by the included `Sample` and `Template` snippets. This allows you to test the connection and see working examples out of the box.

1.  **Get the Demo File:** Navigate to **Snippets > Demo Setup** in your WordPress admin. This page contains links to download the `CWP Snippets.fmp12` demo file and view its default login credentials.
2.  **Host the Database on FileMaker Server:** For the plugin to connect, you must upload the demo file to your FileMaker Server.
3.  **Enable the Data API:** Log in to your FileMaker Server Admin Console and ensure that the **FileMaker Data API** is enabled. This is required for all communication with the plugin.

---

### Section 2: Configuring the Demo Connection

Once the demo database is hosted, you need to tell the plugin how to connect to it.

1.  **Navigate to Demo Setup:** In your WordPress admin dashboard, go to **Snippets > Demo Setup**.
2.  **Enter Credentials:** You will see fields for Host, Database, Username, and Password.
3.  **Fill and Save:** Enter the connection details for your hosted demo database and save the settings.
4.  **Confirm Connection:** The plugin will immediately test the connection. You should see a success message confirming it is working. If you see an error, double-check your credentials and server settings before proceeding.

---

### Section 3: Understanding `Templates` (The Developer Library)

The `Template` snippets are your direct access to the FileMaker Data API. Think of them as a reference library for developers.

*   **What they are:** Each `Template` corresponds to a single function in the plugin's FileMaker integration class (e.g., `getRecords`, `createRecord`, `editRecord`). They are simple, raw calls to the API.
*   **How to use them:** Navigate to **Snippets > All Snippets** and select the "Template" filter. Choose a template like `getRecords` and click **Preview**.
*   **What you will see:** The preview will show a "Success!!" message and the raw, unformatted array of data that FileMaker returns. This is extremely useful for developers to see the exact field names and data structure available from the database.

---

### Section 4: Understanding `Samples` (The Working Examples)

The `Sample` snippets are fully-functional examples that show what you can build with the plugin. They take the raw data (like that seen in the `Templates`) and turn it into a user-facing application.

*   **What they are:** `Samples` are complete pages with PHP logic, HTML structure, and CSS styling. The included samples (`Contact List`, `Contact Details`, `Contact Add Edit`) work together to form a mini-application.
*   **How to use them:** Navigate to **Snippets > All Snippets** and select the "Sample" filter. Choose the `Contact List` sample and click **Preview**.
*   **What you will see:** Instead of a raw data array, you will see a fully styled webpage displaying a list of contacts from the demo database, complete with links for viewing details or adding new records. The `Samples` are the best way to understand the power of the plugin in a real-world context.

---

### Section 5: Connecting to Your Live Database

When you are ready to work with your own production FileMaker database, follow these steps:

1.  **Configure Live Settings:** Navigate to **Snippets > Settings**. This page is for your live, production-ready credentials. Fill in the Host, Database, Username, and Password for your own database and save.
2.  **Modify Your Snippet:** To switch any snippet from the demo to your live database, you simply change the constants used to connect. Open the snippet and find the connection line.

    *   **Change From (Demo):**
        `$fm = new fmCWP(FM_HOST_DEMO, FM_DATABASE_DEMO, FM_LAYOUT_DEMO, FM_USER_DEMO, FM_PASSWORD_DEMO);`

    *   **Change To (Live):**
        `$fm = new fmCWP(FM_HOST, FM_DATABASE, FM_LAYOUT, FM_USER, FM_PASSWORD);`

By removing the `_DEMO` suffix from the constants, the snippet will now use the credentials you saved on the main **Settings** page. It's recommended to use the `Template` snippets as a starting point for your own code by copying them to the `Snippet` category and then changing the connection constants.
