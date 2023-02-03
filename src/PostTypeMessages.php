<?php

namespace JazzMan\Post;

use JazzMan\AutoloadInterface\AutoloadInterface;

class PostTypeMessages implements AutoloadInterface {
    public function load(): void {
        add_filter('post_updated_messages', [self::class, 'updatedMessages']);
        add_filter('bulk_post_updated_messages', [self::class, 'bulkUpdatedMessages'], 10, 2);
    }

    /**
     * @param array<string,array<string,string>> $messages
     * @param array<string,int>                  $counts
     *
     * @return string[][]
     *
     * @psalm-return array<string, array<string, string>>
     */
    public static function bulkUpdatedMessages(array $messages = [], array $counts = []): array {
        global $post_type;

        $labels = self::getPostTypeLabelForMessages();

        if (empty($labels)) {
            return $messages;
        }

        if (!empty($messages[$post_type])) {
            return $messages;
        }

        $messages[$post_type] = [
            'updated' => _n(
                sprintf('%%s %s updated.', $labels['singular']),
                sprintf('%%s %s updated.', $labels['plural']),
                $counts['updated']
            ),
            'locked' => _n(
                sprintf('%%s %s not updated, somebody is editing it.', $labels['singular']),
                sprintf('%%s %s not updated, somebody is editing them.', $labels['plural']),
                $counts['locked']
            ),
            'deleted' => _n(
                sprintf('%%s %s permanently deleted.', $labels['singular']),
                sprintf('%%s %s permanently deleted.', $labels['plural']),
                $counts['deleted']
            ),
            'trashed' => _n(
                sprintf('%%s %s moved to the Trash.', $labels['singular']),
                sprintf('%%s %s moved to the Trash.', $labels['plural']),
                $counts['trashed']
            ),
            'untrashed' => _n(
                sprintf('%%s %s restored from the Trash.', $labels['singular']),
                sprintf('%%s %s restored from the Trash.', $labels['plural']),
                $counts['untrashed']
            ),
        ];

        return $messages;
    }

    /**
     * @param array<string,array<string,mixed>> $messages
     *
     * @return array[]
     *
     * @psalm-return array<string, array<int|string, mixed>>
     */
    public static function updatedMessages(array $messages = []): array {
        global $post_type, $post;

        $labels = self::getPostTypeLabelForMessages();

        if (empty($labels)) {
            return $messages;
        }

        if (!empty($messages[$post_type])) {
            return $messages;
        }

        /** @var null|int $revision */
        $revision = filter_input(INPUT_GET, 'revision', FILTER_SANITIZE_NUMBER_INT);

        /** @var false|string $revisionTitle */
        $revisionTitle = false;

        if (!empty($revision)) {
            $title = wp_post_revision_title($revision, false);

            if (!empty($title)) {
                $revisionTitle = sprintf(
                    '%1$s restored to revision from %2$s',
                    $labels['singular'],
                    $title
                );
            }
        }

        $messages[$post_type] = [
            0 => '',
            1 => sprintf('%s updated.', $labels['singular']),
            2 => 'Custom field updated.',
            3 => 'Custom field deleted.',
            4 => sprintf('%s updated.', $labels['singular']),
            5 => $revisionTitle,
            6 => sprintf('%s updated.', $labels['singular']),
            7 => sprintf('%s saved.', $labels['singular']),
            8 => sprintf('%s submitted.', $labels['singular']),
            9 => sprintf(
                '%s scheduled for: <strong>%s</strong>.',
                $labels['singular'],
                $post instanceof \WP_Post ? date_i18n('M j, Y @ G:i', strtotime($post->post_date)) : ''
            ),
            10 => sprintf('%s draft updated.', $labels['singular']),
        ];

        return $messages;
    }

    /**
     * @return array<string,string>|false
     */
    private static function getPostTypeLabelForMessages() {
        global $post_type, $post_type_object;

        if (empty($post_type)) {
            return false;
        }

        if (!$post_type_object instanceof \WP_Post_Type) {
            return false;
        }

        $labels = (array) $post_type_object->labels;

        if (empty($labels)) {
            return false;
        }

        $singular = !empty($labels['singular_name']) ? $labels['singular_name'] : false;
        $plural = !empty($labels['all_items']) ? $labels['all_items'] : false;

        if (empty($singular) || empty($plural)) {
            return false;
        }

        return [
            'singular' => esc_attr($singular),
            'plural' => esc_attr($plural),
        ];
    }
}
