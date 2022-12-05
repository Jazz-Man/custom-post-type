<?php

namespace JazzMan\Post;

use JazzMan\AutoloadInterface\AutoloadInterface;

class ReusableBlocks implements AutoloadInterface {
    /**
     * @var string
     */
    public const CACHE_GROUP = 'custom-post-type';

    private const POST_TYPE = 'wp_block';

    public function load(): void {
        $block = new CustomPostType(self::POST_TYPE, [
            'labels' => app_get_post_type_labels('Reusable Blocks'),
            'public' => false,
            'rewrite' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'add_archive_page' => false,
        ]);

        $block->registerTaxonomy('block_category', [
            'public' => false,
            'show_ui' => true,
        ]);

        $block->setColumns([
            'post_id' => 'Block ID',
            'post_name' => 'Block Slug',
        ]);

        $block->setPopulateColumns('post_name', function (string $column, \WP_Post $post): void {
            printf('<code>%s</code>', esc_attr($post->post_name));
        });

        add_action(sprintf('save_post_%s', self::POST_TYPE), static function (int $postId, \WP_Post $wpPost): void {
            wp_cache_delete(sprintf('%s_%s', $wpPost->post_type, $wpPost->post_name), self::CACHE_GROUP);
        }, 10, 2);
    }
}
