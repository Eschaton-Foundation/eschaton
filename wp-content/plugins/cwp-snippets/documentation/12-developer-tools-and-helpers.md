# Developer Tools & Helpers: Overview

A standardized library of reusable utility functions for the **CWP Snippets WordPress Plugin**. These helpers ensure consistency, reduce code duplication, and provide developers with battle-tested patterns for common tasks.

---

## Overview

The CWP Snippets plugin allows users to inject custom code into their WordPress site via shortcodes and global enqueuing while maintaining organization and clean separation of concerns. This helper library provides:

- **Universal AJAX handlers** for admin interactions
- **Consistent notification/feedback systems**
- **Standardized logging** to custom debug logs
- **Data caching & transient management**
- **Debug utilities** for development
- **FileMaker integration helpers**

All functions are:
- ✅ Wrapped with `if (!function_exists)` guards to prevent conflicts
- ✅ Prefixed with `.cwp-` (CSS) or `cwp_` (PHP/JS) for clear namespace
- ✅ Globally accessible (no file enqueuing required)
- ✅ Production-ready and fully documented
