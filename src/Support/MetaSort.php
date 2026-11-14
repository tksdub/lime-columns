<?php
declare(strict_types=1);

namespace LimeColumns\Support;

/**
 * meta_key での並び替えを「相関サブクエリ」の ORDER BY へ変換する。
 *
 * - WP標準の meta_key + orderby は INNER JOIN となり、メタを持たない行が結果から消える
 * - meta_query の OR(EXISTS/NOT EXISTS) 方式は大規模テーブルで極端に遅い
 * そこで行は一切減らさず、値が空の行は常に末尾へ回す。
 */
final class MetaSort
{
    /**
     * @param array<string,mixed> $args 列タイプの sortArgs()
     * @param 'post'|'user' $object
     * @return array<string,mixed> meta_key 指定を orderby_sql 指定に置き換えたもの
     */
    public static function normalize(array $args, string $object): array
    {
        if (empty($args['meta_key'])) {
            return $args;
        }
        global $wpdb;
        [$table, $idCol, $fk] = $object === 'user'
            ? [$wpdb->usermeta, "{$wpdb->users}.ID", 'user_id']
            : [$wpdb->postmeta, "{$wpdb->posts}.ID", 'post_id'];
        $expr = $wpdb->prepare("(SELECT m.meta_value FROM {$table} m WHERE m.{$fk} = {$idCol} AND m.meta_key = %s LIMIT 1)", (string) $args['meta_key']); // phpcs:ignore
        if (($args['orderby'] ?? '') === 'meta_value_num') {
            $expr = "CAST({$expr} AS DECIMAL(30,6))";
        }
        unset($args['meta_key']);
        $args['orderby_sql'] = $expr;
        $args['nulls_last'] = true;
        $args['orderby'] = '';
        return $args;
    }

    /** ORDER BY 句（ORDER BY は含まない）を組み立てる。 */
    public static function clause(string $expr, string $dir, bool $nullsLast, string $idCol): string
    {
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        return ($nullsLast ? "({$expr} IS NULL OR {$expr} = '') ASC, " : '') . "{$expr} {$dir}, {$idCol} DESC";
    }
}
