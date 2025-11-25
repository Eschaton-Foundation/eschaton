<?php
/**
 * WP Ultimate Exporter plugin file.
 *
 * Copyright (C) 2010-2020, Smackcoders Inc - info@smackcoders.com
 */
namespace Smackcoders\SMEXP;

use Smackcoders\WCSV\WC_Coupon;

if (! defined('ABSPATH'))
    exit; // Exit if accessed directly

    class JetReviewsExport
{
    protected static $instance = null, $mapping_instance, $export_handler, $export_instance, $post_export;
    public $totalRowCount;
    public $plugin;

    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
            JetReviewsExport::$export_instance = ExportExtension::getInstance();
            JetReviewsExport::$post_export = PostExport::getInstance();
        }
        return self::$instance;
    }

    /**
     * JetReviewsExport constructor.
     */
    public function __construct()
    {
        $this->plugin = Plugin::getInstance();
    }

    /**
     * Fetch JetReviews data for export
     * @param $reviewId
     */

     public function getJetReviewsData($id) {
        global $wpdb;
        // Retrieve review by ID
        $review = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}jet_reviews WHERE id = %d", $id),
            ARRAY_A

        );
        if (!empty($review)) {
            

            // Populate data for the successful query
        JetReviewsExport::$export_instance->data[$id]['id'] = $review['id'] ?? '';        JetReviewsExport::$export_instance->data[$id]['post_id'] = $review['post_id'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['source'] = $review['source'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['post_type'] = $review['post_type'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['type_slug'] = $review['type_slug'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['author'] = $review['author'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['date'] = date('Y-m-d H:i:s', strtotime($review['date'])) ?? '';
        JetReviewsExport::$export_instance->data[$id]['title'] = $review['title'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['content'] = $review['content'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['rating_data'] = $review['rating_data'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['rating'] = $review['rating'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['likes'] = $review['likes'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['dislikes'] = $review['dislikes'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['approved'] = $review['approved'] ?? '';
        JetReviewsExport::$export_instance->data[$id]['pinned'] = $review['pinned'] ?? '';
        }
    }
}
