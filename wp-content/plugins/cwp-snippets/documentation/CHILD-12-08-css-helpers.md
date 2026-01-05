# CSS Helpers

## Floating Notification Container

### `.cwp-floating-notification`
Styles the floating notification container for AJAX/admin notifications.
- Fixed position (bottom-right)
- White text, blue background (default)
- Padding, border-radius, drop shadow
- Smooth slide-in animation (`@keyframes cwp-slideIn`)
- Max width: 300px
- Z-index: 9999

---

## Responsive Utility Classes

Quickly show/hide elements on mobile or desktop devices.

### `.cwp-hide-mobile`
Hides the element on screens ≤ 767px (mobile). Shows on desktop.

### `.cwp-show-desktop`
Shows the element only on desktop (> 767px). Hides on mobile.

### `.cwp-show-mobile`
Shows the element only on mobile (≤ 767px). Hides on desktop.

---

**Usage Example:**

```html
<div class="cwp-floating-notification">Saved!</div>
<div class="cwp-hide-mobile">Desktop only</div>
<div class="cwp-show-mobile">Mobile only</div>
```

See `/admin/css/universal-helper.css` for full details and more helper classes.
