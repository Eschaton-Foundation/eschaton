# FileMaker Functions

## `cwpSendToFM($data, $layout, $field, $script = null, $fm_opts = [])`
Send any array/object as JSON to FileMaker, with optional script trigger. Designed for event-driven data dumps (e.g., after checkout, form submission, etc.).

- **Purpose:**
  - Quickly send data to FileMaker as a JSON string in a specified field
  - Optionally trigger a FileMaker script after record creation
  - Keeps integration simple and safe (not for arbitrary FM actions)

- **Parameters:**
  - `$data` (array|object): Data to send (will be JSON-encoded)
  - `$layout` (string): FileMaker layout to use
  - `$field` (string): FileMaker field to store JSON
  - `$script` (string|null): Optional FileMaker script to run
  - `$fm_opts` (array): Optional override for FM connection constants (host, db, user, pass)

- **Returns:**
  - Array with keys: `success` (bool), `message` (string), `response` (FM API response or script result)

- **Example:**
  ```php
  $result = cwpSendToFM($checkout_data, 'Web_Orders', 'json_field', 'AfterOrderScript');
  if (!$result['success']) {
      fmcwpShowResponse($result, 'FileMaker Error');
  }
  ```
