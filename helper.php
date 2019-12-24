<?php

namespace JazzMan\Post;

use JazzMan\Pluralizer\Pluralizer;

/**
 * @param string $name
 *
 * @return string
 */
function cpt_get_human_friendly(string $name = '')
{
    return ucwords(strtolower(str_replace(['-', '_'], ' ', $name)));
}

/**
 * @param string $post_type
 * @param string $textdomain
 *
 * @return array
 */
function cpt_get_post_type_labels(string $post_type, $textdomain = 'cpt')
{
    $human_friendly = cpt_get_human_friendly($post_type);
    $singular       = Pluralizer::singular($human_friendly);
    $plural         = Pluralizer::plural($human_friendly);

    $labels = [
        'name'               => sprintf(__('%s', $textdomain), $plural),
        'singular_name'      => sprintf(__('%s', $textdomain), $singular),
        'menu_name'          => sprintf(__('%s', $textdomain), $plural),
        'all_items'          => sprintf(__('%s', $textdomain), $plural),
        'add_new'            => __('Add New', $textdomain),
        'add_new_item'       => sprintf(__('Add New %s', $textdomain), $singular),
        'edit_item'          => sprintf(__('Edit %s', $textdomain), $singular),
        'new_item'           => sprintf(__('New %s', $textdomain), $singular),
        'view_item'          => sprintf(__('View %s', $textdomain), $singular),
        'search_items'       => sprintf(__('Search %s', $textdomain), $plural),
        'not_found'          => sprintf(__('No %s found', $textdomain), $plural),
        'not_found_in_trash' => sprintf(__('No %s found in Trash', $textdomain), $plural),
        'parent_item_colon'  => sprintf(__('Parent %s:', $textdomain), $singular),
    ];

    return $labels;
}

/**
 * @param string $taxonomy_name
 * @param string $textdomain
 *
 * @return array
 */
function cpt_get_taxonomy_labels(string $taxonomy_name, $textdomain = 'cpt')
{
    $human_friendly = cpt_get_human_friendly($taxonomy_name);
    $singular       = Pluralizer::singular($human_friendly);
    $plural         = Pluralizer::plural($human_friendly);

    $labels = [
        'name'                       => sprintf(__('%s', $textdomain), $plural),
        'singular_name'              => sprintf(__('%s', $textdomain), $singular),
        'menu_name'                  => sprintf(__('%s', $textdomain), $plural),
        'all_items'                  => sprintf(__('All %s', $textdomain), $plural),
        'edit_item'                  => sprintf(__('Edit %s', $textdomain), $singular),
        'view_item'                  => sprintf(__('View %s', $textdomain), $singular),
        'update_item'                => sprintf(__('Update %s', $textdomain), $singular),
        'add_new_item'               => sprintf(__('Add New %s', $textdomain), $singular),
        'new_item_name'              => sprintf(__('New %s Name', $textdomain), $singular),
        'parent_item'                => sprintf(__('Parent %s', $textdomain), $plural),
        'parent_item_colon'          => sprintf(__('Parent %s:', $textdomain), $plural),
        'search_items'               => sprintf(__('Search %s', $textdomain), $plural),
        'popular_items'              => sprintf(__('Popular %s', $textdomain), $plural),
        'separate_items_with_commas' => sprintf(__('Seperate %s with commas', $textdomain), $plural),
        'add_or_remove_items'        => sprintf(__('Add or remove %s', $textdomain), $plural),
        'choose_from_most_used'      => sprintf(__('Choose from most used %s', $textdomain), $plural),
        'not_found'                  => sprintf(__('No %s found', $textdomain), $plural),
    ];

    return $labels;
}

/**
 * @param string $post_type
 *
 * @return bool|mixed
 */
function cpt_get_post_type_archive_post_id(string $post_type)
{
    global $wpdb;

    $query = $wpdb->prepare(<<<SQL
SELECT 
  ID 
FROM $wpdb->posts 
WHERE 
  post_status ='publish' 
  AND post_type = %s 
  AND post_name = %s 
LIMIT 1
SQL
        ,'hdptap_cpt_archive',$post_type);


    $post_type_archive_id = $wpdb->get_var($query);

    if ( $post_type_archive_id !== null) {
        return (int)$post_type_archive_id;
    }

    return false;
}

/**
 * @param string $post_type
 *
 * @return bool|string
 */
function cpt_get_post_type_archive_title(string $post_type)
{
    // get this post types archive page post id.
    $archive_page_id = cpt_get_post_type_archive_post_id($post_type);

    if ( ! empty($archive_page_id)) {
        return get_post($archive_page_id)->post_title;
    }

    return false;
}


/**
 * @param string $post_type
 *
 * @return bool|string
 */
function cpt_get_post_type_archive_content(string $post_type)
{
    // get this post types archive page post id.
    $archive_page_id = cpt_get_post_type_archive_post_id($post_type);

    if ( ! empty($archive_page_id)) {
        return get_post($archive_page_id)->post_content;
    }

    return false;

}
