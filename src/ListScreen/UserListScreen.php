<?php
declare(strict_types=1);

namespace LimeColumns\ListScreen;

use LimeColumns\Support\MetaSort;

use LimeColumns\Contracts\ListScreen;

/** ユーザー一覧（users.php）。 */
final class UserListScreen implements ListScreen
{
    public function key(): string { return 'user'; }
    public function label(): string { return __('Users', 'default'); }
    public function group(): string { return __('Users', 'default'); }
    public function objectType(): string { return 'user'; }

    public function capability(): string { return 'list_users'; }

    public function matches(\WP_Screen $screen): bool { return $screen->id === 'users'; }
    public function columnsFilter(): string { return 'manage_users_columns'; }
    public function sortableFilter(): string { return 'manage_users_sortable_columns'; }

    public function attachCellRenderer(callable $render): void
    {
        add_filter('manage_users_custom_column', static function ($output, $column, $userId) use ($render) {
            $html = $render((string) $column, (int) $userId);
            return $html ?? $output;
        }, 10, 3);
    }

    public function attachQuery(callable $callback): void
    {
        add_action('pre_get_users', function (\WP_User_Query $q) use ($callback): void {
            if ($this->isListQuery($q)) {
                $callback($q);
            }
        });
    }

    public function isListQuery(object $query): bool
    {
        if (!$query instanceof \WP_User_Query) {
            return false;
        }
        if ($query->get('lime_columns_list') === $this->key()) {
            return true;
        }
        $screen = is_admin() && function_exists('get_current_screen') ? get_current_screen() : null;
        return $screen instanceof \WP_Screen && $this->matches($screen) && (bool) $query->get('count_total');
    }

    public function requestedOrderby(object $query): string
    {
        $o = $query->get('orderby');
        return is_string($o) ? $o : '';
    }

    public function applyOrder(object $query, array $args): void
    {
        global $wpdb;
        $args = MetaSort::normalize($args, 'user');
        if (isset($args['orderby_sql'])) {
            $clause = MetaSort::clause((string) $args['orderby_sql'], (string) $query->get('order'), !empty($args['nulls_last']), "{$wpdb->users}.ID");
            add_action('pre_user_query', static function (\WP_User_Query $uq) use ($query, $clause): void {
                if ($uq === $query) {
                    $uq->query_orderby = 'ORDER BY ' . $clause;
                }
            });
            return;
        }
        foreach ($args as $k => $v) {
            $query->set((string) $k, $v);
        }
    }

    public function searchTerm(object $query): string
    {
        return trim((string) $query->get('search'), '* ');
    }

    public function nativeColumns(): array
    {
        return ['username' => __('Username', 'default'), 'name' => __('Name', 'default'), 'email' => __('Email', 'default'), 'role' => __('Role', 'default'), 'posts' => __('Posts', 'default')];
    }

    public function exportIds(array $request): array
    {
        $args = [
            'fields' => 'ID',
            'number' => -1,
            'lime_columns_list' => $this->key(),
            'orderby' => isset($request['orderby']) ? sanitize_text_field((string) $request['orderby']) : 'login',
            'order' => (isset($request['order']) && strtolower((string) $request['order']) === 'desc') ? 'DESC' : 'ASC',
        ];
        if (!empty($request['s'])) {
            $args['search'] = '*' . sanitize_text_field((string) $request['s']) . '*';
        }
        if (!empty($request['role'])) {
            $args['role'] = sanitize_key((string) $request['role']);
        }
        return array_map('intval', (new \WP_User_Query($args))->get_results());
    }

    public function attachToolbar(callable $print): void
    {
        add_action('restrict_manage_users', static function ($which = 'top') use ($print): void {
            if ($which === 'top') {
                $print();
            }
        });
    }
}
