<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Callback function to display the content of the Add-ons page.
 */
function cwp_snippets_addons_page_html() {
    // Enqueue Font Awesome for icons
    if (function_exists('enqueue_font_awesome')) {
        enqueue_font_awesome();
    }

    fmcwp_header();
    ?>
    <div class="cwp-addons-page-wrapper">
        <div class="cwp-addons-hero">
            <h1><?php esc_html_e( 'Unlock More Power with Add-ons & Snippets', 'cwp-snippets' ); ?></h1>
            <p class="cwp-addons-subtitle"><?php esc_html_e( 'Exciting new libraries are coming soon to supercharge your workflow!', 'cwp-snippets' ); ?></p>
        </div>

        <div class="cwp-addons-features-grid">
            <div class="cwp-addons-feature-card">
                <div class="cwp-addons-card-icon">
                    <i class="fas fa-code"></i>
                </div>
                <h2><?php esc_html_e( 'Snippet Library', 'cwp-snippets' ); ?></h2>
                <p><?php esc_html_e( 'Access a curated collection of pre-written code snippets. From simple UI enhancements to complex backend logic, import them directly into your site and save hours of development time.', 'cwp-snippets' ); ?></p>
            </div>

            <div class="cwp-addons-feature-card">
                <div class="cwp-addons-card-icon">
                    <i class="fas fa-puzzle-piece"></i>
                </div>
                <h2><?php esc_html_e( 'Add-on Library', 'cwp-snippets' ); ?></h2>
                <p><?php esc_html_e( 'Discover powerful, ready-to-use add-ons that extend the core functionality of CWP Snippets. Add new features like payment gateways, advanced forms, and more with just a few clicks.', 'cwp-snippets' ); ?></p>
            </div>
        </div>

        <div class="cwp-addons-stay-tuned">
            <h3><?php esc_html_e( 'Stay Tuned!', 'cwp-snippets' ); ?></h3>
            <p><?php esc_html_e( 'We\'re hard at work building these libraries. Keep an eye on this page for updates and new releases.', 'cwp-snippets' ); ?></p>
        </div>
    </div>
    <?php
    fmcwp_footer();
}