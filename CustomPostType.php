<?php

namespace JazzMan\Post;

use JazzMan\Pluralizer\Pluralizer;

/**
 * Class CustomPostType.
 */
class CustomPostType
{
    /**
     * @var string
     */
    private static $archive_post_type = 'hdptap_cpt_archive';
    /**
     * @var bool
     */
    private static $add_archive_page = false;
    /**
     * @var string
     */
    public $post_type;
    /**
     * @var string
     */
    public $post_type_name;
    /**
     * @var string[]
     */
    private $exisiting_taxonomies;
    /**
     * @var array
     */
    private $filters;
    /**
     * @var array
     */
    private $populate_columns;

    /**
     * @var array<string,string>
     */
    private $current_columns = [];
    /**
     * @var array<string,array>
     */
    private $sortable;
    /**
     * @var array<string,string|array>
     */
    private $post_type_options;
    /**
     * @var string[]
     */
    private $taxonomies;
    /**
     * @var array<string,array>
     */
    private $taxonomy_settings;
    /**
     * @var array
     */
    private $columns;
    /**
     * @var string
     */
    private $post_type_singular;
    /**
     * @var string
     */
    private $post_type_plural;

    /**
     * CustomPostType constructor.
     *
     * @param  string  $post_type_name
     * @param  array<string,string|array>  $options
     */
    public function __construct(string $post_type_name, array $options = [])
    {
        $this->initPostTypeConfig($post_type_name);

        $this->post_type_options = $options;

        add_action('init', [__CLASS__, 'registerCptArchivePostType']);
        add_action('registered_post_type', [__CLASS__, 'createArchivePages'], 10, 2);
        add_action('parent_file', [__CLASS__, 'adminMenuCorrection']);
        add_action('admin_menu', [__CLASS__, 'addAdminMenuArchivePages'], 99);
        add_filter('get_the_archive_title', [__CLASS__, 'archiveTitle'], 10, 1);
        add_filter('get_the_archive_description', [__CLASS__, 'archiveDescription'], 10, 1);
        add_filter('pre_wp_unique_post_slug', [__CLASS__, 'fixArchivePostTypeSlug'], 10, 5);

        add_action('init', [$this, 'registerTaxonomies']);
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerExisitingTaxonomies']);
        add_filter("manage_edit-{$this->post_type}_columns", [$this, 'addAdminColumns']);
        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'populateAdminColumns'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'addTaxonomyFilters']);
        add_filter('post_updated_messages', [$this, 'updatedMessages']);
        add_filter('bulk_post_updated_messages', [$this, 'bulkUpdatedMessages'], 10, 2);
        add_filter("manage_{$this->post_type}_posts_columns", [$this, 'setCurrentColumns'], PHP_INT_MAX);
    }

    private function initPostTypeConfig(string $post_type_name): void
    {
        $this->post_type_name = $post_type_name;

        $this->post_type = sanitize_key($this->post_type_name);

        $human_friendly = app_get_human_friendly($this->post_type_name);

        $this->post_type_singular = Pluralizer::singular($human_friendly);
        $this->post_type_plural = Pluralizer::plural($human_friendly);
    }

    public static function archiveDescription(string $desc = ''): string
    {
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

    public static function archiveTitle(string $title = ''): string
    {
        // only proceed if this is a post type archive.
        if (!is_post_type_archive()) {
            return $title;
        }
        // get the current post type.
        $current_post_type = get_queried_object()->name;
        // get the post type archive title for this post type.
        $archive_title = cpt_get_post_type_archive_title($current_post_type);

        if (!empty($archive_title)) {
            return apply_filters('the_title', $archive_title);
        }

        return $title;
    }

    public static function addAdminMenuArchivePages()
    {
        $post_type_object = get_post_type_object(self::$archive_post_type);

        if (null !== $post_type_object) {
            /** @var \WP_Post[] $post_types */
            $post_types = get_posts(
                [
                    'post_type' => self::$archive_post_type,
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
                        \sprintf("{$post_type_object->_edit_link}&action=edit", $post_type->ID),
                        false
                    );
                }
            }
        }
    }

    /**
     * @param  string  $parent_file
     *
     * @return string
     */
    public static function adminMenuCorrection(string $parent_file = '')
    {
        global $current_screen;
        $request = app_get_request_data();

        $post_id = $request->getDigits('post');
        // if this is a post edit screen for the archive page post type.
        if ($post_id && 'post' === $current_screen->base && self::$archive_post_type === $current_screen->post_type) {
            // get the plugin options.

            $post = get_post($post_id);

            // if we have an archive post type returned.
            if (!empty($post)) {
                // set the parent file to the archive post type.
                $parent_file = sprintf('edit.php?post_type=%s', esc_attr($post->post_name));
            }
        }

        return $parent_file;
    }

    public static function registerCptArchivePostType()
    {
        if (!post_type_exists(self::$archive_post_type)) {
            /**
             * Lets register the conditions post type
             * post type name is docp_condition.
             */
            $labels = cpt_get_post_type_labels('Archive Pages');

            self::$add_archive_page = false;

            $supports = apply_filters(
                'hdptap_cpt_archive_supports',
                [
                    'title',
                    'editor',
                    'thumbnail',
                ]
            );

            register_post_type(
                self::$archive_post_type,
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
     * @param null|string $original_slug
     * @param string      $slug
     * @param int         $post_ID
     * @param string      $post_status
     * @param string      $post_type
     *
     * @return string
     */
    public static function fixArchivePostTypeSlug(
        $original_slug,
        $slug,
        $post_ID,
        $post_status,
        $post_type
    ) {
        if ($post_type === self::$archive_post_type) {
            return $slug;
        }

        return $original_slug;
    }

    /**
     * @param string        $post_type
     * @param \WP_Post_Type $args
     */
    public static function createArchivePages($post_type, $args)
    {
        if (self::$add_archive_page && is_admin()) {
            // if this is the archive pages post type - do nothing.
            if (self::$archive_post_type === $post_type) {
                return;
            }

            // if this post type is not supposed to support an archive - do nothing.
            if (empty($args->has_archive)) {
                return;
            }

            $post_type_archive_id = cpt_get_post_type_archive_post_id($post_type);

            if (false === $post_type_archive_id) {
                $postarr = [
                    'post_type' => self::$archive_post_type,
                    'post_title' => $args->labels->name,
                    'post_status' => 'publish',
                    'post_name' => $post_type,
                ];

                wp_insert_post($postarr, true);
            }
        }
    }

    /**
     * @param  string[]  $columns
     * @return array
     */
    public function setCurrentColumns(array $columns): array
    {
        $this->current_columns = $columns;

        return $columns;
    }

    public function setPopulateColumns(string $column_name, callable $callback): void
    {
        $this->populate_columns[$column_name] = $callback;
    }

    public function setColumns(array $columns = []): void
    {
        $this->columns = $columns;
    }

    public function setFilters(array $filters = []): void
    {
        $this->filters = $filters;
    }

    public function registerPostType()
    {
        if (!post_type_exists($this->post_type)) {
            self::$add_archive_page = true;

            $options = $this->getPostTypeOptions($this->post_type_options);

            register_post_type($this->post_type_name, $options);
        }
    }

    /**
     * @param  array  $options
     * @return array
     */
    private function getPostTypeOptions(array $options = [])
    {
        $defaults = [
            'labels' => cpt_get_post_type_labels($this->post_type_name),
            'public' => true,
            'show_in_rest' => true,
        ];

        if (!empty($this->taxonomies)) {
            $defaults['taxonomies'] = $this->taxonomies;
        }

        return array_replace_recursive($defaults, $options);
    }

    /**
     * @param  string  $taxonomy_name
     * @param  array<string,string>  $options
     */
    public function registerTaxonomy(string $taxonomy_name, array $options = []): void
    {
        $taxonomy_name = sanitize_key($taxonomy_name);

        $defaults = [
            'labels' => cpt_get_taxonomy_labels($taxonomy_name),
            'hierarchical' => true,
            'show_in_rest' => true,
        ];
        $this->taxonomies[] = $taxonomy_name;
        $this->taxonomy_settings[$taxonomy_name] = array_replace_recursive($defaults, $options);
    }

    public function registerTaxonomies(): void
    {
        if (\is_array($this->taxonomy_settings)) {
            foreach ($this->taxonomy_settings as $taxonomy_name => $options) {
                if (taxonomy_exists($taxonomy_name)) {
                    $this->exisiting_taxonomies[] = $taxonomy_name;
                } else {
                    register_taxonomy($taxonomy_name, $this->post_type, $options);
                }
            }
        }
    }


    public function registerExisitingTaxonomies(): void
    {
        if (\is_array($this->exisiting_taxonomies)) {
            foreach ($this->exisiting_taxonomies as $taxonomy_name) {
                register_taxonomy_for_object_type($taxonomy_name, $this->post_type);
            }
        }
    }

    /**
     * @param array<string,string> $columns
     *
     * @return array
     */
    public function addAdminColumns(array $columns = []): array
    {
        if (null === $this->columns) {
            $new_columns = [];
            $after = $this->getColumnPositionAfter();

            foreach ($columns as $key => $title) {
                $new_columns[$key] = $title;
                if ($key === $after && \is_array($this->taxonomies)) {
                    foreach ($this->taxonomies as $tax) {
                        if ('category' !== $tax && 'post_tag' !== $tax) {
                            $taxonomy_object = get_taxonomy($tax);
                            $new_columns[$tax] = esc_attr($taxonomy_object->labels->name);
                        }
                    }
                }
            }

            return $new_columns;
        }

        return $this->columns;
    }

    private function getColumnPositionAfter(): string
    {
        $after = '';

        switch (true) {
            case 'post' === $this->post_type && \is_array($this->taxonomies):
                if (\in_array('post_tag', $this->taxonomies)) {
                    $after = 'tags';
                } elseif (\in_array('category', $this->taxonomies)) {
                    $after = 'categories';
                }

                break;

            case post_type_supports($this->post_type, 'author'):
                $after = 'author';

                break;

            default:
                $after = 'title';

                break;
        }

        return $after;
    }

    private function printTermListColumn(int $post_id, string $column): void
    {
        global $post;
        /** @var \WP_Term[] $terms */
        $terms = get_the_terms($post_id, $column);
        if (!empty($terms)) {
            $output = '';
            foreach ($terms as $term) {
                $output .= sprintf(
                    '<a title="%1$s: %2$s" href="%3$s">%2$s</a>',
                    esc_attr(app_get_human_friendly($term->taxonomy)),
                    esc_attr($term->name),
                    esc_url(
                        sprintf(
                            'edit.php?post_type=%s&%s=%s',
                            esc_attr($post->post_type),
                            $column,
                            $term->slug
                        )
                    )
                );
            }

            printf(
                '<div title="%s List">%s<br class="clear"></div>',
                esc_attr(app_get_human_friendly($column)),
                $output
            );
        } else {
            $taxonomy_object = get_taxonomy($column);

            if ($taxonomy_object){
                printf('No %s', esc_attr($taxonomy_object->labels->name));
            }
        }
    }

    /**
     * @param  int  $post_id
     */
    private function printIconColumn(int $post_id): void
    {
        global $post;

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

    /**
     * @param  int  $post_id
     * @param  string  $meta_key
     */

    private function printMetaColumn(int $post_id, string $meta_key): void
    {
        global $post;

        $meta = get_post_meta($post_id, $meta_key, true);

        if (!empty($meta) && is_string($meta)) {
            printf(
                '<span title="%s Meta: %s">%s</span>',
                esc_attr($post->post_title),
                esc_attr($meta_key),
                esc_attr($meta)
            );
        }
    }

    /**
     * @param  string  $column
     * @param  int  $post_id
     */
    public function populateAdminColumns(string $column = '', int $post_id = 0): void
    {
        if (!empty($this->current_columns) && !empty($this->current_columns[$column])) {
            return;
        }
        global $post;

        switch ($column) {
            case taxonomy_exists($column):
                $this->printTermListColumn($post_id, $column);

                break;

            case 'post_id':
                printf(
                    '<span title="%s ID: %s">%s</span>',
                    esc_attr($post->post_title),
                    esc_attr($post->ID),
                    esc_attr($post->ID)
                );

                break;

            case 0 === strpos($column, 'meta_'):
                $this->printMetaColumn($post_id, ltrim($column, 'meta_'));

                break;

            case 'icon':
                $this->printIconColumn($post_id);

                break;

            default:
                if (!empty($this->populate_columns) && !empty($this->populate_columns[$column])) {
                    \call_user_func($this->populate_columns[$column], $column, $post);
                }

                break;
        }
    }

    public function addTaxonomyFilters(): void
    {
        global $typenow;
        if ($typenow === $this->post_type) {
            $filters = !empty($this->filters) ? $this->filters : $this->taxonomies;
            if (!empty($filters)) {
                $request = app_get_request_data();

                foreach ($filters as $tax_slug) {
                    $tax = get_taxonomy($tax_slug);
                    /** @var \WP_Error|\WP_Term[] $terms */
                    $terms = get_terms($tax_slug, ['orderby' => 'name', 'hide_empty' => false]);
                    if (!is_wp_error($terms) && !empty($terms)) {
                        $options = sprintf('<option value="0">Show all %s</option>', $tax ? esc_attr($tax->label) : '');

                        foreach ($terms as $term) {
                            $options .= sprintf(
                                '<option value="%s" %s>%s (%s)</option>',
                                esc_attr($term->slug),
                                selected($request->get($tax_slug), $term->slug, false),
                                esc_attr($term->name),
                                esc_attr((string) $term->count)
                            );
                        }
                        printf(
                            '<select name="%s" class="postform">%s</select>',
                            esc_attr($tax_slug),
                            $options
                        );
                    }
                }
            }
        }
    }

    /**
     * @param  array<string,array>  $columns
     */

    public function setSortable(array $columns = []): void
    {
        $this->sortable = $columns;

        add_filter("manage_edit-{$this->post_type}_sortable_columns", [$this, 'makeColumnsSortable']);
        add_action('load-edit.php', [$this, 'loadAdit']);
    }

    /**
     * @param array<string,array> $columns
     *
     * @return array<string,array>
     */
    public function makeColumnsSortable(array $columns = []): array
    {
        $sortable_columns = [];
        foreach ($this->sortable as $column => $values) {
            $sortable_columns[$column] = $values[0];
        }

        return array_merge($sortable_columns, $columns);
    }

    /**
     * Load edit
     * Sort columns only on the edit.php page when requested.
     *
     * @see http://codex.wordpress.org/Plugin_API/Filter_Reference/request
     */
    public function loadAdit(): void
    {
        add_filter('request', [$this, 'sortColumns']);
    }

    /**
     * @param array<string,string> $vars
     *
     * @return array<string,string>
     */
    public function sortColumns(array $vars): array
    {
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

    public function setMenuIcon(string $icon = 'dashicons-admin-page'): void
    {
        $this->post_type_options['menu_icon'] = false !== stripos($icon, 'dashicons') ? $icon : 'dashicons-admin-page';
    }

    /**
     * @param array<string,array> $messages
     *
     * @return array<string,array>
     */
    public function updatedMessages(array $messages = []): array
    {
        $post = get_post();

        $request = app_get_request_data();

        $revision = $request->getDigits('revision');

        $messages[$this->post_type_name] = [
            0 => '',
            1 => sprintf('%s updated.', esc_attr($this->post_type_singular)),
            2 => 'Custom field updated.',
            3 => 'Custom field deleted.',
            4 => sprintf('%s updated.', esc_attr($this->post_type_singular)),
            5 => $revision ? sprintf(
                '%1$s restored to revision from %2$s',
                esc_attr($this->post_type_singular),
                wp_post_revision_title($revision, false)
            ) : false,
            6 => sprintf('%s updated.', esc_attr($this->post_type_singular)),
            7 => sprintf('%s saved.', esc_attr($this->post_type_singular)),
            8 => sprintf('%s submitted.', esc_attr($this->post_type_singular)),
            9 => sprintf(
                '%s scheduled for: <strong>%s</strong>.',
                esc_attr($this->post_type_singular),
                $post ? date_i18n('M j, Y @ G:i', strtotime($post->post_date)) : ''
            ),
            10 => sprintf('%s draft updated.', esc_attr($this->post_type_singular)),
        ];

        return $messages;
    }

    /**
     * @param  array<string,array>  $bulk_messages
     * @param  array<string,int>  $bulk_counts
     * @return array<string,string>
     */
    public function bulkUpdatedMessages(array $bulk_messages = [], array $bulk_counts = []): array
    {
        $bulk_messages[$this->post_type_name] = [
            'updated' => _n(
                "%s {$this->post_type_singular} updated.",
                "%s {$this->post_type_plural} updated.",
                $bulk_counts['updated']
            ),
            'locked' => _n(
                "%s {$this->post_type_singular} not updated, somebody is editing it.",
                "%s {$this->post_type_plural} not updated, somebody is editing them.",
                $bulk_counts['locked']
            ),
            'deleted' => _n(
                "%s {$this->post_type_singular} permanently deleted.",
                "%s {$this->post_type_plural} permanently deleted.",
                $bulk_counts['deleted']
            ),
            'trashed' => _n(
                "%s {$this->post_type_singular} moved to the Trash.",
                "%s {$this->post_type_plural} moved to the Trash.",
                $bulk_counts['trashed']
            ),
            'untrashed' => _n(
                "%s {$this->post_type_singular} restored from the Trash.",
                "%s {$this->post_type_plural} restored from the Trash.",
                $bulk_counts['untrashed']
            ),
        ];

        return $bulk_messages;
    }
}
