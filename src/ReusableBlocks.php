<?php

namespace JazzMan\Post;

use JazzMan\AutoloadInterface\AutoloadInterface;
use WP_Post;

class ReusableBlocks implements AutoloadInterface {
    /**
     * @var string
     */
    public const CACHE_GROUP = 'custom-post-type';

    private ?string $postType = null;

    public function load(): void {
        $block = new CustomPostType('wp_block', [
            'add_archive_page' => false,
        ]);

        $block->registerTaxonomy('block_category', [
            'public' => false,
            'show_ui' => true,
        ]);

        $this->postType = $block->post_type;

        $block->setColumns([
            'post_id' => 'Block ID',
            'post_name' => 'Block Slug',
        ]);

        $block->setPopulateColumns('post_name', function (string $column, WP_Post $post): void {
            printf('<code>%s</code>', esc_attr($post->post_name));
        });

        add_action('admin_menu', [$this, 'reusableBlocks']);
        add_action(sprintf('save_post_%s', $this->postType), static function (int $postId, WP_Post $wpPost): void {
            wp_cache_delete(sprintf('%s_%s', $wpPost->post_type, $wpPost->post_name), self::CACHE_GROUP);
        }, 10, 2);
    }

    public function reusableBlocks(): void {
        $postTypeProps = [
            'post_type' => $this->postType,
        ];

        $wpBlockSlug = add_query_arg($postTypeProps, 'edit.php');

        add_menu_page(
            'Reusable Blocks',
            'Reusable Blocks',
            'edit_posts',
            $wpBlockSlug,
            '',
            'dashicons-editor-table',
            22
        );

        $postTypeProps['taxonomy'] = 'block_category';

        add_submenu_page(
            $wpBlockSlug,
            'Blocks Tax',
            'Blocks Tax',
            'edit_posts',
            add_query_arg(
                $postTypeProps,
                'edit-tags.php'
            )
        );
    }
}
