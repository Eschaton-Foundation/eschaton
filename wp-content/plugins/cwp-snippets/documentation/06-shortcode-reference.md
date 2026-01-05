# Shortcode Reference

This section provides a detailed reference for using shortcodes in CWP Snippets.

---

### Dynamic Shortcodes

A unique shortcode tag is automatically generated for each snippet that needs one, based on its type and title.

**Creation:**

1.  When you create or edit a snippet (specifically 'Snippet', 'Template', or 'Sample' types), you will see a "Shortcode" field.
2.  The shortcode tag is automatically generated based on the snippet's **type** and **title**. For example, if your snippet's type is "Snippet" and its title is "My Contact Form", the shortcode might be `[cwp-snip-my-contact-form]`. If the type were "Template", it would be `[cwp-tmpl-my-contact-form]`.
3.  Save the snippet.

**Important Note:** The shortcode for your snippet is automatically generated based on its type and title. If you change either the snippet's type or its title, the shortcode will also change. Ensure you update any pages or posts where you have used the old shortcode.

The plugin will automatically register shortcodes like `[cwp-snip-my-contact-form]` or `[cwp-snip-show-latest-posts]` based on the snippet's type and title.

**Basic Usage:**

To display the output of your snippet, you simply place the shortcode you defined into any page, post, or widget area.

Example: `[cwp-snip-my-contact-form]`

**Advanced Usage: Passing Data with Attributes ( Pro )**

You can make your snippets more powerful by passing data to them directly from the shortcode using attributes.

Any attributes you add to the shortcode will be automatically converted into PHP variables that you can use inside your snippet's code.

**Important Note on Attributes:** WordPress automatically converts all attribute names to **lowercase**. For example, if you use an attribute `eventCode="FA2025"`, you must use the variable `$eventcode` (all lowercase) in your PHP code to access its value.

**Example:**

Let's say you have a shortcode `[cwp-snip-greet-user]` and you use it like this:

`[cwp-snip-greet-user name="Ron" location="Florida"]`

Inside the PHP code for that snippet, you will have access to two variables:

*   `$name` will have the value `"Ron"`.
*   `$location` will have the value `"Florida"`.

You could then write your snippet code like this:

```php
<?php
// The variables $name and $location are automatically created
// from the shortcode attributes.

if (isset($name) && isset($location)) {
    echo "Hello, " . esc_html($name) . " from " . esc_html($location) . "!";
} else {
    echo "Hello, world!";
}
?>
```

This allows you to create flexible, reusable snippets that can be customized wherever you place them.

---

## Nesting Shortcodes

Sometimes you need to include one CWP snippet within another snippet. For example, you might have a reusable "header" template that you want to include in multiple other templates. CWP Snippets provides a special function called `cwp_do_shortcode()` for this purpose.

### The Problem with WordPress's do_shortcode()

WordPress provides a built-in `do_shortcode()` function that you can use to execute shortcodes from within PHP code. However, it has a limitation:

**WordPress's `do_shortcode()` does NOT include the CSS** that is associated with CWP snippets. This means if you execute a shortcode that has custom styling, the CSS will be missing, and the content will appear unstyled.

**Example of the problem:**
```php
<?php
// This will output the HTML, but CSS styling will be missing!
echo do_shortcode('[cwp-tmpl-invoice]');
?>
```

### The Solution: cwp_do_shortcode()

CWP Snippets provides a special function called `cwp_do_shortcode()` that works just like `do_shortcode()`, but it **automatically includes all CSS** associated with the shortcode.

**Syntax:**
```php
cwp_do_shortcode($shortcode)
```

**Parameters:**
- `$shortcode` (string, required): The full shortcode string, including brackets. Example: `'[cwp-tmpl-invoice]'`

**Returns:**
- (string) The shortcode output with all CSS styling included

### Usage Examples

**Basic Usage:**
```php
<?php
// This will output the HTML WITH CSS styling applied!
echo cwp_do_shortcode('[cwp-tmpl-invoice]');
?>
```

**With Attributes:**
```php
<?php
// You can pass attributes just like with do_shortcode()
echo cwp_do_shortcode('[cwp-tmpl-invoice customer_id="123" type="standard"]');
?>
```

**Nesting Multiple Shortcodes:**
```php
<?php
// Include a header template
echo cwp_do_shortcode('[cwp-tmpl-header]');

// Include main content
echo cwp_do_shortcode('[cwp-tmpl-main-content]');

// Include a footer template
echo cwp_do_shortcode('[cwp-tmpl-footer]');
?>
```

**Storing Output in a Variable:**
```php
<?php
// Capture the output instead of displaying it immediately
$invoice_html = cwp_do_shortcode('[cwp-tmpl-invoice customer_id="456"]');

// Do something with the output
if (!empty($invoice_html)) {
    echo '<div class="invoice-container">' . $invoice_html . '</div>';
}
?>
```

### What CSS Does cwp_do_shortcode() Include?

When you use `cwp_do_shortcode()`, it automatically includes:

1. **The snippet's own CSS** - Any CSS defined in the target snippet's CSS field
2. **Global Style snippets** - All active "Style" type snippets (snippets with no shortcode that contain only CSS)

All CSS is automatically validated before being injected to ensure it contains valid CSS syntax.

### Key Features

✅ **Automatic CSS inclusion** - No need to manually manage CSS  
✅ **Supports all shortcode attributes** - Works exactly like `do_shortcode()`  
✅ **Safe CSS validation** - Only valid CSS is injected  
✅ **Nested shortcode support** - You can call `cwp_do_shortcode()` from within another CWP snippet  
✅ **Error handling** - Gracefully handles missing or invalid shortcodes  
✅ **Debug logging** - Errors are logged when `WP_DEBUG` is enabled  

### When to Use cwp_do_shortcode()

Use `cwp_do_shortcode()` whenever you need to:
- Include one CWP snippet from within another snippet
- Reuse styled content components
- Build modular snippet templates
- Create complex layouts by combining multiple snippets

### When to Use do_shortcode()

Use WordPress's built-in `do_shortcode()` when:
- You're executing shortcodes from non-CWP snippets
- The shortcode doesn't require CSS styling
- You're working with other WordPress plugin shortcodes
````