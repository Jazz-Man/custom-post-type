<?php

namespace JazzMan\Post;

use JazzMan\AutoloadInterface\AutoloadInterface;

class ReusableBlocks implements AutoloadInterface {
    /**
     * @var string
     */
    public const CACHE_GROUP = 'custom-post-type';

    /**
     * @var string
     */
    private const POST_TYPE = 'wp_block';

    public function load(): void {
        $customPostType = new CustomPostType(self::POST_TYPE, [
            'labels' => app_get_post_type_labels('Reusable Blocks'),
            'public' => false,
            'rewrite' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'add_archive_page' => false,
        ]);

        $customPostType->registerTaxonomy('block_category', [
            'public' => false,
            'show_ui' => true,
        ]);

        $customPostType->setColumns([
            'post_id' => 'Block ID',
            'post_name' => 'Block Slug',
        ]);

        $customPostType->setPopulateColumns('post_name', static function (string $column, \WP_Post $wpPost): void {
            printf('<code>%s</code>', esc_attr($wpPost->post_name));
        });

        add_action(
            sprintf('save_post_%s', self::POST_TYPE),
            static function (int $postId, \WP_Post $wpPost): void {
                wp_cache_delete(sprintf('%s_%s', $wpPost->post_type, $wpPost->post_name), self::CACHE_GROUP);
            },
            10,
            2
        );
    }
}
