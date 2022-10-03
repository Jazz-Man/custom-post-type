<?php

namespace JazzMan\Post;

use JazzMan\AutoloadInterface\AutoloadInterface;

class ArchivePostType implements AutoloadInterface {
    private const ARCHIVE_POST_TYPE = 'hdptap_cpt_archive';

    public function load(): void {
        add_action('init', [self::class, 'registerCptArchivePostType']);
        add_action('registered_post_type', [self::class, 'createArchivePages'], 10, 2);
        add_filter('parent_file', [self::class, 'adminMenuCorrection']);

        add_filter('pre_wp_unique_post_slug', [self::class, 'fixArchivePostTypeSlug'], 10, 5);

        add_action('admin_menu', [self::class, 'addAdminMenuArchivePages'], 99);

        add_filter('get_the_archive_title', [self::class, 'archiveTitle'], 10, 1);
        add_filter('get_the_archive_description', [self::class, 'archiveDescription'], 10, 1);
    }

    public static function registerCptArchivePostType(): void {
        if (!post_type_exists(self::ARCHIVE_POST_TYPE)) {
            /**
             * Lets register the conditions post type
             * post type name is docp_condition.
             */
            $labels = cpt_get_post_type_labels('Archive Pages');

            $supports = apply_filters(
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

    /**
     * @return string
     */
    public static function fixArchivePostTypeSlug(?string $original, string $slug, int $postId, string $postStatus, string $postType): ?string {
        if (self::ARCHIVE_POST_TYPE === $postType) {
            return $slug;
        }

        return $original;
    }

    public static function createArchivePages(string $post_type, \WP_Post_Type $args): void {
        // if this is the archive pages post type - do nothing.
        // if this post type is not supposed to support an archive - do nothing.
        if (self::ARCHIVE_POST_TYPE === $post_type || empty($args->has_archive) || !isset($args->add_archive_page) || !is_admin()) {
            return;
        }

        if ($args->add_archive_page) {
            $post_type_archive_id = cpt_get_post_type_archive_post_id($post_type);

            if (false === $post_type_archive_id) {
                $postarr = [
                    'post_type' => self::ARCHIVE_POST_TYPE,
                    'post_title' => $args->labels->name,
                    'post_status' => 'publish',
                    'post_name' => $post_type,
                ];

                wp_insert_post($postarr, true);
            }
        }
    }

    public static function adminMenuCorrection(string $parent_file = ''): string {
        global $current_screen;

        /** @var null|int $postId */
        $postId = filter_input(INPUT_GET, 'post', FILTER_SANITIZE_NUMBER_INT);
        // if this is a post edit screen for the archive page post type.
        if (!empty($postId) && 'post' === $current_screen->base && self::ARCHIVE_POST_TYPE === $current_screen->post_type) {
            // get the plugin options.

            $post = get_post($postId);

            // if we have an archive post type returned.
            if (!empty($post)) {
                // set the parent file to the archive post type.
                $parent_file = sprintf('edit.php?post_type=%s', esc_attr($post->post_name));
            }
        }

        return $parent_file;
    }

    public static function addAdminMenuArchivePages(): void {
        $post_type_object = get_post_type_object(self::ARCHIVE_POST_TYPE);

        if ($post_type_object instanceof \WP_Post_Type) {
            /** @var \WP_Post[] $post_types */
            $post_types = get_posts(
                [
                    'post_type' => self::ARCHIVE_POST_TYPE,
                    'post_status' => 'publish',
                    'numberposts' => -1,
                ]
            );

            if (!empty($post_types)) {
                foreach ($post_types as $post_type) {
                    // add the menu item for this post type.
                    add_submenu_page(
                        sprintf('edit.php?post_type=%s', esc_attr($post_type->post_name)),
                        'Archive Page',
                        'Archive Page',
                        'edit_posts',
                        sprintf("{$post_type_object->_edit_link}&action=edit", $post_type->ID),
                        false
                    );
                }
            }
        }
    }

    public static function archiveTitle(string $title = ''): string {
        // only proceed if this is a post type archive.
        if (!is_post_type_archive()) {
            return $title;
        }
        // get the current post type.
        $object = get_queried_object();

        if ($object instanceof \WP_Post_Type) {
            // get the post type archive title for this post type.
            $archive_title = cpt_get_post_type_archive_title($object->name);

            if (!empty($archive_title)) {
                return apply_filters('the_title', $archive_title);
            }
        }

        return $title;
    }

    public static function archiveDescription(string $desc = ''): string {
        // only proceed if this is a post type archive.
        if (!is_post_type_archive()) {
            return $desc;
        }
        // get the current post type.
        $current_post_type = get_queried_object()->name;

        // get the post type archive desc for this post type.

        $archive_content = cpt_get_post_type_archive_content($current_post_type);

        // if we have a desc.

        if (!empty($archive_content)) {
            $desc = apply_filters('the_content', $archive_content);
        }

        return $desc;
    }
}
