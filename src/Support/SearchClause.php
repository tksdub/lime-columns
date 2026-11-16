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

    /**
     * メタ値に「関連先の ID」を保存する項目（ACF の投稿オブジェクト・ユーザー・タクソノミーなど）を、関連先の名前で検索する。
     * 保存値は ID 単体、またはシリアライズされた ID の配列。ID は完全一致で判定する（部分一致の誤ヒットを避ける）。
     * @param 'post'|'user'|'term' $target
     */
    public static function reference(string $metaKey, string $target, string $term): ?string
    {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($term) . '%';
        $map = [
            'post' => [$wpdb->posts, 'ID', 'r.post_title LIKE %s', 1],
            'user' => [$wpdb->users, 'ID', '(r.display_name LIKE %s OR r.user_login LIKE %s)', 2],
            'term' => [$wpdb->terms, 'term_id', 'r.name LIKE %s', 1],
        ];
        if (!isset($map[$target])) {
            return null;
        }
        [$table, $idCol, $cond, $n] = $map[$target];
        return (string) $wpdb->prepare(
            "EXISTS (SELECT 1 FROM {$wpdb->postmeta} rm JOIN {$table} r ON ("
            . "rm.meta_value = CAST(r.{$idCol} AS CHAR)"
            . " OR rm.meta_value LIKE CONCAT('%%\"', r.{$idCol}, '\"%%')"
            . " OR rm.meta_value LIKE CONCAT('%%;i:', r.{$idCol}, ';%%'))"
            . " WHERE rm.post_id = {$wpdb->posts}.ID AND rm.meta_key = %s AND {$cond})", // phpcs:ignore
            ...array_merge([$metaKey], array_fill(0, $n, $like))
        );
    }
}
