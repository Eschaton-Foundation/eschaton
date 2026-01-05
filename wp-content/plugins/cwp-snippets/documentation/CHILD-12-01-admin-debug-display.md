# fmcwpShowResponse – Admin Debug Display

## `fmcwpShowResponse($data, $label = '', $args = [])`

A universal debug helper for displaying PHP arrays, objects, or any data in a readable, styled block in the WordPress admin. Used throughout the plugin for safe, non-intrusive debugging.

- **Purpose:**
  - Output any variable, array, or object in a collapsible, styled debug block.
  - Optionally add a label/title and control display options.

- **Parameters:**
  - `$data` (mixed): The data to display (array, object, string, etc.)
  - `$label` (string): Optional label or title for the debug block
  - `$args` (array): Optional display arguments (e.g., expanded/collapsed by default)

- **Usage Example:**
  ```php
  fmcwpShowResponse($my_array, 'My Debug Data');
  fmcwpShowResponse($result, 'API Response', ['expanded' => true]);
  ```

- **Best Practice:**
  - Use for admin/debug output only. Not for front-end or production display.
  - Safe to use anywhere in the admin, including AJAX responses and custom admin pages.

**Visibility:**
> This debug output is only visible to logged-in WordPress admins. It will never be shown to end users or visitors.
