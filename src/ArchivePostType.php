<?php

namespace JazzMan\Post;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Post;
use WP_Post_Type;
use WP_Screen;

class ArchivePostType implements AutoloadInterface {

    /**
     * @var string
     */
    private const ARCHIVE_POST_TYPE = 'hdptap_cpt_archive';

    /**
     * @var array<array-key|string,WP_Post>
     */
    private static array $store = [];

    public function load(): void {
        add_action( 'init', [ self::class, 'registerCptArchivePostType' ] );

        add_action( 'registered_post_type', [ self::class, 'createArchivePages' ], 10, 2 );

        add_filter( 'parent_file', [ self::class, 'adminMenuCorrection' ] );

        add_filter( 'pre_wp_unique_post_slug', [ self::class, 'fixArchivePostTypeSlug' ], 10, 5 );

        add_action( 'admin_menu', [ self::class, 'addAdminMenuArchivePages' ], 99 );

        add_filter( 'post_type_archive_title', [ self::class, 'archiveTitle' ], 10, 2 );

        add_filter( 'get_the_post_type_description', [ self::class, 'archiveDescription' ], 10, 2 );
    }

    public static function registerCptArchivePostType(): void {
        if ( ! post_type_exists( self::ARCHIVE_POST_TYPE ) ) {
            /**
             * Lets register the conditions post type
             * post type name is docp_condition.
             */
            $labels = app_get_post_type_labels( 'Archive Pages' );

            /** @var string[] $supports */
            $supports = (array) apply_filters(
                'hdptap_cpt_archive_supports',
                [
                    'title',
                    'editor',
                    'thumbnail',
                ]
            );

            register_post_type(
                self::ARCHIVE_POST_TYPE,
                [
                    'description' => 'Archive posts associated with each post type.',
                    'public' => false,
                    'show_in_nav_menus' => false,
                    'show_in_admin_bar' => false,
                    'exclude_from_search' => true,
                    'show_ui' => true,
                    'show_in_menu' => true,
                    'can_export' => true,
                    'delete_with_user' => false,
                    'hierarchical' => false,
                    'has_archive' => false,
                    'menu_icon' => 'dashicons-media-text',
                    'query_var' => 'hdptap_cpt_archive',
                    'menu_position' => 26,
                    'show_in_rest' => true,
                    'labels' => $labels,
                    'supports' => $supports,
                ]
            );
        }
    }

    public static function fixArchivePostTypeSlug( ?string $original, string $slug, int $postId, string $postStatus, string $postType ): ?string {
        if ( self::ARCHIVE_POST_TYPE === $postType ) {
            return $slug;
        }

        return $original;
    }

    public static function createArchivePages( string $postType, WP_Post_Type $wpPostType ): void {
        // if this is the archive pages post type - do nothing.
        // if this post type is not supposed to support an archive - do nothing.
        if ( self::ARCHIVE_POST_TYPE === $postType ) {
            return;
        }

        if ( empty( $wpPostType->has_archive ) ) {
            return;
        }

        if ( ! ( property_exists( $wpPostType, 'add_archive_page' ) && null !== $wpPostType->add_archive_page ) ) {
            return;
        }

        if ( ! is_admin() ) {
            return;
        }

        if ( $wpPostType->add_archive_page ) {
            $archivePost = self::getPostTypeArchive( $postType );

            if ( ! $archivePost instanceof WP_Post ) {
                $postarr = [
                    'post_type' => self::ARCHIVE_POST_TYPE,
                    'post_title' => $wpPostType->labels->name,
                    'post_status' => 'publish',
                    'post_name' => $postType,
                ];

                wp_insert_post( $postarr, true );
            }
        }
    }

    public static function adminMenuCorrection( string $parentFile = '' ): string {
        global $current_screen;

        if ( ! $current_screen instanceof WP_Screen ) {
            return $parentFile;
        }

        /** @var int|null $postId */
        $postId = filter_input( INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT );
        // if this is a post edit screen for the archive page post type.
        if ( ! empty( $postId ) && 'post' === $current_screen->base && self::ARCHIVE_POST_TYPE === $current_screen->post_type ) {
            /** @var WP_Post|null $post */
            $post = get_post( $postId );

            // if we have an archive post type returned.
            if ( ! empty( $post ) ) {
                // set the parent file to the archive post type.
                $parentFile = sprintf( 'edit.php?post_type=%s', esc_attr( $post->post_name ) );
            }
        }

        return $parentFile;
    }

    public static function addAdminMenuArchivePages(): void {
        $post_type_object = get_post_type_object( self::ARCHIVE_POST_TYPE );

        if ( $post_type_object instanceof WP_Post_Type ) {
            /** @var WP_Post[] $post_types */
            $post_types = get_posts(
                [
                    'post_type' => self::ARCHIVE_POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                ]
            );

            foreach ( $post_types as $post_type ) {
                // add the menu item for this post type.
                add_submenu_page(
                    sprintf( 'edit.php?post_type=%s', esc_attr( $post_type->post_name ) ),
                    'Archive Page',
                    'Archive Page',
                    'edit_posts',
                    sprintf( sprintf( '%s&action=edit', $post_type_object->_edit_link ), $post_type->ID ),
                    false
                );
            }
        }
    }

    public static function archiveTitle( string $title, string $postType ): string {
        $object = get_post_type_object( $postType );

        if ( empty( $object->has_archive ) ) {
            return $title;
        }

        if ( ! ( property_exists( $object, 'add_archive_page' ) && null !== $object->add_archive_page ) ) {
            return $title;
        }

        if ( $object->add_archive_page ) {
            $archivePost = self::getPostTypeArchive( $postType );

            if ( $archivePost instanceof WP_Post ) {
                return (string) apply_filters( 'the_title', $archivePost->post_title );
            }

            return $title;
        }

        return $title;
    }

    public static function archiveDescription( string $description, WP_Post_Type $wpPostType ): string {
        if ( empty( $wpPostType->has_archive ) ) {
            return $description;
        }

        if ( ! ( property_exists( $wpPostType, 'add_archive_page' ) && null !== $wpPostType->add_archive_page ) ) {
            return $description;
        }

        if ( $wpPostType->add_archive_page ) {
            $archivePost = self::getPostTypeArchive( $wpPostType->name );

            if ( $archivePost instanceof WP_Post ) {
                return (string) apply_filters( 'the_content', $archivePost->post_content );
            }

            return $description;
        }

        return $description;
    }

    private static function getPostTypeArchive( string $postType ): ?WP_Post {
        global $wpdb;

        if ( empty( self::$store[ $postType ] ) ) {
            /** @var string|void $prepare */
            $prepare = $wpdb->prepare( <<<SQL
                        SELECT
                          ID
                        FROM {$wpdb->posts}
                        WHERE
                          post_status ='publish'
                          AND post_type = %s
                          AND post_name = %s
                        LIMIT 1
                SQL, 'hdptap_cpt_archive', $postType );

            if ( empty( $prepare ) ) {
                return null;
            }

            /** @var int|string|null $postId */
            $postId = $wpdb->get_var( $prepare );

            if ( null !== $postId ) {
                /** @var WP_Post $post */
                $post = WP_Post::get_instance( (int) $postId );

                self::$store[ $postType ] = $post;
            }
        }

        return ! empty( self::$store[ $postType ] ) ? self::$store[ $postType ] : null;
    }
}
