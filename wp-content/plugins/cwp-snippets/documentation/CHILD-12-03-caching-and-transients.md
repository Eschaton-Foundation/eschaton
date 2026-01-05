# Caching & Transients

## `cwpCacheData($key, $data = null, $expire = 3600)`
Set or get cached data using WordPress transients.
- **Set cache:**
  ```php
  cwpCacheData('my_key', $data, 3600); // Cache for 1 hour
  ```
- **Get cache:**
  ```php
  $data = cwpCacheData('my_key');
  ```
- **Parameters:**
  - `$key` (string): Unique cache key
  - `$data` (mixed): Data to cache (if null, function returns cached value)
  - `$expire` (int): Expiry in seconds (default: 1 hour)

## `cwpClearCache($key)`
Clear cached data by key.
- **Usage:**
  ```php
  cwpClearCache('my_key');
  ```
- **Parameters:**
  - `$key` (string): Cache key to clear

## `cwp_cache_bust($url, $version = null)`
Append a cache-busting query string to asset URLs to force browsers to reload updated files.
- **Usage:**
  ```php
  // Using file modification time (recommended)
  $url = cwp_cache_bust(get_stylesheet_directory_uri() . '/style.css');
  
  // Using custom version
  $url = cwp_cache_bust(get_stylesheet_directory_uri() . '/script.js', '1.2.3');
  ```
- **Parameters:**
  - `$url` (string): The asset URL to modify
  - `$version` (string|int, optional): Version or timestamp. If null, uses file modification time or current timestamp
- **Returns:** Cache-busted URL string, or false if URL is invalid

---

## `cwpIsURLValid($data, $logging = false)`

**Purpose:** Quickly check whether cached FileMaker responses contain expired `Streaming_SSL` image URLs so you can decide whether to repopulate the cache *before* a page renders. This prevents broken images or missing assets on page load by enabling a fast pre-flight check of cached responses.

**How it works:**
- Accepts a string or a nested array (e.g., a FileMaker response) and recursively searches the values for the *first* occurrence of a value containing the substring `Streaming_SSL` (case-insensitive).
- If no Streaming link is found anywhere in the input, the function returns `true` (no Streaming links to check).
- If a Streaming_SSL value is found and it looks like an `http`/`https` URL, the function performs a short `HEAD` request (falls back to `GET` once if `HEAD` is blocked). It returns `true` for 2xx responses and `false` otherwise.
- **Important:** the function only validates the *first* Streaming_SSL URL found (keeps the check fast); it does not iterate and test every Streaming_SSL URL in an array.
- **Optional logging:** If `$logging` is `true`, failed Streaming_SSL URL checks are recorded to the CWP debug log using `cwpLog()` with the `error_issue` set to `cwp_image_invalid`. The log includes HTTP status code or connection error details when available.

**Typical usage (cache-check & repopulate):**
```php
$cache_key = 'fm_records';
$cached = cwpCacheData($cache_key);
// Pass true for $logging to record failed URL checks to the CWP debug log
if ($cached && cwpIsURLValid($cached, true)) {
    // Cached data is OK
    $data = $cached;
} else {
    // Cache missing or contains expired Streaming_SSL links — fetch fresh and re-cache
    $fresh = cwpFetchFromFileMaker(); // replace with your FileMaker fetch helper
    cwpCacheData($cache_key, $fresh, 3600);
    $data = $fresh;
}
```

**Quick single-URL check:**
```php
// Check a single URL string
if (!cwpIsURLValid('https://example.com/Streaming_SSL/abc.jpg')) {
    // URL invalid or expired — take corrective action
}

// With logging enabled
if (!cwpIsURLValid('https://example.com/Streaming_SSL/abc.jpg', true)) {
    // URL invalid or expired — take corrective action (and a log entry will be created)
}
```

**Parameters:**
- `$data` (string|array): FileMaker response array or URL string to inspect. The function will recurse arrays and test only the first Streaming_SSL value found.
- `$logging` (bool, optional): When `true`, failed URL checks are logged via `cwpLog()` to the CWP debug log (uses `cwp_image_invalid` as the category).

**Returns:** `bool` — `true` if no problematic Streaming_SSL URL found or the tested URL returns 2xx; `false` if the first Streaming_SSL URL is invalid.

**Notes:**
- Uses a short timeout and `sslverify => false` for resiliency.
- Designed as a lightweight boolean helper to help you decide whether to repopulate cached FileMaker responses before page load.

---

## REST API Endpoint: Clear Cache

### `POST /wp-json/cwp/v1/clear-cache`
Allows external apps (e.g., FileMaker) to clear a cache key via REST API.

- **Usage:**
  POST to `/wp-json/cwp/v1/clear-cache` with JSON body:
  ```json
  {
    "cache_key": "your_key",
    "token": "your_token" // optional, add your own auth logic
  }
  ```
- **Security:** Add your own token/auth logic in the permission_callback
- **Response:**
  - Success: `{ success: true, message: "Cache 'your_key' cleared." }`
  - Error: `{ success: false, message: "..." }`

---

## Clear All: REST flag & Admin AJAX

You can clear *all* CWP-prefixed transients either via the REST endpoint or via an admin AJAX action. Both call `cwpClearAllTransients()` and return the number of transients deleted.

- **REST (external/automation):**
  - Use the existing endpoint `POST /wp-json/cwp/v1/clear-cache` with either:
    - `{ "clear_all": true }` or
    - `{ "cache_key": "__all__" }`
  - The clear-all action is intentionally permissive by default; implementers can add their own permission or token checks in the REST `permission_callback` if desired. The endpoint will return:
    ```json
    { "success": true, "deleted": 12, "message": "Deleted 12 transients." }
    ```

- **Admin AJAX (admin UI):**
  - Endpoint: `admin-ajax.php` with `action=cwp_clear_all_transients`
  - Required POST fields: `nonce` (your `cwp_universal_nonce`) and `confirm=1` to avoid accidental clears.
  - Example JS (admin page):
    ```javascript
    jQuery.post(ajaxurl, {
      action: 'cwp_clear_all_transients',
      nonce: CWP.universalNonce,
      confirm: '1'
    }, function(resp){
      console.log(resp);
    });
    ```

**Security notes:**
- Both methods enforce admin capability for destructive `clear_all` actions. The REST route currently returns `true` for `permission_callback` (no token) but the clear-all path explicitly checks `current_user_can('manage_options')` before proceeding. Consider adding token/auth later for external automation.

---

## Transient Expiry Viewer

### `cwpShowTransients()`

**Purpose:**
- Displays all current CWP transients (cache items) and their expiry times.
- Only visible to users with `manage_options` capability (admins/webmasters).

**Usage:**
```php
cwpShowTransients();
```

**Security Note:**
- This function is for debugging and admin use only. It is not visible to regular users.
- The code does not reveal or hint at any sensitive or private transients.
