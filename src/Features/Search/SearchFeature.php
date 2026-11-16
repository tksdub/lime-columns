<?php
declare(strict_types=1);

namespace LimeColumns\Features\Search;

use LimeColumns\Column\Column;
use LimeColumns\Container;
use LimeColumns\Context;
use LimeColumns\Contracts\ColumnType;
use LimeColumns\Contracts\Feature;

/**
 * 一覧の検索を、検索対象に指定した列（メタ値・投稿者名・タクソノミー名など）へ拡張する。現状は投稿一覧のみ。
 * 検索窓の横のプルダウン（lc_search_in）で、検索対象を「すべて」「標準のみ」「特定の列」に絞れる。
 */
final class SearchFeature implements Feature
{
    public const PARAM = 'lc_search_in';
    private const SCOPE_NATIVE = '__native';

    public function id(): string { return 'search'; }
    public function appliesToListScreens(): bool { return true; }

    public function boot(Container $c): void
    {
        add_action('lime_columns/context_ready', function (Context $ctx): void {
            if ($ctx->screen->objectType() !== 'post') {
                return;
            }
            /** @var array<string,array{col:Column,type:ColumnType,keys:list<string>,label:string}> $targets */
            $targets = [];
            foreach ($ctx->set->columns as $col) {
                $type = $col->search ? $ctx->type($col) : null;
                if (!$type) {
                    continue;
                }
                $keys = array_values(array_unique(array_filter($type->searchMetaKeys($col))));
                // メタキーも検索条件も持たない列（標準列など）は、絞り込みの候補にしない。
                if (!$keys && $type->searchClause($col, 'x') === null) {
                    continue;
                }
                $label = trim(wp_strip_all_tags($col->label));
                $targets[$col->name] = ['col' => $col, 'type' => $type, 'keys' => $keys, 'label' => $label !== '' ? $label : $col->name];
            }
            if (!$targets) {
                return;
            }

            $scope = $this->requestedScope(array_keys($targets));
            $this->registerUi($targets, $scope);

            if ($scope === self::SCOPE_NATIVE) {
                return; // 標準の検索（タイトル・本文など）だけ
            }
            $active = $scope === '' ? $targets : [$scope => $targets[$scope]];
            $only = $scope !== ''; // 特定の列だけを検索するときは、標準の検索条件を使わない

            $ctx->screen->attachQuery(function (object $q) use ($ctx, $active, $only): void {
                $term = $ctx->screen->searchTerm($q);
                if ($term === '') {
                    return;
                }
                $cb = static function (string $search, \WP_Query $query) use (&$cb, $q, $active, $only, $term): string {
                    if ($query !== $q || trim($search) === '') {
                        return $search;
                    }
                    remove_filter('posts_search', $cb, 20);
                    global $wpdb;
                    $keys = [];
                    foreach ($active as $t) {
                        $keys = array_merge($keys, $t['keys']);
                    }
                    $keys = array_values(array_unique($keys));
                    $extra = [];
                    if ($keys) {
                        $in = implode(',', array_fill(0, count($keys), '%s'));
                        $like = '%' . $wpdb->esc_like($term) . '%';
                        $extra[] = $wpdb->prepare(
                            "EXISTS (SELECT 1 FROM {$wpdb->postmeta} lm WHERE lm.post_id = {$wpdb->posts}.ID AND lm.meta_key IN ($in) AND lm.meta_value LIKE %s)", // phpcs:ignore
                            ...array_merge($keys, [$like])
                        );
                    }
                    foreach ($active as $t) {
                        if (($clause = $t['type']->searchClause($t['col'], $term)) !== null) {
                            $extra[] = $clause;
                        }
                    }
                    if (!$extra) {
                        return $search;
                    }
                    if ($only) {
                        return ' AND (' . implode(' OR ', $extra) . ')';
                    }
                    $base = preg_replace('/^\s*AND\s*/i', '', $search);
                    return " AND (({$base}) OR " . implode(' OR ', $extra) . ')';
                };
                add_filter('posts_search', $cb, 20, 2);
            });
        });
    }

    /**
     * リクエストの検索対象。実在する列名か「標準のみ」以外は、空文字（＝すべて）として扱う。
     * @param list<string|int> $names
     */
    private function requestedScope(array $names): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- 表示条件の読み取りのみ
        $v = isset($_GET[self::PARAM]) && is_string($_GET[self::PARAM]) ? wp_unslash($_GET[self::PARAM]) : '';
        if ($v === self::SCOPE_NATIVE) {
            return $v;
        }
        return in_array($v, array_map('strval', $names), true) ? $v : '';
    }

    /**
     * 検索対象のプルダウンを、フィルター欄に出す（検索窓と同じフォームなので、JS なしでも一緒に送信される）。
     * JS が検索窓の横へ移すが、移せなくてもフィルター欄でそのまま使える。
     * @param array<string,array{label:string}> $targets
     */
    private function registerUi(array $targets, string $scope): void
    {
        add_action('restrict_manage_posts', static function (string $postType = '', string $which = 'top') use ($targets, $scope): void {
            if ($which !== 'top') {
                return;
            }
            echo '<span class="lc-search-in-wrap" id="lc-search-in-wrap">';
            printf('<label class="screen-reader-text" for="lc-search-in">%s</label>', esc_html__('Search in', 'lime-columns'));
            printf('<select name="%s" id="lc-search-in" class="lc-search-in">', esc_attr(self::PARAM));
            printf('<option value=""%s>%s</option>', selected($scope, '', false), esc_html__('Search in: all', 'lime-columns'));
            printf('<option value="%s"%s>%s</option>', esc_attr(self::SCOPE_NATIVE), selected($scope, self::SCOPE_NATIVE, false), esc_html__('Standard (title, content)', 'lime-columns'));
            foreach ($targets as $name => $t) {
                printf('<option value="%s"%s>%s</option>', esc_attr((string) $name), selected($scope, (string) $name, false), esc_html($t['label']));
            }
            echo '</select></span>';
        });
        add_action('admin_enqueue_scripts', static function (): void {
            wp_enqueue_style('lime-columns-search-scope', LIME_COLUMNS_URL . 'assets/css/search-scope.css', [], LIME_COLUMNS_VERSION);
            wp_enqueue_script('lime-columns-search-scope', LIME_COLUMNS_URL . 'assets/js/search-scope.js', [], LIME_COLUMNS_VERSION, ['in_footer' => true]);
        });
    }
}
