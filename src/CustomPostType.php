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
    private array $postTypeOptions;

    private string $singularLabel;

    private string $pluralLabel;

    /**
     * CustomPostType constructor.
     *
     * @param array<string,mixed> $options
     */
    public function __construct(string $postType, array $options = []) {
        $this->initPostTypeConfig($postType);

        $this->postTypeOptions = $options;

        $this->registerPostType();

        add_filter('post_updated_messages', [$this, 'updatedMessages']);
        add_filter('bulk_post_updated_messages', [$this, 'bulkUpdatedMessages'], 10, 2);
    }

    /**
     * Add custom columns to the table in the admin part.
     *
     * @param array<string,string> $columns
     */
    public function setColumns(array $columns = []): void {
        add_filter("manage_edit-{$this->post_type}_columns", function (array $wp_columns = []) use ($columns) {
            $newColumns = [];

            $newColumns['cb'] = $wp_columns['cb'];
            $newColumns['title'] = $wp_columns['title'];

            $date = $wp_columns['date'];

            unset($wp_columns['cb'], $wp_columns['title'], $wp_columns['date']);

            foreach (['cb', 'title', 'date'] as $col) {
                if (!empty($columns[$col])) {
                    unset($columns[$col]);
                }
            }

            $merge = wp_parse_args($wp_columns, $columns);

            foreach ($merge as $key => $value) {
                $newColumns[$key] = $value;
            }

            $newColumns['date'] = $date;

            return $newColumns;
        });
    }

    /**
     * Fires for each custom column of a specific post type in the Posts list table.
     * The dynamic portion of the hook name, `$post->post_type`, refers to the post type.
     *
     * @see \WP_Posts_List_Table::column_default
     */
    public function setPopulateColumns(string $column, callable $callback): void {
        add_action(
            "manage_{$this->post_type}_posts_custom_column",
            function (string $col = '', int $postId = 0) use ($callback): void {
                global $post;

                if (!$post instanceof WP_Post) {
                    return;
                }

                switch ($col) {
                    case 'post_id':
                        printf(
                            '<span title="%1$s ID: %2$s">%2$s</span>',
                            esc_attr($post->post_title),
                            esc_attr((string) $post->ID),
                        );

                        break;

                    case 0 === strpos($col, 'meta_'):
                        $this->printMetaColumn($postId, ltrim($col, 'meta_'), $post);

                        break;

                    case 'icon':
                        $this->printIconColumn($postId, $post);

                        break;

                    default:
                        \call_user_func($callback, $col, $post);

                        break;
                }
            },
            10,
            2
        );
    }

    /**
     * @param array $options {
     *
     * @var string[]      $labels
     * @var string        $description
     * @var bool          $public
     * @var bool          $publicly_queryable
     * @var bool          $hierarchical
     * @var bool          $show_ui
     * @var bool          $show_in_menu
     * @var bool          $show_in_nav_menus
     * @var bool          $show_in_rest
     * @var string        $rest_base
     * @var string        $rest_namespace
     * @var string        $rest_controller_class
     * @var bool          $show_tagcloud
     * @var bool          $show_in_quick_edit
     * @var bool          $show_admin_column
     * @var bool|callable $meta_box_cb
     * @var callable      $meta_box_sanitize_cb
     * @var string[]      $capabilities {
     * @var string        $manage_terms
     * @var string        $edit_terms
     * @var string        $delete_terms
     * @var string        $assign_terms
     *                    }
     * @var array|bool    $rewrite {
     * @var string        $slug
     * @var bool          $with_front
     * @var bool          $hierarchical
     * @var int           $ep_mask
     *                    }
     * @var bool|string   $query_var
     * @var callable      $update_count_callback
     * @var array|string  $default_term {
     * @var string        $name
     * @var string        $slug
     * @var string        $description
     *                    }
     * @var bool          $sort
     * @var array         $args
     * @var bool          $_builtin
     *                    }
     */
    public function registerTaxonomy(string $taxonomy, array $options = []): void {
        $taxonomy = sanitize_key($taxonomy);

        $defaults = [
            'labels' => app_get_taxonomy_labels($taxonomy),
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'show_in_quick_edit' => true,
        ];

        $options = wp_parse_args($options, $defaults);

        $taxonomyObject = get_taxonomy($taxonomy);

        if ($taxonomyObject instanceof WP_Taxonomy) {
            add_filter(
                "register_{$taxonomy}_taxonomy_args",
                fn (array $args) => wp_parse_args($options, $args)
            );
        } else {
            add_action('init', function () use ($taxonomy, $options): void {
                register_taxonomy($taxonomy, $this->post_type, $options);
            });
        }

        add_action('init', function () use ($taxonomy): void {
            register_taxonomy_for_object_type($taxonomy, $this->post_type);
        });
    }

    public function setMenuIcon(string $icon = 'dashicons-admin-page'): void {
        $this->postTypeOptions['menu_icon'] = false !== stripos($icon, 'dashicons') ? $icon : 'dashicons-admin-page';
    }

    /**
     * @param array<string,array<string,mixed>> $messages
     *
     * @return array[]
     *
     * @psalm-return array<string, array<int|string, mixed>>
     */
    public function updatedMessages(array $messages = []): array {
        $post = get_post();

        /** @var null|int $revision */
        $revision = filter_input(INPUT_GET, 'revision', FILTER_SANITIZE_NUMBER_INT);

        /** @var false|string $revisionTitle */
        $revisionTitle = false;

        if (!empty($revision)) {
            $title = wp_post_revision_title($revision, false);

            if (!empty($title)) {
                $revisionTitle = sprintf(
                    '%1$s restored to revision from %2$s',
                    esc_attr($this->singularLabel),
                    $title
                );
            }
        }

        $messages[$this->post_type_name] = [
            0 => '',
            1 => sprintf('%s updated.', esc_attr($this->singularLabel)),
            2 => 'Custom field updated.',
            3 => 'Custom field deleted.',
            4 => sprintf('%s updated.', esc_attr($this->singularLabel)),
            5 => $revisionTitle,
            6 => sprintf('%s updated.', esc_attr($this->singularLabel)),
            7 => sprintf('%s saved.', esc_attr($this->singularLabel)),
            8 => sprintf('%s submitted.', esc_attr($this->singularLabel)),
            9 => sprintf(
                '%s scheduled for: <strong>%s</strong>.',
                esc_attr($this->singularLabel),
                $post instanceof WP_Post ? date_i18n('M j, Y @ G:i', strtotime($post->post_date)) : ''
            ),
            10 => sprintf('%s draft updated.', esc_attr($this->singularLabel)),
        ];

        return $messages;
    }

    /**
     * @param array<string,array<string,string>> $messages
     * @param array<string,int>                  $counts
     *
     * @return string[][]
     *
     * @psalm-return array<string, array<string, string>>
     */
    public function bulkUpdatedMessages(array $messages = [], array $counts = []): array {
        $messages[$this->post_type_name] = [
            'updated' => _n(
                "%s {$this->singularLabel} updated.",
                "%s {$this->pluralLabel} updated.",
                $counts['updated']
            ),
            'locked' => _n(
                "%s {$this->singularLabel} not updated, somebody is editing it.",
                "%s {$this->pluralLabel} not updated, somebody is editing them.",
                $counts['locked']
            ),
            'deleted' => _n(
                "%s {$this->singularLabel} permanently deleted.",
                "%s {$this->pluralLabel} permanently deleted.",
                $counts['deleted']
            ),
            'trashed' => _n(
                "%s {$this->singularLabel} moved to the Trash.",
                "%s {$this->pluralLabel} moved to the Trash.",
                $counts['trashed']
            ),
            'untrashed' => _n(
                "%s {$this->singularLabel} restored from the Trash.",
                "%s {$this->pluralLabel} restored from the Trash.",
                $counts['untrashed']
            ),
        ];

        return $messages;
    }

    private function registerPostType(): void {
        $typeObject = get_post_type_object($this->post_type_name);

        $options = $this->getPostTypeOptions($this->postTypeOptions);

        if ($typeObject instanceof WP_Post_Type) {
            add_filter(
                "register_{$this->post_type_name}_post_type_args",
                fn (array $args) => wp_parse_args($options, $args)
            );
        } else {
            add_action('init', function () use ($options): void {
                register_post_type($this->post_type_name, $options);
            });
        }

        $this->addTaxonomyFilters();
    }

    private function addTaxonomyFilters(): void {
        /**
         * @param string $postType the post type slug
         * @param string $which    the location of the extra table nav markup:
         *                         'top' or 'bottom' for WP_Posts_List_Table,
         *                         'bar' for WP_Media_List_Table
         */
        add_action('restrict_manage_posts', function (string $postType, string $which): void {
            if ('top' !== $which) {
                return;
            }

            if ($postType !== $this->post_type) {
                return;
            }

            /** @var WP_Taxonomy[] $taxonomies */
            $taxonomies = get_object_taxonomies($this->post_type, 'objects');

            if (empty($taxonomies)) {
                return;
            }

            foreach ($taxonomies as $taxonomy => $object) {
                if (!$object->show_admin_column || empty($object->query_var)) {
                    continue;
                }

                /** @var null|string $currentTerm */
                $currentTerm = filter_input(INPUT_GET, $object->query_var, FILTER_SANITIZE_STRING);

                $options = [
                    'hide_empty' => 0,
                    'hierarchical' => 1,
                    'show_count' => 0,
                    'orderby' => 'name',
                    'name' => $object->query_var,
                    'value_field' => 'slug',
                    'taxonomy' => $taxonomy,
                ];

                if (!empty($object->labels->all_items)) {
                    $options['show_option_all'] = (string) $object->labels->all_items;
                }

                if (!empty($currentTerm)) {
                    $options['selected'] = $currentTerm;
                }

                printf(
                    '<label class="screen-reader-text" for="%s">%s</label>',
                    esc_attr($taxonomy),
                    esc_html((string) $object->labels->filter_by_item)
                );

                wp_dropdown_categories($options);
            }
        }, 10, 2);
    }

    private function initPostTypeConfig(string $postType): void {
        $this->post_type_name = $postType;

        $this->post_type = sanitize_key($this->post_type_name);

        $pluralizer = app_string_pluralizer($this->post_type_name);

        $this->singularLabel = $pluralizer['singular'];
        $this->pluralLabel = $pluralizer['plural'];
    }

    /**
     * @param array<string,mixed> $options
     */
    private function getPostTypeOptions(array $options = []): array {
        $defaults = [
            'labels' => app_get_post_type_labels($this->post_type_name),
            'public' => true,
            'show_in_rest' => true,
            'add_archive_page' => true,
        ];

        return array_replace_recursive($defaults, $options);
    }

    private function printIconColumn(int $postId, WP_Post $post): void {
        $link = sprintf('post.php?post=%d&action=edit', $post->ID);

        if (has_post_thumbnail($postId)) {
            printf(
                '<a title="%s Thumbnail" href="%s">%s</a>',
                esc_attr($post->post_title),
                esc_url($link),
                get_the_post_thumbnail(
                    $postId,
                    [60, 60],
                    [
                        'alt' => $post->post_title,
                    ]
                )
            );
        } else {
            printf(
                '<a title="%3$s Thumbnail" href="%1$s"><img src="%2$s" alt="%3$s"/></a>',
                esc_url($link),
                esc_url(includes_url('images/crystal/default.png')),
                esc_attr($post->post_title)
            );
        }
    }

    private function printMetaColumn(int $postId, string $metaKey, WP_Post $post): void {
        /** @var null|string $meta */
        $meta = get_post_meta($postId, $metaKey, true);

        if (!empty($meta)) {
            printf(
                '<span title="%s Meta: %s">%s</span>',
                esc_attr($post->post_title),
                esc_attr($metaKey),
                esc_attr($meta)
            );
        }
    }
}
