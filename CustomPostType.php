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
    private static $textdomain = 'cpt';
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
     * @var array
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
     * @var array
     */
    private $sortable;
    /**
     * @var array
     */
    private $post_type_options;
    /**
     * @var array
     */
    private $taxonomies;
    /**
     * @var array
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
     * @param string $post_type_names
     * @param array $options
     */
    public function __construct($post_type_names, array $options = [])
    {
        $this->post_type_name = $post_type_names;

        $this->post_type = sanitize_key($this->post_type_name);

        $human_friendly = cpt_get_human_friendly($this->post_type_name);

        $this->post_type_singular = Pluralizer::singular($human_friendly);
        $this->post_type_plural = Pluralizer::plural($human_friendly);

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
    }

    /**
     * @param string $desc
     *
     * @return string
     */
    public static function archiveDescription(string $desc = '')
    {
        // only proceed if this is a post type archive.
        if (! is_post_type_archive()) {
            return $desc;
        }
        // get the current post type.
        $current_post_type = get_queried_object()->name;

        // get the post type archive desc for this post type.

        $post_type_archive_desc = cpt_get_post_type_archive_content($current_post_type);

        // if we have a desc.

        if (! empty($post_type_archive_desc)) {
            $desc = apply_filters('the_content', $post_type_archive_desc);
        }

        return $desc;
    }

    /**
     * @param string $title
     * @return string
     */
    public static function archiveTitle(string $title = '')
    {
        // only proceed if this is a post type archive.
        if (! is_post_type_archive()) {
            return $title;
        }
        // get the current post type.
        $current_post_type = get_queried_object()->name;
        // get the post type archive title for this post type.
        $post_type_archive_title = cpt_get_post_type_archive_title($current_post_type);

        if (! empty($post_type_archive_title)) {
            return apply_filters('the_title', $post_type_archive_title);
        }

        return $title;
    }

    public static function addAdminMenuArchivePages()
    {
        $post_type_object = get_post_type_object(self::$archive_post_type);

        if (null !== $post_type_object) {
            /** @var \WP_Post[] $post_types */
            $post_types = get_posts([
                'post_type' => self::$archive_post_type,
                'post_status' => 'publish',
                'numberposts' => -1,
            ]);

            if (! empty($post_types)) {
                foreach ($post_types as $post_type) {
                    $edit_link = \sprintf("{$post_type_object->_edit_link}&action=edit", $post_type->ID);

                    // add the menu item for this post type.
                    add_submenu_page("edit.php?post_type={$post_type->post_name}",
                        __('Archive Page', self::$textdomain), __('Archive Page', self::$textdomain), 'edit_posts',
                        $edit_link, false);
                }
            }
        }
    }

    /**
     * @param string $parent_file
     * @return string
     */
    public static function adminMenuCorrection(string $parent_file = '')
    {
        global $current_screen;
        // if this is a post edit screen for the archive page post type.
        if (! empty($_GET['post']) && 'post' === $current_screen->base && self::$archive_post_type === $current_screen->post_type) {
            // get the plugin options.

            $post = get_post((int) $_GET['post']);

            // if we have an archive post type returned.
            if (! empty($post)) {
                // set the parent file to the archive post type.
                $parent_file = 'edit.php?post_type=' . $post->post_name;
            }
        }

        return $parent_file;
    }

    public static function registerCptArchivePostType()
    {
        if (! post_type_exists(self::$archive_post_type)) {
            /**
             * Lets register the conditions post type
             * post type name is docp_condition.
             */
            $labels = cpt_get_post_type_labels('Archive Pages');

            self::$add_archive_page = false;

            $supports = apply_filters('hdptap_cpt_archive_supports', [
                'title',
                'editor',
                'thumbnail',
            ]);

            register_post_type(self::$archive_post_type, [
                'description' => __('Archive posts associated with each post type.', self::$textdomain),
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
            ]);
        }
    }

    /**
     * @param string|null $original_slug
     * @param string $slug
     * @param int $post_ID
     * @param string $post_status
     * @param string $post_type
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
     * @param string $post_type
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
     * @param string $column_name
     * @param callable $callback
     */
    public function setPopulateColumns(string $column_name, callable $callback): void
    {
        $this->populate_columns[$column_name] = $callback;
    }

    /**
     * @param array $columns
     */
    public function setColumns(array $columns = []): void
    {
        $this->columns = $columns;
    }

    /**
     * @param array $filters
     */
    public function setFilters(array $filters = []): void
    {
        $this->filters = $filters;
    }

    public function registerPostType()
    {
        if (! post_type_exists($this->post_type)) {
            self::$add_archive_page = true;

            $options = $this->getPostTypeOptions($this->post_type_options);

            register_post_type($this->post_type_name, $options);
        }
    }

    /**
     * @param array $options
     * @return array
     */
    private function getPostTypeOptions(array $options = [])
    {
        $defaults = [
            'labels' => cpt_get_post_type_labels($this->post_type_name),
            'public' => true,
            'show_in_rest' => true,
        ];

        if (! empty($this->taxonomies)) {
            $defaults['taxonomies'] = $this->taxonomies;
        }

        return \array_replace_recursive($defaults, $options);
    }

    /**
     * @param string $taxonomy_name
     * @param array $options
     */
    public function registerTaxonomy(string $taxonomy_name, array $options = [])
    {
        $taxonomy_name = sanitize_key($taxonomy_name);

        $defaults = [
            'labels' => cpt_get_taxonomy_labels($taxonomy_name),
            'hierarchical' => true,
            'show_in_rest' => true,
        ];
        $this->taxonomies[] = $taxonomy_name;
        $this->taxonomy_settings[$taxonomy_name] = \array_replace_recursive($defaults, $options);
    }

    public function registerTaxonomies()
    {
        if (\is_array($this->taxonomy_settings)) {
            foreach ($this->taxonomy_settings as $taxonomy_name => $options) {
                if (! taxonomy_exists($taxonomy_name)) {
                    register_taxonomy($taxonomy_name, $this->post_type, $options);
                } else {
                    $this->exisiting_taxonomies[] = $taxonomy_name;
                }
            }
        }
    }

    public function registerExisitingTaxonomies()
    {
        if (\is_array($this->exisiting_taxonomies)) {
            foreach ($this->exisiting_taxonomies as $taxonomy_name) {
                register_taxonomy_for_object_type($taxonomy_name, $this->post_type);
            }
        }
    }

    /**
     * @param array $columns
     * @return array
     */
    public function addAdminColumns(array $columns = [])
    {
        if (null === $this->columns) {
            $new_columns = [];

            $after = '';

            if ('post' === $this->post_type && \is_array($this->taxonomies)) {
                if (\in_array('post_tag', $this->taxonomies, true)) {
                    $after = 'tags';
                } elseif (\in_array('category', $this->taxonomies, true)) {
                    $after = 'categories';
                }
            } elseif (post_type_supports($this->post_type, 'author')) {
                $after = 'author';
            } else {
                $after = 'title';
            }
            foreach ($columns as $key => $title) {
                $new_columns[$key] = $title;
                if ($key === $after && \is_array($this->taxonomies)) {
                    foreach ($this->taxonomies as $tax) {
                        if ('category' !== $tax && 'post_tag' !== $tax) {
                            $taxonomy_object = get_taxonomy($tax);
                            $new_columns[$tax] = \sprintf(__('%s', self::$textdomain), $taxonomy_object->labels->name);
                        }
                    }
                }
            }
            $columns = $new_columns;
        } else {
            $columns = $this->columns;
        }

        return $columns;
    }

    /**
     * @param string $column
     * @param int $post_id
     */
    public function populateAdminColumns(string $column = '', int $post_id = 0)
    {
        global $post;
        switch ($column) {
            case taxonomy_exists($column):
                $terms = get_the_terms($post_id, $column);
                if (! empty($terms)) {
                    $output = [];
                    foreach ((array) $terms as $term) {
                        $term_url = add_query_arg([
                            'post_type' => $post->post_type,
                            $column => $term->slug,
                        ], 'edit.php');

                        $term_name = sanitize_term_field('name', $term->name, $term->term_id, $column, 'display');

                        $output[] = \sprintf('<a href="%s">%s</a>', esc_url($term_url), esc_html($term_name));
                    }
                    echo \implode(', ', $output);
                } else {
                    $taxonomy_object = get_taxonomy($column);
                    \printf(__('No %s', self::$textdomain), esc_attr($taxonomy_object->labels->name));
                }
                break;
            case 'post_id':
                echo esc_attr($post->ID);
                break;
            case 0 === \strpos($column, 'meta_'):
                $meta_column = \substr($column, 5);
                $meta = get_post_meta($post->ID, $meta_column, true);
                echo '' !== \trim($meta) ? esc_attr($meta) : '&mdash;';
                break;
            case 'icon':
                $link = esc_url(add_query_arg([
                    'post' => $post->ID,
                    'action' => 'edit',
                ], 'post.php'));

                if (has_post_thumbnail()) {
                    $thumbnail = get_the_post_thumbnail($post_id, [60, 60]);
                    echo "<a href='{$link}'>{$thumbnail}</a>";
                } else {
                    $default_icon = esc_url(includes_url('images/crystal/default.png'));

                    \printf('<a href="%s"><img src="%s" alt="%s" /></a>', $link, $default_icon, esc_attr($post->post_title));
                }
                break;
            default:
                if (! empty($this->populate_columns) && ! empty($this->populate_columns[$column])) {
                    \call_user_func($this->populate_columns[$column], $column, $post);
                }

                break;
        }
    }

    public function addTaxonomyFilters()
    {
        global $typenow;
        if ($typenow === $this->post_type) {
            $filters = \is_array($this->filters) ? $this->filters : $this->taxonomies;
            if (! empty($filters)) {
                foreach ($filters as $tax_slug) {
                    $tax = get_taxonomy($tax_slug);
                    static $args = [
                        'orderby' => 'name',
                        'hide_empty' => false,
                        ];
                    $terms = get_terms($tax_slug, $args);
                    if ($terms) {
                        $show_all = \sprintf(__('Show all %s', self::$textdomain), esc_attr($tax->label));

                        \printf(' &nbsp;<select name="%s" class="postform">', esc_attr($tax_slug));
                        \printf('<option value="0">%s</option>', $show_all);
                        foreach ((array) $terms as $term) {
                            if (isset($_GET[$tax_slug]) && $_GET[$tax_slug] === $term->slug) {
                                \printf('<option value="%s" selected="selected">%s (%s)</option>', esc_attr($term->slug),
                                    esc_attr($term->name), esc_attr($term->count));
                            } else {
                                \printf('<option value="%s">%s (%s)</option>', esc_attr($term->slug), $term->name, $term->count);
                            }
                        }
                        echo '</select>&nbsp;';
                    }
                }
            }
        }
    }

    /**
     * @param array $columns
     */
    public function setSortable(array $columns = null)
    {
        $this->sortable = $columns;

        add_filter("manage_edit-{$this->post_type}_sortable_columns", [$this, 'makeColumnsSortable']);
        add_action('load-edit.php', [$this, 'loadAdit']);
    }

    /**
     * @param array $columns
     * @return array
     */
    public function makeColumnsSortable(array $columns = [])
    {
        $sortable_columns = [];
        foreach ($this->sortable as $column => $values) {
            $sortable_columns[$column] = $values[0];
        }

        return \array_merge($sortable_columns, $columns);
    }

    /**
     * Load edit
     * Sort columns only on the edit.php page when requested.
     *
     * @see http://codex.wordpress.org/Plugin_API/Filter_Reference/request
     */
    public function loadAdit()
    {
        add_filter('request', [$this, 'sortColumns']);
    }

    /**
     * @param array $vars
     *
     * @return array
     */
    public function sortColumns($vars)
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

        if (! empty($_vars)) {
            $vars = \array_merge([], ...$_vars);
        }

        return $vars;
    }

    /**
     * @param string $icon
     */
    public function setMenuIcon($icon = 'dashicons-admin-page')
    {
        if (\is_string($icon) && false !== \stripos($icon, 'dashicons')) {
            $this->post_type_options['menu_icon'] = $icon;
        } else {
            $this->post_type_options['menu_icon'] = 'dashicons-admin-page';
        }
    }

    /**
     * @param array $messages
     * @return array
     */
    public function updatedMessages(array $messages = [])
    {
        $post = get_post();

        $messages[$this->post_type_name] = [
            0 => '',
            1 => \sprintf(__('%s updated.', self::$textdomain), $this->post_type_singular),
            2 => __('Custom field updated.', self::$textdomain),
            3 => __('Custom field deleted.', self::$textdomain),
            4 => \sprintf(__('%s updated.', self::$textdomain), $this->post_type_singular),
            5 => isset($_GET['revision']) ? \sprintf(__('%2$s restored to revision from %1$s', self::$textdomain),
                wp_post_revision_title((int) $_GET['revision'], false), $this->post_type_singular) : false,
            6 => \sprintf(__('%s updated.', self::$textdomain), $this->post_type_singular),
            7 => \sprintf(__('%s saved.', self::$textdomain), $this->post_type_singular),
            8 => \sprintf(__('%s submitted.', self::$textdomain), $this->post_type_singular),
            9 => \sprintf(__('%2$s scheduled for: <strong>%1$s</strong>.', self::$textdomain),
                date_i18n(__('M j, Y @ G:i', self::$textdomain), \strtotime($post->post_date)),
                $this->post_type_singular),
            10 => \sprintf(__('%s draft updated.', self::$textdomain), $this->post_type_singular),
        ];

        return $messages;
    }

    /**
     * @param array $bulk_messages
     * @param array $bulk_counts
     * @return mixed
     */
    public function bulkUpdatedMessages(array $bulk_messages = [], array $bulk_counts = [])
    {
        $bulk_messages[$this->post_type_name] = [
            'updated' => _n("%s {$this->post_type_singular} updated.", "%s {$this->post_type_plural} updated.",
                $bulk_counts['updated']),
            'locked' => _n("%s {$this->post_type_singular} not updated, somebody is editing it.",
                "%s {$this->post_type_plural} not updated, somebody is editing them.", $bulk_counts['locked']),
            'deleted' => _n("%s {$this->post_type_singular} permanently deleted.",
                "%s {$this->post_type_plural} permanently deleted.", $bulk_counts['deleted']),
            'trashed' => _n("%s {$this->post_type_singular} moved to the Trash.",
                "%s {$this->post_type_plural} moved to the Trash.", $bulk_counts['trashed']),
            'untrashed' => _n("%s {$this->post_type_singular} restored from the Trash.",
                "%s {$this->post_type_plural} restored from the Trash.", $bulk_counts['untrashed']),
        ];

        return $bulk_messages;
    }
}
