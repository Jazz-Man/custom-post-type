<?php

namespace JazzMan\Post;

use JetBrains\PhpStorm\ExpectedValues;
use WP_Post;

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

    private ?string $mataLabel = null;

    private ?string $mataDescription = null;

    private bool $_showInRest = false;

    private bool $_isSortColumn = false;

    /**
     * @var callable|string
     */
    private $quickEditCallback;

    /**
     * @var callable|string|null
     */
    private $columnCallback;

    /**
     * @var string|callable|null
     */
    private $sortCallback;

    private mixed $defaultValue;

    private bool $_isSingle = false;

    private string $metaType = self::TYPE_STRING;

    /**
     * @var callable|string|null
     */
    private $sanitizeCallback;

    private string $capability = 'manage_options';

    public function __construct( private readonly string $postType, private string $metaKey ) {}

    public function setQuickEditCallback( callable|string $quickEditCallback ): self {
        $this->quickEditCallback = $quickEditCallback;

        return $this;
    }

    public function setColumnCallback( callable|string|null $columnCallback ): self {
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

    public function setMataDescription( string $mataDescription ): self {
        $this->mataDescription = $mataDescription;

        return $this;
    }

    public function setMataLabel( string $mataLabel ): self {
        $this->mataLabel = $mataLabel;

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

                if ( ! empty( $this->mataDescription ) ) {
                    $default['description'] = $this->mataDescription;
                }

                if ( ! empty( $this->sanitizeCallback ) && \is_callable( $this->sanitizeCallback ) ) {
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

        if ( empty( $this->quickEditCallback ) ) {
            return;
        }

        if ( ! \is_callable( $this->quickEditCallback ) ) {
            return;
        }

        $this->quickEdit();
    }

    public static function quickEditField( #[ExpectedValues( values: [ 'textarea', 'text' ] )] string $type, string $columnName, string $columnLabel, ?string $metaValue = null ): void {
        if ( 'textarea' === $type ) {
            printf(
                '%s<label><span class="title">%s</span><span class="input-text-wrap"><textarea name="%s" class="inline-edit-menu-order-input">%s</textarea></span></label>%s',
                self::BEFORE,
                $columnLabel,
                $columnName,
                empty( $metaValue ) ? '' : $metaValue,
                self::AFTER
            );
        } elseif ( 'text' === $type ) {
            printf(
                '%s<label><span class="title">%s</span><span class="input-text-wrap"><input type="text" name="%s" class="inline-edit-menu-order-input" value="%s"/></span></label>%s',
                self::BEFORE,
                $columnLabel,
                $columnName,
                empty( $metaValue ) ? '' : $metaValue,
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
        if ( empty( $this->mataLabel ) ) {
            return;
        }

        if ( empty( $this->columnCallback ) ) {
            return;
        }

        if ( ! \is_callable( $this->columnCallback ) ) {
            return;
        }

        add_filter(
            sprintf( 'manage_%s_posts_columns', $this->postType ),
            function ( array $_columns ): array {
                $_columns[ $this->metaKey ] = $this->mataLabel;

                return $_columns;
            }
        );

        add_action(
            sprintf( 'manage_%s_posts_custom_column', $this->postType ),
            function ( string $columnName ): void {
                global $post;

                if ( ! $post instanceof WP_Post ) {
                    return;
                }

                if ( $columnName !== $this->metaKey ) {
                    return;
                }

                \call_user_func( $this->columnCallback, $columnName, $post );
            }
        );

        if ( $this->_isSortColumn ) {
            add_filter(
                sprintf( 'manage_edit-%s_sortable_columns', $this->postType ),
                function ( array $_columns ): array {
                    $_columns[ $this->metaKey ] = $this->metaKey;

                    return $_columns;
                }
            );

            if ( empty( $this->sortCallback ) ) {
                return;
            }

            if ( ! \is_callable( $this->sortCallback ) ) {
                return;
            }

            add_filter( 'request', $this->sortCallback );
        }
    }

    private function quickEdit(): void {
        add_action(
            'quick_edit_custom_box',
            function ( string $column_name, string $post_type, ?string $taxonomy ): void {
                global $post;

                if ( $post_type !== $this->postType ) {
                    return;
                }

                if ( ! $post instanceof WP_Post ) {
                    return;
                }

                if ( $column_name !== $this->metaKey ) {
                    return;
                }

                \call_user_func( $this->quickEditCallback, $column_name, $post, $taxonomy );
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
