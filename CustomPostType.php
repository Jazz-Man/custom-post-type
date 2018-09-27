<?php

namespace JazzMan\Post;

use JazzMan\Pluralizer\Pluralizer;

/**
 * Class CustomPostType
 *
 * @package JazzMan\Post
 */
class CustomPostType
{

    public $post_type;

    /**
     * @var
     */
    public $post_type_name;
    /**
     * @var array
     */
    private $options;
    /**
     * @var
     */
    public $exisiting_taxonomies;
    /**
     * @var
     */
    public $filters;
    /**
     * @var
     */
    public $custom_populate_columns;
    /**
     * @var array
     */
    public $sortable;
    /**
     * @var string
     */
    private $singular;
    /**
     * @var string
     */
    private $plural;
    /**
     * @var
     */
    private $taxonomies;
    /**
     * @var
     */
    private $taxonomy_settings;
    /**
     * @var
     */
    private $columns;
    /**
     * @var string
     */
    private $textdomain = 'cpt';
    private $human_friendly;

    /**
     * CustomPostType constructor.
     *
     * @param string $post_type_names
     * @param array  $options
     */
    public function __construct($post_type_names, array $options = [])
    {
        $this->post_type_name = $post_type_names;
        $this->human_friendly = $this->get_human_friendly($this->post_type_name);
        $this->plural         = $this->get_plural();
        $this->singular       = $this->get_singular();

        $this->post_type = sanitize_key($this->post_type_name);

        if ( ! empty($options)) {
            $this->set_post_type_options($options);
        }
        add_action('init', [$this, 'register_taxonomies']);
        add_action('init', [$this, 'register_post_type']);
        add_action('init', [$this, 'register_exisiting_taxonomies']);
        add_filter("manage_edit-{$this->post_type}_columns", [$this, 'add_admin_columns']);
        add_action("manage_{$this->post_type}_posts_custom_column", [$this, 'populate_admin_columns'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'add_taxonomy_filters']);
        add_filter('post_updated_messages', [$this, 'updated_messages']);
        add_filter('bulk_post_updated_messages', [$this, 'bulk_updated_messages'], 10, 2);
    }

    /**
     * @param string|null $name
     *
     * @return string
     */
    private function get_human_friendly($name = null)
    {
        if (null === $name) {
            $name = $this->post_type_name;
        }

        return ucwords(strtolower(str_replace(['-', '_'], ' ', $name)));
    }

    /**
     *
     * @param null|string $name
     *
     * @return string
     */
    private function get_plural($name = null)
    {
        if ($name === null) {
            $name = $this->human_friendly;
        }

        return Pluralizer::plural($name);
    }

    /**
     * @param null|string $name
     *
     * @return string
     */
    private function get_singular($name = null)
    {
        if ($name === null) {
            $name = $this->human_friendly;
        }

        return Pluralizer::singular($name);
    }

    /**
     * @return array
     */
    public function get_post_type_options()
    {
        return $this->options;
    }

    /**
     * @param array $options
     */
    public function set_post_type_options(array $options = [])
    {
        $labels = [
            'name'               => sprintf(__('%s', $this->textdomain), $this->plural),
            'singular_name'      => sprintf(__('%s', $this->textdomain), $this->singular),
            'menu_name'          => sprintf(__('%s', $this->textdomain), $this->plural),
            'all_items'          => sprintf(__('%s', $this->textdomain), $this->plural),
            'add_new'            => __('Add New', $this->textdomain),
            'add_new_item'       => sprintf(__('Add New %s', $this->textdomain), $this->singular),
            'edit_item'          => sprintf(__('Edit %s', $this->textdomain), $this->singular),
            'new_item'           => sprintf(__('New %s', $this->textdomain), $this->singular),
            'view_item'          => sprintf(__('View %s', $this->textdomain), $this->singular),
            'search_items'       => sprintf(__('Search %s', $this->textdomain), $this->plural),
            'not_found'          => sprintf(__('No %s found', $this->textdomain), $this->plural),
            'not_found_in_trash' => sprintf(__('No %s found in Trash', $this->textdomain), $this->plural),
            'parent_item_colon'  => sprintf(__('Parent %s:', $this->textdomain), $this->singular),
        ];

        $defaults = [
            'labels'       => $labels,
            'public'       => true,
            'show_in_rest' => true,
        ];

        $this->options = array_replace_recursive($defaults, $options);
    }

