<?php
declare(strict_types=1);

namespace LimeColumns\ListScreen;

use LimeColumns\Support\MetaSort;

use LimeColumns\Contracts\ListScreen;

/** 投稿タイプ別の投稿一覧（edit.php?post_type=...）。 */
final class PostListScreen implements ListScreen
{
    public function __construct(private readonly \WP_Post_Type $postType)
    {
    }

    public function key(): string { return 'post:' . $this->postType->name; }
    public function label(): string { return (string) $this->postType->labels->name; }
    public function group(): string { return __('Post types', 'lime-columns'); }
    public function objectType(): string { return 'post'; }

    public function capability(): string { return (string) $this->postType->cap->edit_posts; }

    public function matches(\WP_Screen $screen): bool
    {
        return $screen->base === 'edit' && $screen->post_type === $this->postType->name;
    }

    public function columnsFilter(): string { return "manage_{$this->postType->name}_posts_columns"; }
    public function sortableFilter(): string { return "manage_edit-{$this->postType->name}_sortable_columns"; }

    public function attachCellRenderer(callable $render): void
    {
        add_action("manage_{$this->postType->name}_posts_custom_column", static function ($column, $postId) use ($render): void {
            $html = $render((string) $column, (int) $postId);
            if ($html !== null) {
                echo $html; // phpcs:ignore WordPress.Security.EscapeOutput -- 列タイプ側でエスケープ済み
            }
        }, 10, 2);
    }

    public function attachQuery(callable $callback): void
    {
        add_action('pre_get_posts', function (\WP_Query $q) use ($callback): void {
            if ($this->isListQuery($q)) {
                $callback($q);
            }
        });
    }

    public function isListQuery(object $query): bool
    {
        if (!$query instanceof \WP_Query) {
            return false;
        }
        if ($query->get('lime_columns_list') === $this->key()) {
            return true;
        }
        if (!is_admin() || !$query->is_main_query()) {
            return false;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen instanceof \WP_Screen && $this->matches($screen);
    }

    public function requestedOrderby(object $query): string
    {
        $o = $query->get('orderby');
        return is_string($o) ? $o : '';
    }

    public function applyOrder(object $query, array $args): void
    {
        global $wpdb;
        $args = MetaSort::normalize($args, 'post');
        if (isset($args['orderby_sql'])) {
            $clause = MetaSort::clause((string) $args['orderby_sql'], (string) $query->get('order'), !empty($args['nulls_last']), "{$wpdb->posts}.ID");
            $cb = static function (string $orderby, \WP_Query $q) use (&$cb, $query, $clause): string {
                if ($q !== $query) {
                    return $orderby;
                }
                remove_filter('posts_orderby', $cb, 10);
                return $clause;
            };
            add_filter('posts_orderby', $cb, 10, 2);
            return;
        }
        foreach ($args as $k => $v) {
            $query->set((string) $k, $v);
        }
    }

    public function searchTerm(object $query): string
    {
        return trim((string) $query->get('s'));
    }

    public function nativeColumns(): array
    {
        $pt = $this->postType->name;
        $cols = ['title' => __('Title', 'default')];
        if (post_type_supports($pt, 'author')) {
            $cols['author'] = __('Author', 'default');
        }
        if (post_type_supports($pt, 'comments')) {
            $cols['comments'] = __('Comments', 'default');
        }
        $cols['date'] = __('Date', 'default');
        return $cols;
    }

    public function exportIds(array $request): array
    {
        $pto = $this->postType;
        $status = isset($request['post_status']) ? sanitize_key((string) $request['post_status']) : '';
        $args = [
            'post_type' => $pto->name,
            'post_status' => ($status === '' || $status === 'all') ? array_values(get_post_stati(['show_in_admin_all_list' => true])) : $status,
            'fields' => 'ids',
            'posts_per_page' => -1,
            'no_found_rows' => true,
            'lime_columns_list' => $this->key(),
            's' => isset($request['s']) ? sanitize_text_field((string) $request['s']) : '',
            'orderby' => isset($request['orderby']) ? sanitize_text_field((string) $request['orderby']) : '',
            'order' => (isset($request['order']) && strtolower((string) $request['order']) === 'asc') ? 'ASC' : 'DESC',
        ];
        foreach (['m' => 'm', 'cat' => 'cat', 'author' => 'author', 'tag' => 'tag'] as $in => $out) {
            if (!empty($request[$in])) {
                $args[$out] = is_numeric($request[$in]) ? (int) $request[$in] : sanitize_text_field((string) $request[$in]);
            }
        }
        foreach (get_object_taxonomies($pto->name, 'objects') as $tax) {
            if ($tax->query_var && !empty($request[$tax->query_var])) {
                $args[$tax->query_var] = sanitize_text_field((string) $request[$tax->query_var]);
            }
        }
        if (!current_user_can($pto->cap->edit_others_posts)) {
            $args['author'] = get_current_user_id();
        }
        if ($args['orderby'] === '') {
            unset($args['orderby'], $args['order']);
        }
        return array_map('intval', (new \WP_Query($args))->posts);
    }

    public function attachToolbar(callable $print): void
    {
        add_action('restrict_manage_posts', function ($postType, $which = 'top') use ($print): void {
            if ($postType === $this->postType->name && $which === 'top') {
                $print();
            }
        }, 20, 2);
    }
}
