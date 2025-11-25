<?php
/******************************************************************************************
 * Copyright (C) Smackcoders. - All Rights Reserved under Smackcoders Proprietary License
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * You can contact Smackcoders at email address info@smackcoders.com.
 *******************************************************************************************/

namespace Smackcoders\FCSV;

if ( ! defined( 'ABSPATH' ) )
    exit; 

class SlimSeoImport {
    private static $slimseo_instance = null, $media_instance;

    public static function getInstance() {
        if (SlimSeoImport::$slimseo_instance == null) {
            SlimSeoImport::$slimseo_instance = new SlimSeoImport;
            SlimSeoImport::$media_instance = new MediaHandling;
        }
        return SlimSeoImport::$slimseo_instance;
    }

    function set_slimseo_values($header_array, $value_array, $map, $post_id, $type, $hash_key, $gmode, $templatekey, $line_number) {
        $helpers_instance = ImportHelpers::getInstance();
        $post_values = $helpers_instance->get_header_values($map, $header_array, $value_array);

        $this->slimseo_import_function($post_values, $type, $post_id, $header_array, $value_array, $hash_key, $gmode, $templatekey, $line_number);
    }


   function slimseo_import_function($data_array, $importas, $pID, $header_array, $value_array, $hash_key, $gmode, $templatekey, $line_number) {

    $createdFields = [];
    $media_instance = MediaHandling::getInstance();

    foreach ($data_array as $dkey => $dvalue) {
        $createdFields[] = $dkey;
    }


  $mapping = [
    'title'          => ['title', 'seo_title', 'SEO Title'],
    'description'    => ['description', 'meta_desc', 'Meta Description'],
    'canonical'      => ['canonical', 'Canonical URL'],
    'noindex'        => ['noindex', 'Noindex'],
    'facebook_image' => ['facebook_image', 'opengraph-image', 'Facebook Image'],
    'twitter_image'  => ['twitter_image', 'twitter-image', 'Twitter Image']
];


    $slimseo_fields = [];
    foreach ($mapping as $key => $aliases) {
        $value = '';
        foreach ($aliases as $alias) {
            if (isset($data_array[$alias]) && !empty($data_array[$alias])) {
                $value = urldecode($data_array[$alias]);
                    break;
            }
        }
        if ($key === 'noindex') {
            $slimseo_fields[$key] = !empty($value) ? (int)$value : 0;
        } else {
            $slimseo_fields[$key] = (string)$value;
        }
    }

    if (!empty($slimseo_fields['facebook_image'])) {
        $image_id = $media_instance->image_meta_table_entry(
            $line_number, '', $pID, 'opengraph-image', $slimseo_fields['facebook_image'],
            $hash_key, 'slimseo_opengraph', 'post', $templatekey, $gmode
        );
    }

    if (!empty($slimseo_fields['twitter_image'])) {
        $image_id = $media_instance->image_meta_table_entry(
            $line_number, '', $pID, 'twitter-image', $slimseo_fields['twitter_image'],
            $hash_key, 'slimseo_twitter', 'post', $templatekey, $gmode
        );
    }

    $existing = get_post_meta($pID, 'slim_seo', true);

    if (!is_array($existing)) $existing = [];
    $slimseo_fields = array_merge($existing, $slimseo_fields);

    update_post_meta($pID, 'slim_seo', $slimseo_fields);

    return $createdFields;
}


}