    /**
     * @param $textdomain
     */
    public function set_textdomain($textdomain)
    {
        $this->textdomain = $textdomain;
    }

    /**
     * @param $var
     *
     * @return bool
     */
    public function get($var)
    {
        return $this->$var ?: false;
    }

    public function register_post_type()
    {
        if ( ! post_type_exists($this->post_type)) {
            register_post_type($this->post_type_name, $this->get_post_type_options());
        }
    }

    /**
     * @param       $taxonomy_names
     * @param array $options
     */
    public function register_taxonomy($taxonomy_names, array $options = [])
    {
        $taxonomy_name  = sanitize_key($taxonomy_names);
        $human_friendly = $this->get_human_friendly($taxonomy_names);
        $singular       = $this->get_singular($human_friendly);
        $plural         = $this->get_plural($human_friendly);

        $labels                                  = [
            'name'                       => sprintf(__('%s', $this->textdomain), $plural),
            'singular_name'              => sprintf(__('%s', $this->textdomain), $singular),
            'menu_name'                  => sprintf(__('%s', $this->textdomain), $plural),
            'all_items'                  => sprintf(__('All %s', $this->textdomain), $plural),
            'edit_item'                  => sprintf(__('Edit %s', $this->textdomain), $singular),
            'view_item'                  => sprintf(__('View %s', $this->textdomain), $singular),
            'update_item'                => sprintf(__('Update %s', $this->textdomain), $singular),
            'add_new_item'               => sprintf(__('Add New %s', $this->textdomain), $singular),
            'new_item_name'              => sprintf(__('New %s Name', $this->textdomain), $singular),
            'parent_item'                => sprintf(__('Parent %s', $this->textdomain), $plural),
            'parent_item_colon'          => sprintf(__('Parent %s:', $this->textdomain), $plural),
            'search_items'               => sprintf(__('Search %s', $this->textdomain), $plural),
            'popular_items'              => sprintf(__('Popular %s', $this->textdomain), $plural),
            'separate_items_with_commas' => sprintf(__('Seperate %s with commas', $this->textdomain), $plural),
            'add_or_remove_items'        => sprintf(__('Add or remove %s', $this->textdomain), $plural),
            'choose_from_most_used'      => sprintf(__('Choose from most used %s', $this->textdomain), $plural),
            'not_found'                  => sprintf(__('No %s found', $this->textdomain), $plural),
        ];
        $defaults                                = [
            'labels'       => $labels,
            'hierarchical' => true,
            'show_in_rest' => true,
        ];
        $this->taxonomies[]                      = $taxonomy_name;
        $this->taxonomy_settings[$taxonomy_name] = array_replace_recursive($defaults, $options);
    }

    public function register_taxonomies()
    {
        if (is_array($this->taxonomy_settings)) {
            foreach ((array)$this->taxonomy_settings as $taxonomy_name => $options) {
                if ( ! taxonomy_exists($taxonomy_name)) {
                    register_taxonomy($taxonomy_name, $this->post_type, $options);
                } else {
                    $this->exisiting_taxonomies[] = $taxonomy_name;
                }
            }
        }
    }

    public function register_exisiting_taxonomies()
    {
        if (is_array($this->exisiting_taxonomies)) {
            foreach ((array)$this->exisiting_taxonomies as $taxonomy_name) {
                register_taxonomy_for_object_type($taxonomy_name, $this->post_type);
            }
        }
    }

