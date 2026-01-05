# Universal Error Logging Helper

## `cwpLog($error_issue, $error, $snippet_name = '', $snippet_id = 0, $error_line = 0)`

Log errors, warnings, or info to the CWP error log table (Pro) or the WordPress error log (Free).

- **Purpose:**
  - Simple, unified logging for all plugin errors and warnings.
  - Pro version logs to a custom DB table; Free version logs to PHP error log.

- **Parameters:**
  - `$error_issue` (string): Short category/slug for the error (required)
  - `$error` (string): Main error message/details (required)
  - `$snippet_name` (string): Name of the snippet (optional)
  - `$snippet_id` (int): ID of the snippet (optional)
  - `$error_line` (int): Line number (optional)

- **Returns:**
  - `true` if logged successfully, `false` otherwise

- **Example:**
  ```php
  cwpLog('api_error', 'Failed to connect to FileMaker API');
  cwpLog('validation', 'Missing required field', 'My Snippet', 123, 45);
  ```

- **Notes:**
  - If Pro is active and custom logging is enabled, logs to the DB table.
  - Otherwise, logs to the PHP error log.
