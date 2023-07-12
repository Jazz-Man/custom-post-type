<?php

use JazzMan\Pluralizer\Pluralizer;
use JazzMan\Post\ReusableBlocks;

if ( ! function_exists( 'app_get_post_type_labels' ) ) {
    /**
     * @param array<string,mixed> $options
     *
     * @return array<string,mixed>
     */
    function app_get_post_type_labels( string $post_type, array $options = [] ): array {
        $pluralizer = app_string_pluralizer( $post_type );

        $labels = [
            'name' => $pluralizer['plural'],
            'singular_name' => $pluralizer['singular'],
            'menu_name' => $pluralizer['plural'],
            'all_items' => $pluralizer['plural'],
            'add_new' => sprintf( 'Add New %s', $pluralizer['singular'] ),
            'add_new_item' => sprintf( 'Add New %s', $pluralizer['singular'] ),
            'edit_item' => sprintf( 'Edit %s', $pluralizer['singular'] ),
            'new_item' => sprintf( 'New %s', $pluralizer['singular'] ),
            'view_item' => sprintf( 'View %s', $pluralizer['singular'] ),
            'search_items' => sprintf( 'Search %s', $pluralizer['plural'] ),
            'not_found' => sprintf( 'No %s found', $pluralizer['plural'] ),
            'not_found_in_trash' => sprintf( 'No %s found in Trash', $pluralizer['plural'] ),
            'parent_item_colon' => sprintf( 'Parent %s:', $pluralizer['singular'] ),
            'items_list_navigation' => sprintf( '%s list navigation', $pluralizer['plural'] ),
            'items_list' => sprintf( '%s list', $pluralizer['plural'] ),
            'item_published' => sprintf( '%s published', $pluralizer['singular'] ),
            'item_published_privately' => sprintf( '%s published privately.', $pluralizer['singular'] ),
            'item_reverted_to_draft' => sprintf( '%s reverted to draft.', $pluralizer['singular'] ),
            'item_scheduled' => sprintf( '%s scheduled.', $pluralizer['singular'] ),
            'item_updated' => sprintf( '%s updated.', $pluralizer['singular'] ),
            'item_link' => sprintf( '%s Link.', $pluralizer['singular'] ),
            'item_link_description' => sprintf( 'A link to a %s.', $pluralizer['singular'] ),
        ];

        return wp_parse_args( $options, $labels );
    }
}

if ( ! function_exists( 'app_get_taxonomy_labels' ) ) {
    /**
     * @param array<string,string> $options
     *
     * @return array<string,mixed>
     */
    function app_get_taxonomy_labels( string $taxonomy, array $options = [] ): array {
        $pluralizer = app_string_pluralizer( $taxonomy );

        $labels = [
            'name' => $pluralizer['plural'],
            'singular_name' => $pluralizer['singular'],
            'menu_name' => $pluralizer['plural'],
            'all_items' => sprintf( 'All %s', $pluralizer['plural'] ),
            'edit_item' => sprintf( 'Edit %s', $pluralizer['singular'] ),
            'view_item' => sprintf( 'View %s', $pluralizer['singular'] ),
            'view_items' => sprintf( 'View %s', $pluralizer['plural'] ),
            'update_item' => sprintf( 'Update %s', $pluralizer['singular'] ),
            'add_new_item' => sprintf( 'Add New %s', $pluralizer['singular'] ),
            'new_item_name' => sprintf( 'New %s Name', $pluralizer['singular'] ),
            'parent_item' => sprintf( 'Parent %s', $pluralizer['plural'] ),
            'parent_item_colon' => sprintf( 'Parent %s:', $pluralizer['plural'] ),
            'search_items' => sprintf( 'Search %s', $pluralizer['plural'] ),
            'popular_items' => sprintf( 'Popular %s', $pluralizer['plural'] ),
            'separate_items_with_commas' => sprintf( 'Seperate %s with commas', $pluralizer['plural'] ),
            'add_or_remove_items' => sprintf( 'Add or remove %s', $pluralizer['plural'] ),
            'choose_from_most_used' => sprintf( 'Choose from most used %s', $pluralizer['plural'] ),
            'not_found' => sprintf( 'No %s found', $pluralizer['plural'] ),
        ];

        return wp_parse_args( $options, $labels );
    }
}

if ( ! function_exists( 'app_get_human_friendly' ) ) {
    function app_get_human_friendly( string $name ): string {
        $ucwords = static function ( string $string ): string {
            if ( function_exists( 'mb_convert_case' ) ) {
                return mb_convert_case( $string, MB_CASE_TITLE, 'UTF-8' );
            }

            return ucwords( $string );
        };

        $strtolower = static function ( string $string ): string {
            if ( function_exists( 'mb_strtolower' ) ) {
                return mb_strtolower( $string, 'UTF-8' );
            }

            return strtolower( $string );
        };

        return $ucwords( $strtolower( str_replace( [ '-', '_' ], ' ', $name ) ) );
    }
}

if ( ! function_exists( 'app_string_pluralizer' ) ) {
    /**
     * @return array{singular:string, plural:string}
     */
    function app_string_pluralizer( string $name ): array {
        $humanFriendly = app_get_human_friendly( $name );

        $singular = Pluralizer::singular( $humanFriendly );
        $plural = Pluralizer::plural( $humanFriendly );

        return [ 'singular' => $singular, 'plural' => $plural ];
    }
}

if ( ! function_exists( 'app_get_wp_block' ) ) {
    function app_get_wp_block( string $postName ): bool|WP_Post {
        $cacheKey = sprintf( 'wp_block_%s', $postName );

        /** @var false|\WP_Post $result */
        $result = wp_cache_get( $cacheKey, ReusableBlocks::CACHE_GROUP );

        if ( false === $result ) {
            /** @var \WP_Post[] $posts */
            $posts = get_posts( [
                'post_type' => 'wp_block',
                'numberposts' => 1,
                'name' => $postName,
            ] );

            if ( [] !== $posts ) {
                $result = reset( $posts );
                wp_cache_set( $cacheKey, $result, ReusableBlocks::CACHE_GROUP );
            }
        }

        return $result;
    }
}