    /**
     * @param $columns
     *
     * @return array
     */
    public function add_admin_columns($columns)
    {
        if ($this->columns === null) {
            $new_columns = [];
            if ((is_array($this->taxonomies) && in_array('post_tag',
                        $this->taxonomies)) || $this->post_type === 'post') {
                $after = 'tags';
            } elseif ((is_array($this->taxonomies) && in_array('category',
                        $this->taxonomies)) || $this->post_type === 'post') {
                $after = 'categories';
            } elseif (post_type_supports($this->post_type, 'author')) {
                $after = 'author';
            } else {
                $after = 'title';
            }
            foreach ((array)$columns as $key => $title) {
                $new_columns[$key] = $title;
                if ($key === $after && is_array($this->taxonomies)) {
                    foreach ((array)$this->taxonomies as $tax) {
                        if ($tax !== 'category' && $tax !== 'post_tag') {
                            $taxonomy_object   = get_taxonomy($tax);
                            $new_columns[$tax] = sprintf(__('%s', $this->textdomain), $taxonomy_object->labels->name);
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
     * @param $column
     * @param $post_id
     */
    public function populate_admin_columns($column, $post_id)
    {
        global $post;
        switch ($column) {
            case taxonomy_exists($column):
                $terms = get_the_terms($post_id, $column);
                if ( ! empty($terms)) {
                    $output = [];
                    foreach ((array)$terms as $term) {
                        $output[] = sprintf('<a href="%s">%s</a>', esc_url(add_query_arg([
                            'post_type' => $post->post_type,
                            $column     => $term->slug,
                        ], 'edit.php')),
                            esc_html(sanitize_term_field('name', $term->name, $term->term_id, $column, 'display')));
                    }
                    echo implode(', ', $output);
                } else {
                    $taxonomy_object = get_taxonomy($column);
                    printf(__('No %s', $this->textdomain), $taxonomy_object->labels->name);
                }
                break;
            case 'post_id':
                echo $post->ID;
                break;
            case 0 === mb_strpos($column, 'meta_'):
                $meta_column = mb_substr($column, 5);
                $meta        = get_post_meta($post->ID, $meta_column, true);
                echo trim($meta) !== '' ? $meta : '&mdash;';
                break;
            case 'icon':
                $link = esc_url(add_query_arg([
                    'post'   => $post->ID,
                    'action' => 'edit',
                ], 'post.php'));
                if (has_post_thumbnail()) {
                    $thumbnail = get_the_post_thumbnail($post_id, [60, 60]);
                    echo "<a href='$link'>$thumbnail</a>";
                } else {
                    $default_icon = esc_url(includes_url('images/crystal/default.png'));

                    echo "<a href='$link'><img src='$default_icon' alt='$post->post_title' /></a>";
                }
                break;
            default:
                if (isset($this->custom_populate_columns) && is_array($this->custom_populate_columns)) {
                    if (isset($this->custom_populate_columns[$column]) && is_callable($this->custom_populate_columns[$column])) {
                        call_user_func($this->custom_populate_columns[$column], $column, $post);
                    }
                }
                break;
        }
    }

    /**
     * @param array $filters
     */
    public function filters(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function add_taxonomy_filters()
    {
        global $typenow;
        if ($typenow === $this->post_type) {
            $filters = is_array($this->filters) ? $this->filters : $this->taxonomies;
            if ( ! empty($filters)) {
                foreach ($filters as $tax_slug) {
                    $tax = get_taxonomy($tax_slug);
                    static $args = ['orderby' => 'name', 'hide_empty' => false];
                    $terms = get_terms($tax_slug, $args);
                    if ($terms) {
                        printf(' &nbsp;<select name="%s" class="postform">', $tax_slug);
                        printf('<option value="0">%s</option>',
                            sprintf(__('Show all %s', $this->textdomain), $tax->label));
                        foreach ((array)$terms as $term) {
                            if (isset($_GET[$tax_slug]) && $_GET[$tax_slug] === $term->slug) {
                                printf('<option value="%s" selected="selected">%s (%s)</option>', $term->slug,
                                    $term->name, $term->count);
                            } else {
                                printf('<option value="%s">%s (%s)</option>', $term->slug, $term->name, $term->count);
                            }
                        }
                        echo '</select>&nbsp;';
                    }
                }
            }
        }
    }

    /**
     * @param $columns
     */
    public function columns($columns)
    {
        if (isset($columns)) {
            $this->columns = $columns;
        }
    }

    /**
     * @param $column_name
     * @param $callback
     */
    public function populate_column($column_name, $callback)
    {
        $this->custom_populate_columns[$column_name] = $callback;
    }

    /**
     * @param array $columns
     */
    public function sortable(array $columns = null)
    {
        $this->sortable = $columns;

        add_filter("manage_edit-{$this->post_type}_sortable_columns", [$this, 'make_columns_sortable']);
        add_action('load-edit.php', [$this, 'load_edit']);
    }

    /**
     * @param     $callback
     * @param int $priority
     * @param int $accepted_args
     */
    public function save_post($callback, $priority = 10, $accepted_args = 2)
    {
        add_action("save_post_{$this->post_type}", $callback, $priority, $accepted_args);
    }

    /**
     * @param $columns
     *
     * @return array
     */
    public function make_columns_sortable($columns)
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
    public function load_edit()
    {
        add_filter('request', [$this, 'sort_columns']);
    }

    /**
     * @param array $vars
     *
     * @return array
     */
    public function sort_columns($vars)
    {
        foreach ($this->sortable as $column => $values) {
            $meta_key = $values[0];
            $orderby  = isset($values[1]) && true === $values[1] ? 'meta_value_num' : 'meta_value';
            if (isset($vars['post_type']) && $this->post_type === $vars['post_type'] && isset($vars['orderby']) && $meta_key === $vars['orderby']) {
                $vars = array_merge($vars, [
                    'meta_key' => $meta_key,
                    'orderby'  => $orderby,
                ]);
            }
        }

        return $vars;
    }

    /**
     * @param string $icon
     */
    public function menu_icon($icon = 'dashicons-admin-page')
    {
        if (is_string($icon) && mb_stripos($icon, 'dashicons') !== false) {
            $this->options['menu_icon'] = $icon;
        } else {
            $this->options['menu_icon'] = 'dashicons-admin-page';
        }
    }

    /**
     * @param array $messages
     *
     * @return array
     */
    public function updated_messages($messages)
    {
        $post = get_post();

        $messages[$this->post_type_name] = [
            0  => '',
            1  => sprintf(__('%s updated.', $this->textdomain), $this->singular),
            2  => __('Custom field updated.', $this->textdomain),
            3  => __('Custom field deleted.', $this->textdomain),
            4  => sprintf(__('%s updated.', $this->textdomain), $this->singular),
            5  => isset($_GET['revision']) ? sprintf(__('%2$s restored to revision from %1$s', $this->textdomain),
                wp_post_revision_title((int)$_GET['revision'], false), $this->singular) : false,
            6  => sprintf(__('%s updated.', $this->textdomain), $this->singular),
            7  => sprintf(__('%s saved.', $this->textdomain), $this->singular),
            8  => sprintf(__('%s submitted.', $this->textdomain), $this->singular),
            9  => sprintf(__('%2$s scheduled for: <strong>%1$s</strong>.', $this->textdomain),
                date_i18n(__('M j, Y @ G:i', $this->textdomain), strtotime($post->post_date)), $this->singular),
            10 => sprintf(__('%s draft updated.', $this->textdomain), $this->singular),
        ];

        return $messages;
    }

    /**
     * @param $bulk_messages
     * @param $bulk_counts
     *
     * @return mixed
     */
    public function bulk_updated_messages($bulk_messages, $bulk_counts)
    {
        $bulk_messages[$this->post_type_name] = [
            'updated'   => _n("%s {$this->singular} updated.", "%s {$this->plural} updated.", $bulk_counts['updated']),
            'locked'    => _n("%s {$this->singular} not updated, somebody is editing it.",
                "%s {$this->plural} not updated, somebody is editing them.", $bulk_counts['locked']),
            'deleted'   => _n("%s {$this->singular} permanently deleted.", "%s {$this->plural} permanently deleted.",
                $bulk_counts['deleted']),
            'trashed'   => _n("%s {$this->singular} moved to the Trash.", "%s {$this->plural} moved to the Trash.",
                $bulk_counts['trashed']),
            'untrashed' => _n("%s {$this->singular} restored from the Trash.",
                "%s {$this->plural} restored from the Trash.", $bulk_counts['untrashed']),
        ];

        return $bulk_messages;
    }
}
