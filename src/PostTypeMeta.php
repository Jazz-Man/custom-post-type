<?php

namespace JazzMan\Post;

use JetBrains\PhpStorm\ExpectedValues;
use WP_Post;
use WP_Post_Type;

final class PostTypeMeta {

    /**
     * @var string
     */
    public const TYPE_STRING = 'string';

    /**
     * @var string
     */
    public const TYPE_BOOLEAN = 'boolean';

    /**
     * @var string
     */
    public const TYPE_INTEGER = 'integer';

    /**
     * @var string
     */
    public const TYPE_NUMBER = 'number';

    /**
     * @var string
     */
    public const TYPE_ARRAY = 'array';

    /**
     * @var string
     */
    public const TYPE_OBJECT = 'object';

    /**
     * @var string
     */
    private const BEFORE = '<fieldset class="inline-edit-col-right"><div class="inline-edit-col"><div class="inline-edit-group wp-clearfix">';

    /**
     * @var string
     */
    private const AFTER = '</div></div></fieldset>';

    private ?string $metaLabel = null;

    private ?string $metaDescription = null;

    private bool $_showInRest = false;

    private bool $_isSortColumn = false;

    /**
     * @var callable|string|null
     */
    private mixed $quickEditCallback = null;

    /**
     * @var callable|string
     */
    private mixed $columnCallback = null;

    /**
     * @var string|callable|null
     */
    private mixed $sortCallback = null;

    private mixed $defaultValue = null;

    private bool $_isSingle = false;

    private string $metaType = self::TYPE_STRING;

    /**
     * @var callable|string|null
     */
    private mixed $sanitizeCallback = null;

    private string $capability = 'manage_options';

    public function __construct( private readonly string $postType, private string $metaKey ) {}

    public static function addInlineEditScript(): void {
        add_action( 'admin_enqueue_scripts', static function (): void {
            wp_enqueue_script( 'post-meta-inline-edit', plugin_dir_url( __DIR__ ).'js/admin.js', [ 'jquery', 'inline-edit-post' ] );
        } );
    }

    public function setQuickEditCallback( callable|string $quickEditCallback ): self {
        $this->quickEditCallback = $quickEditCallback;

        return $this;
    }

    public function setColumnCallback( callable|string $columnCallback ): self {
        $this->columnCallback = $columnCallback;

        return $this;
    }

    public function setSortCallback( callable|string $sortCallback ): self {
        $this->sortCallback = $sortCallback;

        return $this;
    }

    public function isSortColumn( bool $isSortColumn ): self {
        $this->_isSortColumn = $isSortColumn;

        return $this;
    }

    public function showInRest( bool $showInRest ): self {
        $this->_showInRest = $showInRest;

        return $this;
    }

    public function setSanitizeCallback( callable|string $sanitizeCallback ): self {
        $this->sanitizeCallback = $sanitizeCallback;

        return $this;
    }

