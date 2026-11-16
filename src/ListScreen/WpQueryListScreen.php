<?php
declare(strict_types=1);

namespace LimeColumns\ListScreen;

use LimeColumns\Contracts\ListScreen;
use LimeColumns\Support\MetaSort;

/**
 * WP_Query で一覧を作る画面（投稿一覧・メディア一覧）の共通部分。
 * クエリへのフック・並び替え・検索語の取得は同じなので、ここにまとめる。
 * 画面ごとの違い（フック名・画面の判定・標準の列・エクスポート）は、サブクラスが書く。
 */
abstract class WpQueryListScreen implements ListScreen
{
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
}
