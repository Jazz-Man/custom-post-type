<?php

namespace JazzMan\Post;

/**
 * Class CustomPostType.
 */
class CustomPostType {
    public string $post_type;

    public string $post_type_name;

    /**
     * @var string[]
     */
    private array $exisitingTaxonomies = [];

    /**
     * @var array<string,array<string,mixed>>
     */
    private array $sortable;

    /**
     * @var array<string,mixed>
     */
    private array $postTypeOptions;

    /**
     * @var null|string[]
     */
    private ?array $taxonomies;

    /**
     * @var array[]       {
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
     * @var bool          $_builtin
     *                    }
     */
    private array $taxonomySettings = [];

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

        add_action('init', [$this, 'registerTaxonomies']);
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerExisitingTaxonomies']);

        add_action('restrict_manage_posts', [$this, 'addTaxonomyFilters']);

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

                if (!$post instanceof \WP_Post) {
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

    public function registerPostType(): void {
        if (!post_type_exists($this->post_type)) {
            $options = $this->getPostTypeOptions($this->postTypeOptions);

            register_post_type($this->post_type_name, $options);
        }
    }

    /**
     * @param array $options {
     *
     *  @var string[]      $labels
     *  @var string        $description
     *  @var bool          $public
     *  @var bool          $publicly_queryable
     *  @var bool          $hierarchical
     *  @var bool          $show_ui
     *  @var bool          $show_in_menu
     *  @var bool          $show_in_nav_menus
     *  @var bool          $show_in_rest
     *  @var string        $rest_base
     *  @var string        $rest_namespace
     *  @var string        $rest_controller_class
     *  @var bool          $show_tagcloud
     *  @var bool          $show_in_quick_edit
     *  @var bool          $show_admin_column
     *  @var bool|callable $meta_box_cb
     *  @var callable      $meta_box_sanitize_cb
     *  @var string[]      $capabilities {
     *      @var string $manage_terms
     *      @var string $edit_terms
     *      @var string $delete_terms
     *      @var string $assign_terms
     *  }
     *  @var array|bool    $rewrite {
     *      @var string $slug
     *      @var bool   $with_front
     *      @var bool   $hierarchical
     *      @var int    $ep_mask
     *  }
     *  @var bool|string   $query_var
     *  @var callable      $update_count_callback
     *  @var array|string  $default_term {
     *      @var string $name
     *      @var string $slug
     *      @var string $description
     *  }
     *  @var bool          $sort
     *  @var array         $args
     *  @var bool          $_builtin
     * }
     */
    public function registerTaxonomy(string $taxonomy, array $options = []): void {
        $taxonomy = sanitize_key($taxonomy);

        $defaults = [
            'labels' => cpt_get_taxonomy_labels($taxonomy),
            'hierarchical' => true,
            'show_in_rest' => true,
            'show_admin_column' => true,
            'show_in_quick_edit' => true,
        ];
        $this->taxonomies[] = $taxonomy;
        $this->taxonomySettings[$taxonomy] = array_replace_recursive($defaults, $options);
    }

    public function registerTaxonomies(): void {
        if (!empty($this->taxonomySettings)) {
            foreach ($this->taxonomySettings as $taxonomy => $options) {
                if (taxonomy_exists($taxonomy)) {
                    $this->exisitingTaxonomies[] = $taxonomy;
                } else {
                    register_taxonomy($taxonomy, $this->post_type, $options);
                }
            }
        }
    }

    public function registerExisitingTaxonomies(): void {
        if (!empty($this->exisitingTaxonomies)) {
            foreach ($this->exisitingTaxonomies as $taxonomy) {
                register_taxonomy_for_object_type($taxonomy, $this->post_type);
            }
        }
    }

    public function addTaxonomyFilters(): void {
        global $typenow;

        if ($typenow === $this->post_type && !empty($this->taxonomies)) {
            foreach ($this->taxonomies as $filter) {
                $tax = get_taxonomy($filter);

                /** @var null|string $currentTerm */
                $currentTerm = filter_input(INPUT_GET, $filter, FILTER_SANITIZE_STRING);

                /** @var \WP_Error|\WP_Term[] $terms */
                $terms = get_terms(
                    [
                        'taxonomy' => $filter,
                        'orderby' => 'name',
                        'hide_empty' => false,
                    ]
                );

                if (!is_wp_error($terms) && !empty($terms)) {
                    $options = sprintf('<option value="0">Show all %s</option>', $tax ? esc_attr($tax->label) : '');

                    foreach ($terms as $term) {
                        $options .= sprintf(
                            '<option value="%s" %s>%s (%s)</option>',
                            esc_attr($term->slug),
                            !empty($currentTerm) ? selected($currentTerm, $term->slug, false) : '',
                            esc_attr($term->name),
                            esc_attr((string) $term->count)
                        );
                    }
                    printf(
                        '<select name="%s" class="postform">%s</select>',
                        esc_attr($filter),
                        $options
                    );
                }
            }
        }
    }

    /**
     * @param array<string,array<string,mixed>> $columns
     */
    public function setSortable(array $columns = []): void {
        $this->sortable = $columns;

        add_filter("manage_edit-{$this->post_type}_sortable_columns", function (array $columns = []): array {
            $sortable_columns = [];

            foreach ($this->sortable as $column => $values) {
                $sortable_columns[$column] = $values[0];
            }

            return array_merge($sortable_columns, $columns);
        });

        add_action('load-edit.php', function (): void {
            /**
             * Load edit
             * Sort columns only on the edit.php page when requested.
             *
             * @see http://codex.wordpress.org/Plugin_API/Filter_Reference/request
             */
            add_filter('request', fn (array $vars) => $this->sortColumns($vars));
        });
    }

    public function setMenuIcon(string $icon = 'dashicons-admin-page'): void {
        $this->postTypeOptions['menu_icon'] = false !== stripos($icon, 'dashicons') ? $icon : 'dashicons-admin-page';
    }

    /**
     * @param array<string,array<string,mixed>> $messages
     *
     * @return array<string,array<string,mixed>>
     */
    public function updatedMessages(array $messages = []): array {
        $post = get_post();

        if ($post instanceof \WP_Post) {
            /** @var null|int $revision */
            $revision = filter_input(INPUT_GET, 'revision', FILTER_SANITIZE_NUMBER_INT);

            $messages[$this->post_type_name] = [
                0 => '',
                1 => sprintf('%s updated.', esc_attr($this->singularLabel)),
                2 => 'Custom field updated.',
                3 => 'Custom field deleted.',
                4 => sprintf('%s updated.', esc_attr($this->singularLabel)),
                5 => !empty($revision) ? sprintf(
                    '%1$s restored to revision from %2$s',
                    esc_attr($this->singularLabel),
                    wp_post_revision_title($revision, false)
                ) : false,
                6 => sprintf('%s updated.', esc_attr($this->singularLabel)),
                7 => sprintf('%s saved.', esc_attr($this->singularLabel)),
                8 => sprintf('%s submitted.', esc_attr($this->singularLabel)),
                9 => sprintf(
                    '%s scheduled for: <strong>%s</strong>.',
                    esc_attr($this->singularLabel),
                    $post ? date_i18n('M j, Y @ G:i', strtotime($post->post_date)) : ''
                ),
                10 => sprintf('%s draft updated.', esc_attr($this->singularLabel)),
            ];
        }

        return $messages;
    }

    /**
     * @param array<string,array<string,string>> $messages
     * @param array<string,int>                  $counts
     *
     * @return array<string,array<string,string>>
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

    /**
     * @param array<string,string> $vars
     *
     * @return array<string,string>
     */
    private function sortColumns(array $vars): array {
        $_vars = [];

        foreach ($this->sortable as $column => $values) {
            $meta_key = $values[0];
            $orderby = isset($values[1]) && true === $values[1] ? 'meta_value_num' : 'meta_value';

            if (isset($vars['post_type']) && $this->post_type === $vars['post_type'] && isset($vars['orderby']) && $meta_key === $vars['orderby']) {
                $_vars[] = [
                    'meta_key' => $meta_key,
                    'orderby' => $orderby,
                ];
            }
        }

        if (!empty($_vars)) {
            $vars = array_merge([], ...$_vars);
        }

        return $vars;
    }

    private function initPostTypeConfig(string $post_type_name): void {
        $this->post_type_name = $post_type_name;

        $this->post_type = sanitize_key($this->post_type_name);

        $pluralizer = cpt_string_pluralizer($this->post_type_name);

        $this->singularLabel = $pluralizer['singular'];
        $this->pluralLabel = $pluralizer['plural'];
    }

    /**
     * @param array<string,mixed> $options
     */
    private function getPostTypeOptions(array $options = []): array {
        $defaults = [
            'labels' => cpt_get_post_type_labels($this->post_type_name),
            'public' => true,
            'show_in_rest' => true,
            'add_archive_page' => true,
        ];

        if (!empty($this->taxonomies)) {
            $defaults['taxonomies'] = $this->taxonomies;
        }

        return array_replace_recursive($defaults, $options);
    }

    private function printIconColumn(int $post_id, \WP_Post $post): void {
        $link = sprintf('post.php?post=%d&action=edit', $post->ID);

        if (has_post_thumbnail($post_id)) {
            printf(
                '<a title="%s Thumbnail" href="%s">%s</a>',
                esc_attr($post->post_title),
                esc_url($link),
                get_the_post_thumbnail(
                    $post_id,
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

    private function printMetaColumn(int $post_id, string $meta_key, \WP_Post $post): void {
        $meta = get_post_meta($post_id, $meta_key, true);

        if (!empty($meta) && \is_string($meta)) {
            printf(
                '<span title="%s Meta: %s">%s</span>',
                esc_attr($post->post_title),
                esc_attr($meta_key),
                esc_attr($meta)
            );
        }
    }
}
