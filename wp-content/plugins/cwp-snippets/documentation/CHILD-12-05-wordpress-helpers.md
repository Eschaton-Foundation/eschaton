# WordPress Functions

## Universal Toggle Setting Callback

### `cwp_universal_toggle_setting_callback()`
Universal AJAX handler for admin toggle switches and settings updates. Handles all validation, nonce checking, and permission verification.

**Parameters (via POST/AJAX):**
- `action` (string): Always `'cwp_universal_toggle_setting'`
- `option_name` (string): WordPress option group name (e.g., `'cwp_authnet_api_keys'`)
- `setting_key` (string): Specific setting key within that option (e.g., `'api_mode'`)
- `setting_value` (string): Value to save
- `message` (string): User-friendly notification message
- `nonce` (string): Security nonce token

**Returns:**
- Success: `{'success': true, 'data': {'message': '...'}}`
- Error: `{'success': false, 'data': {'message': '...'}}`

**Security Features:**
- Nonce verification via `check_ajax_referer()`
- Capability check (`manage_options`)
- Input sanitization on all fields

## `cwp_safe_redirect($url, $status = 302)`
Safely redirect to a URL, even if headers have already been sent. **Critical for WordPress custom development environments** where traditional `wp_redirect()` fails due to output buffering and header conflicts.

**Why This Function is Essential:**
In WordPress custom development and snippet environments, `wp_redirect()` **rarely works** because:
- The page has already loaded by the time plugins/snippets execute
- Headers are sent early in the WordPress loading process
- Output buffering issues prevent proper header modification
- Content is already being output before redirect logic runs

**Usage:**
```php
// Basic redirect - works even after headers sent
cwp_safe_redirect(home_url('/thank-you'));

// Redirect with custom status code
cwp_safe_redirect('https://example.com', 301);
```

**Parameters:**
- `$url` (string): The URL to redirect to
- `$status` (int, optional): HTTP status code (default: 302)

**Fallback Behavior:**
- **Headers not sent:** Uses standard `wp_redirect()` and exits
- **Headers already sent:** Falls back to HTML meta refresh + JavaScript redirect:
  ```html
  <meta http-equiv="refresh" content="0;url=https://example.com">
  <script>window.location.href="https://example.com";</script>
  ```

**Returns:**
- `false` if URL is empty/invalid
- Does not return on successful redirect (exits execution)

**Security:**
- Automatically escapes URLs for HTML and JavaScript contexts
- Validates URL before redirecting