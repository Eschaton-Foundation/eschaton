# Troubleshooting

This guide helps you diagnose and solve common issues you might encounter with CWP Snippets.

---

### Issue: Snippet shortcode displays as plain text (e.g., `[cwp-snip-my-snippet]`)

This is the most common issue and usually has a simple cause.

*   **Cause 1: Snippet is not published.**
    *   **Solution:** Go to **Snippets > All Snippets**. Find your snippet and make sure its status is "Active", not "Inactive" or "Draft".

*   **Cause 2: Shortcode is inside a `<code>` or `<pre>` block.**
    *   **Solution:** WordPress does not process shortcodes inside these tags. Edit the page and ensure the shortcode is in a standard paragraph block.

*   **Cause 3: Conflict with another plugin.**
    *   **Solution:** Temporarily deactivate other plugins one by one to see if the issue is resolved. A plugin that alters how content is displayed might be interfering.

---

### Issue: FileMaker data is not appearing in your snippet

If your custom PHP snippet that uses the `fmCWP` class is not returning data from FileMaker, follow these steps to diagnose the problem.

*   **Step 1: Check Connection Settings**
    *   **For Demo Database:** Go to **Snippets > Demo Setup**. Double-check that your Host, Database, Username, and Password are correct for your hosted demo database.
    *   **For Live Database:** Go to **Snippets > Settings**. Double-check that your Host, Database, Username, and Password are correct for your live database.
    *   **Common Problem:** Incorrect credentials are a very common problem. Ensure there are no typos.

*   **Step 2: Verify FileMaker Server Data API Status**
    *   Log in to your FileMaker Server Admin Console.
    *   Ensure that the **FileMaker Data API** is enabled. This is a server-wide setting required for all communication.

*   **Step 3: Review Snippet Code**
    *   Open your snippet in the editor.
    *   Ensure you are using the correct constants for your connection (e.g., `FM_HOST_DEMO` for demo, `FM_HOST` for live).
    *   Verify that the `fmCWP` class is instantiated correctly: `$fm = new fmCWP(FM_HOST, FM_DATABASE, FM_LAYOUT, FM_USER, FM_PASSWORD);`
    *   Check that the layout name used in your `fmCWP` methods (e.g., `$fm->getRecords('YourLayoutName')`) is correct and that the fields you are trying to access exist on that layout.

*   **Step 4: Enable the Debug Log**
    *   If the above steps don't resolve the issue, the debug log is your most powerful tool.
    *   Go to **Snippets > Debug Log** and click the "Enable Debug Log" button.
    *   Reload the page where your snippet is supposed to display FileMaker data.
    *   Return to the Debug Log page and refresh it. It will now contain detailed information about the API calls being made to FileMaker, including the query sent and the response (or error) received from the server. This log will almost always reveal the source of the problem.

---

*This documentation can be expanded as more troubleshooting scenarios are identified.*