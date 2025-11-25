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