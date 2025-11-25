<?php 

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;


// *********************************************************************************************************************************
// Delete Preview Page

function fm_cwp_deactivate() {
    $preview_page_id = get_option('fm_cwp_preview_page_id');
    if ($preview_page_id) {
        wp_delete_post($preview_page_id, true); // Force delete
        delete_option('fm_cwp_preview_page_id');
    }
}