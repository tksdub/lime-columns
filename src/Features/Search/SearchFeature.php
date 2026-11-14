<?php
declare(strict_types=1);

namespace LimeColumns\Features\Search;

use LimeColumns\Container;
use LimeColumns\Context;
use LimeColumns\Contracts\Feature;

/** 一覧の検索を、検索対象に指定した列（メタ値）へ拡張する。現状は投稿一覧のみ。 */
final class SearchFeature implements Feature
{
    public function id(): string { return 'search'; }
    public function appliesToListScreens(): bool { return true; }

    public function boot(Container $c): void
    {
        add_action('lime_columns/context_ready', function (Context $ctx): void {
            if ($ctx->screen->objectType() !== 'post') {
                return;
            }
            $keys = [];
            foreach ($ctx->set->columns as $col) {
                if ($col->search && $ctx->type($col)) {
                    $keys = array_merge($keys, $ctx->type($col)->searchMetaKeys($col));
                }
            }
            $keys = array_values(array_unique(array_filter($keys)));
            if (!$keys) {
                return;
            }
            $ctx->screen->attachQuery(function (object $q) use ($ctx, $keys): void {
                $term = $ctx->screen->searchTerm($q);
                if ($term === '') {
                    return;
                }
                $cb = static function (string $search, \WP_Query $query) use (&$cb, $q, $keys, $term): string {
                    if ($query !== $q || trim($search) === '') {
                        return $search;
                    }
                    remove_filter('posts_search', $cb, 20);
                    global $wpdb;
                    $in = implode(',', array_fill(0, count($keys), '%s'));
                    $like = '%' . $wpdb->esc_like($term) . '%';
                    $exists = $wpdb->prepare(
                        "EXISTS (SELECT 1 FROM {$wpdb->postmeta} lm WHERE lm.post_id = {$wpdb->posts}.ID AND lm.meta_key IN ($in) AND lm.meta_value LIKE %s)", // phpcs:ignore
                        ...array_merge($keys, [$like])
                    );
                    $base = preg_replace('/^\s*AND\s*/i', '', $search);
                    return " AND (({$base}) OR {$exists})";
                };
                add_filter('posts_search', $cb, 20, 2);
            });
        });
    }
}
