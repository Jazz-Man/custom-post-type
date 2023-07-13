<?php

namespace JazzMan\Post;

use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;

/**
 * Class CustomPostType.
 */
final class CustomPostType {

    private readonly string $postType;

    /**
     * CustomPostType constructor.
     *
     * @param array<string, mixed> $postTypeOptions
     */
    public function __construct( public string $postTypeName, private array $postTypeOptions = [] ) {
        $this->postType = sanitize_key( $this->postTypeName );

        $this->registerPostType();
    }

    public function getPostType(): string {
        return $this->postType;
    }

    /**
     * @param array<string,mixed> $options
     */
    public function registerTaxonomy( string $taxonomy, array $options = [] ): void {
        $taxonomy = sanitize_key( $taxonomy );

        $defaults = [
            'labels' => app_get_taxonomy_labels( $taxonomy ),
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'show_in_quick_edit' => true,
        ];

        $options = wp_parse_args( $options, $defaults );

        $taxonomyObject = get_taxonomy( $taxonomy );

        if ( $taxonomyObject instanceof WP_Taxonomy ) {
            add_filter(
                sprintf( 'register_%s_taxonomy_args', $taxonomy ),
                static fn ( array $args ): array => wp_parse_args( $options, $args )
            );
        } else {
            add_action( 'init', function () use ( $taxonomy, $options ): void {
                register_taxonomy( $taxonomy, $this->postType, $options );
            } );
        }

        add_action( 'init', function () use ( $taxonomy ): void {
            register_taxonomy_for_object_type( $taxonomy, $this->postType );
        } );
    }

    public function setMenuIcon( string $icon = 'dashicons-admin-page' ): void {
        $this->postTypeOptions['menu_icon'] = false !== stripos( $icon, 'dashicons' ) ? $icon : 'dashicons-admin-page';
    }

    public function onSave( callable $function, int $priority = 10, int $acceptedArgs = 2 ): void {
        add_action( sprintf( 'save_post_%s', $this->postType ), $function, $priority, $acceptedArgs );
    }

    public function onUpdate( callable $function, int $priority = 10, int $acceptedArgs = 2 ): void {
        add_action( sprintf( 'edit_post_%s', $this->postType ), $function, $priority, $acceptedArgs );
    }

    public function onDeletePost( callable $function, int $priority = 10 ): void {

        $this->deleteActions( 'delete_post', $function, $priority );
    }

    public function onDeletedPost( callable $function, int $priority = 10 ): void {

        $this->deleteActions( 'deleted_post', $function, $priority );
    }

    public function onAfterDeletePost( callable $function, int $priority = 10 ): void {

        $this->deleteActions( 'after_delete_post', $function, $priority );
    }

    private function deleteActions( string $hook, callable $function, int $priority = 10 ): void {
        add_action( $hook, function ( int $postid, WP_Post $post ) use ( $function ): void {
            if ( $post->post_type !== $this->postType ) {
                return;
            }

            \call_user_func( $function, $postid, $post );
        }, $priority, 2 );
    }

    private function registerPostType(): void {
        $typeObject = get_post_type_object( $this->postTypeName );

        $options = $this->getPostTypeOptions( $this->postTypeOptions );

        if ( $typeObject instanceof WP_Post_Type ) {
            add_filter(
                sprintf( 'register_%s_post_type_args', $this->postTypeName ),
                static fn ( array $args ): array => wp_parse_args( $options, $args )
            );
        } else {
            add_action( 'init', function () use ( $options ): void {
                register_post_type( $this->postTypeName, $options );
            } );
        }

        if ( ! empty( $options['supports'] ) && ! empty( $options['supports']['thumbnail'] ) ) {
            $this->registerPostMeta();
        }

        $this->addTaxonomyFilters();
    }

    private function registerPostMeta(): void {
        $meta = ( new PostTypeMeta( $this->postType, 'thumbnail' ) )
            ->setMataDescription( 'Featured image' )
            ->setMataLabel( 'Featured image' )
            ->setColumnCallback(
                static fn ( string $column, WP_Post $post ) => PostTypeMeta::columnContent( $column, $post )
            )
        ;

        $meta->run();
    }

    /**
     * @param array<string,mixed> $options
     *
     * @return array<string,mixed>
     */
    private function getPostTypeOptions( array $options = [] ): array {
        $defaults = [
            'labels' => app_get_post_type_labels( $this->postTypeName ),
            'public' => true,
            'show_in_rest' => true,
            'add_archive_page' => true,
        ];

        return array_replace_recursive( $defaults, $options );
    }

    private function addTaxonomyFilters(): void {
        /**
         * @param string $postType the post type slug
         * @param string $which    the location of the extra table nav markup:
         *                         'top' or 'bottom' for WP_Posts_List_Table,
         *                         'bar' for WP_Media_List_Table
         */
        add_action( 'restrict_manage_posts', function ( string $postType, string $which ): void {
            if ( 'top' !== $which ) {
                return;
            }

            if ( $postType !== $this->postType ) {
                return;
            }

            /** @var array<string,WP_Taxonomy> $taxonomies */
            $taxonomies = get_object_taxonomies( $this->postType, 'objects' );

            if ( [] === $taxonomies ) {
                return;
            }

            foreach ( $taxonomies as $taxonomy => $object ) {
                if ( ! $object->show_admin_column ) {
                    continue;
                }

                if ( empty( $object->query_var ) ) {
                    continue;
                }

                $options = [
                    'hide_empty' => 0,
                    'hierarchical' => 1,
                    'show_count' => 0,
                    'orderby' => 'name',
                    'name' => $object->query_var,
                    'value_field' => 'slug',
                    'taxonomy' => $taxonomy,
                    'selected' => get_query_var( $object->query_var ),
                ];

                if ( ! empty( $object->labels->all_items ) ) {
                    $options['show_option_all'] = (string) $object->labels->all_items;
                }

                printf(
                    '<label class="screen-reader-text" for="%s">%s</label>',
                    esc_attr( $taxonomy ),
                    esc_html( (string) $object->labels->filter_by_item )
                );

                wp_dropdown_categories( $options );
            }
        }, 10, 2 );
    }
}
