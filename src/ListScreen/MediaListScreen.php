<?php
declare(strict_types=1);

namespace LimeColumns\ListScreen;

/**
 * メディアライブラリの「リスト表示」（upload.php?mode=list）。
 * グリッド表示には列がないため、対象外（WordPress の仕様）。
 */
final class MediaListScreen extends WpQueryListScreen
{
    public function key(): string { return 'media'; }
    public function label(): string { return __('Media Library', 'default'); }
    public function group(): string { return __('Media', 'default'); }
    public function objectType(): string { return 'media'; }

    public function capability(): string { return 'upload_files'; }

    public function matches(\WP_Screen $screen): bool
    {
        return $screen->base === 'upload';
    }

    public function columnsFilter(): string { return 'manage_media_columns'; }
    public function sortableFilter(): string { return 'manage_upload_sortable_columns'; }

    public function attachCellRenderer(callable $render): void
    {
        add_action('manage_media_custom_column', static function ($column, $postId) use ($render): void {
            $html = $render((string) $column, (int) $postId);
            if ($html !== null) {
                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- 列タイプ側でエスケープ済み
            }
        }, 10, 2);
    }

    public function nativeColumns(): array
    {
        $cols = ['title' => __('File', 'default'), 'author' => __('Author', 'default'), 'parent' => __('Uploaded to', 'default')];
        if (post_type_supports('attachment', 'comments')) {
            $cols['comments'] = __('Comments', 'default');
        }
        $cols['date'] = __('Date', 'default');
        return $cols;
    }

    public function exportIds(array $request): array
    {
        $args = [
            'post_type' => 'attachment',
            'post_status' => ['inherit', 'private'],
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'lime_columns_list' => $this->key(),
            's' => isset($request['s']) ? sanitize_text_field((string) $request['s']) : '',
            'orderby' => isset($request['orderby']) ? sanitize_text_field((string) $request['orderby']) : '',
            'order' => (isset($request['order']) && strtolower((string) $request['order']) === 'asc') ? 'ASC' : 'DESC',
        ];
        if (!empty($request['m']) && is_numeric($request['m'])) {
            $args['m'] = (int) $request['m'];
        }
        // 種類の絞り込み（attachment-filter）。「post_mime_type:image」「detached」「mine」
        $filter = isset($request['attachment-filter']) ? sanitize_text_field((string) $request['attachment-filter']) : '';
        if (str_starts_with($filter, 'post_mime_type:')) {
            $args['post_mime_type'] = substr($filter, strlen('post_mime_type:'));
        } elseif ($filter === 'detached') {
            $args['post_parent'] = 0;
        } elseif ($filter === 'mine') {
            $args['author'] = get_current_user_id();
        }
        if (!current_user_can('edit_others_posts')) {
            $args['author'] = get_current_user_id();
        }
        if ($args['orderby'] === '') {
            unset($args['orderby'], $args['order']);
        }
        return array_map('intval', (new \WP_Query($args))->posts);
    }

    public function attachToolbar(callable $print): void
    {
        // メディア一覧では、第1引数の投稿タイプが空・第2引数が 'bar' で渡される（WP の仕様）ため、画面の判定で絞る。
        add_action('restrict_manage_posts', function ($postType, $which = 'top') use ($print): void {
            $screen = function_exists('get_current_screen') ? get_current_screen() : null;
            if (in_array($which, ['top', 'bar'], true) && $screen instanceof \WP_Screen && $this->matches($screen)) {
                $print();
            }
        }, 20, 2);
    }
}
