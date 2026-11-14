<?php
declare(strict_types=1);

namespace LimeColumns\Support;

/**
 * 投稿一覧の検索を拡張するための SQL 条件を組み立てる。
 * 列タイプが生の SQL を書かないよう、prepare 済みの断片はここだけで作る（どれも posts テーブルを参照する）。
 */
final class SearchClause
{
    /** 投稿者の表示名・ログイン名に一致する投稿。 */
    public static function author(string $term): string
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($term) . '%';
        return (string) $wpdb->prepare(
            "{$wpdb->posts}.post_author IN (SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s OR user_login LIKE %s)", // phpcs:ignore
            $like,
            $like
        );
    }

    /** 指定タクソノミーのターム名に一致する投稿。 */
    public static function taxonomy(string $taxonomy, string $term): string
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($term) . '%';
        return (string) $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->term_relationships} tr"
            . " JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id"
            . " JOIN {$wpdb->terms} t ON t.term_id = tt.term_id"
            . " WHERE tr.object_id = {$wpdb->posts}.ID AND tt.taxonomy = %s AND t.name LIKE %s)", // phpcs:ignore
            $taxonomy,
            $like
        );
    }
}
