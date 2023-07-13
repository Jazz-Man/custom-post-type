<?php

namespace JazzMan\Post;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Post;
use WP_Post_Type;
use WP_Screen;

final class ArchivePostType implements AutoloadInterface {

    /**
     * @var string
     */
    private const ARCHIVE_POST_TYPE = 'hdptap_cpt_archive';

    /**
     * @var array<array-key|string,WP_Post>
     */
    private static array $store = [];

    public function load(): void {
        add_action( 'init', static function (): void {
            self::registerCptArchivePostType();
        } );

        add_action( 'registered_post_type', static function ( string $postType, WP_Post_Type $wpPostType ): void {
            self::createArchivePages( $postType, $wpPostType );
        }, 10, 2 );

        add_filter( 'parent_file', static fn ( string $parentFile = '' ): string => self::adminMenuCorrection( $parentFile ) );

        add_filter(
            'pre_wp_unique_post_slug',
            static fn ( ?string $original, string $slug, int $postId, string $postStatus, string $postType ): ?string => self::fixArchivePostTypeSlug(
                $original,
                $slug,
                $postId,
                $postStatus,
                $postType
            ),
            10,
            5
        );

        add_action( 'admin_menu', static function (): void {
            self::addAdminMenuArchivePages();
        }, 99 );

        add_filter( 'post_type_archive_title', static fn ( string $title, string $postType ): string => self::archiveTitle( $title, $postType ), 10, 2 );

        add_filter( 'get_the_post_type_description', static fn ( string $description, WP_Post_Type $wpPostType ): string => self::archiveDescription( $description, $wpPostType ), 10, 2 );
    }

    private static function archiveTitle( string $title, string $postType ): string {
        $object = get_post_type_object( $postType );

        if ( ! $object instanceof WP_Post_Type ) {
            return $title;
        }

        $add_archive_page = self::hasAddArchivePage( $object );

        if ( ! $add_archive_page ) {
            return $title;
        }

        $archivePost = self::getPostTypeArchive( $postType );

        if ( $archivePost instanceof WP_Post ) {
            return (string) apply_filters( 'the_title', $archivePost->post_title );
        }

        return $title;
    }

    private static function archiveDescription( string $description, WP_Post_Type $wpPostType ): string {
        $add_archive_page = self::hasAddArchivePage( $wpPostType );

        if ( ! $add_archive_page ) {
            return $description;
        }

        $archivePost = self::getPostTypeArchive( $wpPostType->name );

        if ( $archivePost instanceof WP_Post ) {
            return (string) apply_filters( 'the_content', $archivePost->post_content );
        }

        return $description;
    }

    private static function addAdminMenuArchivePages(): void {
        $post_type_object = get_post_type_object( self::ARCHIVE_POST_TYPE );

        if ( ! $post_type_object instanceof WP_Post_Type ) {
            return;
        }

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

    private static function fixArchivePostTypeSlug( ?string $original, string $slug, int $postId, string $postStatus, string $postType ): ?string {
        if ( self::ARCHIVE_POST_TYPE === $postType ) {
            return $slug;
        }

        return $original;
    }

    private static function adminMenuCorrection( string $parentFile = '' ): string {
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
            if ( $post instanceof WP_Post ) {
                // set the parent file to the archive post type.
                $parentFile = sprintf( 'edit.php?post_type=%s', esc_attr( $post->post_name ) );
            }
        }

        return $parentFile;
    }

    private static function createArchivePages( string $postType, WP_Post_Type $wpPostType ): void {
        // if this is the archive pages post type - do nothing.
        // if this post type is not supposed to support an archive - do nothing.
        if ( self::ARCHIVE_POST_TYPE === $postType ) {
            return;
        }

        $add_archive_page = self::hasAddArchivePage( $wpPostType );

        if ( ! $add_archive_page ) {
            return;
        }

        if ( ! is_admin() ) {
            return;
        }

        $archivePost = self::getPostTypeArchive( $postType );

        if ( $archivePost instanceof WP_Post ) {
            return;
        }

        wp_insert_post( [
            'post_type' => self::ARCHIVE_POST_TYPE,
            'post_title' => $wpPostType->labels->name,
            'post_status' => 'publish',
            'post_name' => $postType,
        ], true );
    }

    private static function registerCptArchivePostType(): void {
        if ( post_type_exists( self::ARCHIVE_POST_TYPE ) ) {
            return;
        }

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

        return empty( self::$store[ $postType ] ) ? null : self::$store[ $postType ];
    }

    private static function hasAddArchivePage( WP_Post_Type $object ): bool {
        if ( empty( $object->has_archive ) ) {
            return false;
        }

        return property_exists( $object, 'add_archive_page' ) && null !== $object->add_archive_page && (bool) $object->add_archive_page;
    }
}
