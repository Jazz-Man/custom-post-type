<?php

namespace JazzMan\Post;

use WP_Post;
use WP_Post_Type;
use WP_Taxonomy;

/**
 * Class CustomPostType.
 */
class CustomPostType {

    public string $post_type;

    public string $post_type_name;

    /**
     * @var array<string,mixed>
     */
    private array $postTypeOptions = [];

    /**
     * CustomPostType constructor.
     *
     * @param array<string,mixed> $options
     */
    public function __construct( string $postType, array $options = [] ) {
        $this->post_type_name = $postType;
        $this->post_type = sanitize_key( $this->post_type_name );

        $this->postTypeOptions = $options;

        $this->registerPostType();
    }

    public function setColumns( array $columns ): void {
        foreach ( $columns as $column_slug => $data ) {
            if ( empty( $data['labes'] ) ) {
                continue;
            }

            if ( empty( $data['callback'] ) ) {
                continue;
            }

            if ( ! \is_callable( $data['callback'] ) ) {
                continue;
            }

            add_filter(
                sprintf( 'manage_%s_posts_columns', $this->post_type ),
                function ( array $_columns ) use ( $column_slug, $data ) {
                    $_columns[$column_slug] = $data['labes'];

                    return $_columns;
                }
            );

            add_action(
                sprintf( 'manage_%s_posts_custom_column', $this->post_type ),
                function ( string $column_name, int $post_id ) use ( $column_slug, $data ): void {
                    global $post;

                    if ( ! $post instanceof WP_Post ) {
                        return;
                    }

                    switch ( $column_name ) {
                        case 'post_id':
                            printf(
                                '<span title="%1$s ID: %2$s">%2$s</span>',
                                esc_attr( $post->post_title ),
                                esc_attr( (string) $post->ID ),
                            );

                            break;

                        case str_starts_with( $column_name, 'meta_' ):
                            self::printMetaColumn( $post_id, ltrim( $column_name, 'meta_' ), $post );

                            break;

                        case 'icon':
                            self::printIconColumn( $post_id, $post );

                            break;

                        default:
                            if ( $column_name !== $column_slug ) {
                                return;
                            }

                            \call_user_func( $data['callback'], $column_name, $post );

                            break;
                    }
                },
                10,
                2
            );

            if ( ! empty( $data['sort'] ) ) {
                add_filter(
                    sprintf( 'manage_edit-%s_sortable_columns', $this->post_type ),
                    function ( array $_columns ) use ( $column_slug ) {
                        $_columns[$column_slug] = $column_slug;

                        return $_columns;
                    }
                );

                if ( ! empty( $data['sort_function'] ) && \is_callable( $data['sort_function'] ) ) {
                    add_filter( 'request', $data['sort_function'] );
                }
            }
        }
    }

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
                fn ( array $args ) => wp_parse_args( $options, $args )
            );
        } else {
            add_action( 'init', function () use ( $taxonomy, $options ): void {
                register_taxonomy( $taxonomy, $this->post_type, $options );
            } );
        }

        add_action( 'init', function () use ( $taxonomy ): void {
            register_taxonomy_for_object_type( $taxonomy, $this->post_type );
        } );
    }

    public function setMenuIcon( string $icon = 'dashicons-admin-page' ): void {
        $this->postTypeOptions['menu_icon'] = false !== stripos( $icon, 'dashicons' ) ? $icon : 'dashicons-admin-page';
    }

    public function onSave( callable $function, int $priority = 10, int $acceptedArgs = 2 ): void {
        add_action( sprintf( 'save_post_%s', $this->post_type ), $function, $priority, $acceptedArgs );
    }

    public function onUpdate( callable $function, int $priority = 10, int $acceptedArgs = 2 ): void {
        add_action( sprintf( 'edit_post_%s', $this->post_type ), $function, $priority, $acceptedArgs );
    }

    private function registerPostType(): void {
        $typeObject = get_post_type_object( $this->post_type_name );

        $options = $this->getPostTypeOptions( $this->postTypeOptions );

        if ( $typeObject instanceof WP_Post_Type ) {
            add_filter(
                sprintf( 'register_%s_post_type_args', $this->post_type_name ),
                fn ( array $args ) => wp_parse_args( $options, $args )
            );
        } else {
            add_action( 'init', function () use ( $options ): void {
                register_post_type( $this->post_type_name, $options );
            } );
        }

        $this->addTaxonomyFilters();
    }

    private function getPostTypeOptions( array $options = [] ): array {
        $defaults = [
            'labels' => app_get_post_type_labels( $this->post_type_name ),
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

            if ( $postType !== $this->post_type ) {
                return;
            }

            /** @var array<string,WP_Taxonomy> $taxonomies */
            $taxonomies = get_object_taxonomies( $this->post_type, 'objects' );

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

    private static function printMetaColumn( int $postId, string $metaKey, WP_Post $wpPost ): void {
        /** @var string|null $meta */
        $meta = get_post_meta( $postId, $metaKey, true );

        if ( ! empty( $meta ) ) {
            printf(
                '<span title="%s Meta: %s">%s</span>',
                esc_attr( $wpPost->post_title ),
                esc_attr( $metaKey ),
                esc_attr( $meta )
            );
        }
    }

    private static function printIconColumn( int $postId, WP_Post $wpPost ): void {
        $link = sprintf( 'post.php?post=%d&action=edit', $wpPost->ID );

        if ( has_post_thumbnail( $postId ) ) {
            printf(
                '<a title="%s Thumbnail" href="%s">%s</a>',
                esc_attr( $wpPost->post_title ),
                esc_url( $link ),
                get_the_post_thumbnail(
                    $postId,
                    [60, 60],
                    [
                        'alt' => $wpPost->post_title,
                    ]
                )
            );
        } else {
            printf(
                '<a title="%3$s Thumbnail" href="%1$s"><img src="%2$s" alt="%3$s"/></a>',
                esc_url( $link ),
                esc_url( includes_url( 'images/crystal/default.png' ) ),
                esc_attr( $wpPost->post_title )
            );
        }
    }
}
