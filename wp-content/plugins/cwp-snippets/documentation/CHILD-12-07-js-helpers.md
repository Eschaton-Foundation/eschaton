# JavaScript Helpers

## Floating Notification

### `cwp_show_floating_notification(message, isSuccess)`
Displays a floating notification in the bottom-right corner of the admin screen. Automatically dismisses after 3 seconds.

**Example:**
```javascript
cwp_show_floating_notification('Setting saved successfully!', true);
cwp_show_floating_notification('Failed to update settings', false);
```

---

## Universal Spinner Overlay

### `cwpShowSpinner(options)` / `cwpHideSpinner()`
Displays a universal pinwheel spinner overlay, useful for AJAX requests, form submissions, or any async UI event. The spinner is created and removed automatically—no markup needed.

**Options:**
- `message` (string): Text to display below the spinner (optional)
- `size` (number): Spinner size in pixels (optional, default: 48)
- `color` (string): Spinner color (border-top, optional, default: #007cba)
- `target` (string): CSS selector to overlay a specific element (optional; overlays page if omitted)

**Example:**
```javascript
cwpShowSpinner(); // Show default spinner overlay
cwpShowSpinner({ message: 'Loading...', size: 60, color: '#ff6600', target: '#myForm' });
cwpHideSpinner(); // Hide spinner overlay
```

---

## Universal JS Helper Functions

These JavaScript helpers are globally available and designed for quick, reusable UI and admin tasks. All functions use the `cwp` prefix to avoid conflicts.

**Available Helpers & Usage:**

```js
// Hide an element
cwpHideElement('.myClass');
// Toggle visibility
cwpToggleElement('#myDiv');
// Smooth scroll to an element
cwpScrollTo('#section2');
// Copy text to clipboard
cwpCopyToClipboard('Some text');
// Copy value/text from an element
cwpSetClipboardFromSelector('#myInput');
// Show a floating notification (uses your global CSS styles)
cwp_show_floating_notification('Saved!', true); // true=success, false=error
// Get a URL query parameter
var id = cwpGetQueryParam('id');
// Check if user is on mobile
if (cwpIsMobile()) { /* ... */ }
// Show a confirmation dialog
cwpConfirm('Are you sure?', function() { /* do something */ });
// Show/hide spinner overlay
cwpShowSpinner();
cwpShowSpinner({ message: 'Saving...', size: 50, color: '#CCC' });
cwpHideSpinner();
```