    public function setDefaultValue( mixed $defaultValue ): self {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    public function isSingle( bool $isSingle ): self {
        $this->_isSingle = $isSingle;

        return $this;
    }

    public function setMetaDescription( string $metaDescription ): self {
        $this->metaDescription = $metaDescription;

        return $this;
    }

    public function setMetaLabel( string $metaLabel ): self {
        $this->metaLabel = $metaLabel;

        return $this;
    }

    public function setMetaType(
        #[ExpectedValues( values: [
            self::TYPE_STRING,
            self::TYPE_BOOLEAN,
            self::TYPE_INTEGER,
            self::TYPE_NUMBER,
            self::TYPE_ARRAY,
            self::TYPE_OBJECT,
        ] )] string $metaType
    ): self {
        $this->metaType = $metaType;

        return $this;
    }

    public function setCapability( string $capability ): self {
        $this->capability = $capability;

        return $this;
    }

    public function run(): void {
        add_action(
            'init',
            function (): void {
                $default = [
                    'type' => $this->metaType,
                    'single' => $this->_isSingle,
                    'show_in_rest' => $this->_showInRest,
                ];

                if ( ! empty( $this->metaDescription ) ) {
                    $default['description'] = $this->metaDescription;
                }

                if ( \is_callable( $this->sanitizeCallback ) ) {
                    $default['sanitize_callback'] = $this->sanitizeCallback;
                }

                if ( null !== $this->defaultValue ) {
                    $default['default'] = $this->defaultValue;
                }

                if ( ! empty( $this->capability ) ) {
                    $default['auth_callback'] = \is_callable( $this->capability ) ?
                        $this->capability :
                        fn ( bool $allowed, string $metaKey, int $postID, int $userId, string $cap, array $caps ): bool => current_user_can( $this->capability );
                }

                register_post_meta( $this->postType, $this->metaKey, $default );
            }
        );

        $this->addMetaColumn();

        $this->quickEdit();
    }

    public static function quickEditField( #[ExpectedValues( values: [ 'textarea', 'text' ] )] string $type, string $columnName, string $columnLabel ): void {
        if ( 'textarea' === $type ) {
            printf(
                '%s<label><span class="title">%s</span><span class="input-text-wrap"><textarea name="%s" class="inline-edit-post-input"></textarea></span></label>%s',
                self::BEFORE,
                $columnLabel,
                $columnName,
                self::AFTER
            );
        } elseif ( 'text' === $type ) {
            printf(
                '%s<label><span class="title">%s</span><span class="input-text-wrap"><input type="text" name="%s" class="inline-edit-post-input" value=""/></span></label>%s',
                self::BEFORE,
                $columnLabel,
                $columnName,
                self::AFTER
            );
        }
    }

    public static function columnContent( string $column, WP_Post $post ): void {
        switch ( $column ) {
            case 'post_id':
                printf(
                    '<span title="%1$s ID: %2$s">%2$s</span>',
                    esc_attr( $post->post_title ),
                    esc_attr( (string) $post->ID ),
                );

                break;

            case str_starts_with( $column, 'meta_' ):
                $meta_key = ltrim( $column, 'meta_' );

                /** @var string|null $meta */
                $meta = get_post_meta( $post->ID, $meta_key, true );

                if ( ! empty( $meta ) ) {
                    printf(
                        '<span title="%s Meta: %s">%s</span>',
                        esc_attr( $post->post_title ),
                        esc_attr( $meta_key ),
                        esc_attr( $meta )
                    );
                }

                break;

            case 'thumbnail':
                $thumbnail_id = get_post_thumbnail_id( $post );

                if ( ! empty( $thumbnail_id ) ) {
                    self::imageColumn( $thumbnail_id, $post );
                }

                break;
        }
    }

    public static function imageColumn( int $imageId, WP_Post $post ): void {
        $link = sprintf( 'post.php?post=%d&action=edit', $post->ID );

        $img_html = wp_get_attachment_image(
            $imageId,
            [ 60, 60 ],
            false,
            [
                'alt' => $post->post_title,
            ]
        );

        if ( ! empty( $img_html ) ) {
            printf(
                '<a title="%s Image" href="%s">%s</a>',
                esc_attr( $post->post_title ),
                esc_url( $link ),
                $img_html
            );
        }
    }

    private function addMetaColumn(): void {
        if ( empty( $this->metaLabel ) ) {
            return;
        }

        if ( ! \is_callable( $this->columnCallback ) ) {
            return;
        }

        add_filter(
            sprintf( 'manage_%s_posts_columns', $this->postType ),
            function ( array $_columns ): array {
                $_columns[ $this->metaKey ] = $this->metaLabel;

                return $_columns;
            }
        );

        add_action(
            sprintf( 'manage_%s_posts_custom_column', $this->postType ),
            function ( string $columnName, int $postID ): void {
                $post = get_post( $postID );

                if ( ! $post instanceof WP_Post ) {
                    return;
                }

                if ( $columnName !== $this->metaKey ) {
                    return;
                }

                \call_user_func( $this->columnCallback, $columnName, $post );
            },
            10,
            2
        );

        if ( $this->_isSortColumn ) {
            add_filter(
                sprintf( 'manage_edit-%s_sortable_columns', $this->postType ),
                function ( array $_columns ): array {
                    $_columns[ $this->metaKey ] = $this->metaKey;

                    return $_columns;
                }
            );

            if ( ! \is_callable( $this->sortCallback ) ) {
                return;
            }

            add_filter( 'request', $this->sortCallback );
        }
    }

    private function quickEdit(): void {

        if ( ! \is_callable( $this->quickEditCallback ) ) {
            return;
        }

        add_action( 'add_inline_data', function ( WP_Post $post, WP_Post_Type $post_type_object ): void {
            if ( $post_type_object->name !== $this->postType ) {
                return;
            }

            /** @var string|null $meta */
            $meta = get_post_meta( $post->ID, $this->metaKey, true );

            if ( empty( $meta ) ) {
                return;
            }

            printf(
                '<div class="quick-edit-custom-box %s" data-meta="%s">%s</div>',
                $this->metaKey,
                $this->metaKey,
                $meta
            );
        }, 10, 2 );

        add_action(
            'quick_edit_custom_box',
            function ( string $columnName, string $postType, ?string $taxonomy ): void {

                if ( $postType !== $this->postType ) {
                    return;
                }

                if ( $columnName !== $this->metaKey ) {
                    return;
                }

                \call_user_func( $this->quickEditCallback, $columnName, $taxonomy );
            },
            10,
            3
        );

        add_action(
            sprintf( 'save_post_%s', $this->postType ),
            function ( int $post_id ): void {
                if ( [] === $_POST ) {
                    return;
                }

                if ( ! isset( $_POST[ $this->metaKey ] ) ) {
                    return;
                }

                update_post_meta( $post_id, $this->metaKey, $_POST[ $this->metaKey ] );
            }
        );
    }
}
