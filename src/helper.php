<?php

namespace JazzMan\Post;

use JazzMan\Pluralizer\Pluralizer;

/**
 * @param  string  $post_type
 * @param  array  $options
 *
 * @return array
 */
function cpt_get_post_type_labels(string $post_type, $options = []): array
{
    $human_friendly = app_get_human_friendly($post_type);
    $singular = Pluralizer::singular($human_friendly);
    $plural = Pluralizer::plural($human_friendly);

    $labels = [
        'name' => $plural,
        'singular_name' => $singular,
        'menu_name' => $plural,
        'all_items' => $plural,
        'add_new' => sprintf('Add New %s', $singular),
        'add_new_item' => sprintf('Add New %s', $singular),
        'edit_item' => sprintf('Edit %s', $singular),
        'new_item' => sprintf('New %s', $singular),
        'view_item' => sprintf('View %s', $singular),
        'search_items' => sprintf('Search %s', $plural),
        'not_found' => sprintf('No %s found', $plural),
        'not_found_in_trash' => sprintf('No %s found in Trash', $plural),
        'parent_item_colon' => sprintf('Parent %s:', $singular),
        'items_list_navigation' => sprintf('%s list navigation', $plural),
        'items_list' => sprintf('%s list', $plural),
        'item_published' => sprintf('%s published', $singular),
        'item_published_privately' => sprintf('%s published privately.', $singular),
        'item_reverted_to_draft' => sprintf('%s reverted to draft.', $singular),
        'item_scheduled' => sprintf('%s scheduled.', $singular),
        'item_updated' => sprintf('%s updated.', $singular),
        'item_link' => sprintf('%s Link.', $singular),
        'item_link_description' => sprintf('A link to a %s.', $singular),
    ];

    return wp_parse_args($options, $labels);
}

/**
 * @param  string  $taxonomy_name
 * @param  array  $options
 *
 * @return array
 */
function cpt_get_taxonomy_labels(string $taxonomy_name, array $options = []): array
{
    $human_friendly = app_get_human_friendly($taxonomy_name);
    $singular = Pluralizer::singular($human_friendly);
    $plural = Pluralizer::plural($human_friendly);

    $labels = [
        'name' => $plural,
        'singular_name' => $singular,
        'menu_name' => $plural,
        'all_items' => sprintf('All %s', $plural),
        'edit_item' => sprintf('Edit %s', $singular),
        'view_item' => sprintf('View %s', $singular),
        'view_items' => sprintf('View %s', $plural),
        'update_item' => sprintf('Update %s', $singular),
        'add_new_item' => sprintf('Add New %s', $singular),
        'new_item_name' => sprintf('New %s Name', $singular),
        'parent_item' => sprintf('Parent %s', $plural),
        'parent_item_colon' => sprintf('Parent %s:', $plural),
        'search_items' => sprintf('Search %s', $plural),
        'popular_items' => sprintf('Popular %s', $plural),
        'separate_items_with_commas' => sprintf('Seperate %s with commas', $plural),
        'add_or_remove_items' => sprintf('Add or remove %s', $plural),
        'choose_from_most_used' => sprintf('Choose from most used %s', $plural),
        'not_found' => sprintf('No %s found', $plural),
    ];

    return wp_parse_args($options, $labels);
}

/**
 * @param  string  $post_type
 * @return bool|mixed
 */
function cpt_get_post_type_archive_post_id(string $post_type)
{
    global $wpdb;

    $post_type_archive_id = $wpdb->get_var($wpdb->prepare(<<<SQL
        SELECT 
          ID 
        FROM {$wpdb->posts} 
        WHERE 
          post_status ='publish' 
          AND post_type = %s 
          AND post_name = %s 
        LIMIT 1
SQL
        , 'hdptap_cpt_archive', $post_type));

    if (null !== $post_type_archive_id) {
        return (int) $post_type_archive_id;
    }

    return false;
}

/**
 * @param  string  $post_type
 * @return bool|string
 */
function cpt_get_post_type_archive_title(string $post_type)
{
    // get this post types archive page post id.
    $archive_page_id = cpt_get_post_type_archive_post_id($post_type);

    if (!empty($archive_page_id)) {
        return get_post($archive_page_id)->post_title;
    }

    return false;
}

/**
 * @param  string  $post_type
 * @return bool|string
 */
function cpt_get_post_type_archive_content(string $post_type)
{
    // get this post types archive page post id.
    $archive_page_id = cpt_get_post_type_archive_post_id($post_type);

    if (!empty($archive_page_id)) {
        return get_post($archive_page_id)->post_content;
    }

    return false;
}
