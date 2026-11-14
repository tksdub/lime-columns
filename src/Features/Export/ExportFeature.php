<?php
declare(strict_types=1);

namespace LimeColumns\Features\Export;

use LimeColumns\Container;
use LimeColumns\Context;
use LimeColumns\ContextResolver;
use LimeColumns\Contracts\Feature;

/** 現在の一覧の絞り込み・並び順を維持したまま、列定義どおりにCSVを出力する。 */
final class ExportFeature implements Feature
{
    private const ACTION = 'lime_columns_export';

    public function id(): string { return 'export'; }
    public function appliesToListScreens(): bool { return true; }

    public function boot(Container $c): void
    {
        add_action('lime_columns/context_ready', function (Context $ctx): void {
            if (!$this->exportColumns($ctx) || !current_user_can($ctx->screen->capability())) {
                return;
            }
            $ctx->screen->attachToolbar(fn () => $this->printButton($ctx));
        });
        add_action('admin_post_' . self::ACTION, fn () => $this->handle($c->get('resolver')));
    }

    /** @return list<\LimeColumns\Column\Column> */
    private function exportColumns(Context $ctx): array
    {
        return array_values(array_filter($ctx->set->columns, static fn ($c) => $c->export && $c->type !== 'foreign'));
    }

    private function printButton(Context $ctx): void
    {
        $args = array_diff_key(wp_unslash($_GET), ['paged' => 1, 'action' => 1, 'page' => 1]); // phpcs:ignore WordPress.Security
        $url = wp_nonce_url(
            add_query_arg(array_merge($args, ['action' => self::ACTION, 'list_key' => $ctx->screen->key()]), admin_url('admin-post.php')),
            self::ACTION . '_' . $ctx->screen->key()
        );
        printf('<a class="button lime-export" href="%s">%s</a>', esc_url($url), esc_html__('Export CSV', 'lime-columns'));
    }

    private function handle(ContextResolver $resolver): void
    {
        $key = sanitize_text_field(wp_unslash($_GET['list_key'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification
        check_admin_referer(self::ACTION . '_' . $key);
        $ctx = $resolver->fromKey($key);
        if (!$ctx || !current_user_can($ctx->screen->capability())) {
            wp_die(esc_html__('You are not allowed to export this list.', 'lime-columns'), '', ['response' => 403]);
        }
        do_action('lime_columns/context_ready', $ctx); // 並び替え・検索フックを一覧と同じ条件で有効化
        $cols = $this->exportColumns($ctx);
        $ids = $ctx->screen->exportIds(wp_unslash($_GET)); // phpcs:ignore WordPress.Security

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name(str_replace(':', '-', $key) . '-' . wp_date('Ymd-His') . '.csv') . '"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- php://output へのCSVストリーム出力。Excel向けUTF-8 BOM
        fputcsv($out, array_map(fn ($c) => $this->plain($c->label) ?: $c->name, $cols));
        foreach ($ids as $id) {
            $row = [];
            foreach ($cols as $c) {
                $row[] = $this->safe($ctx->type($c)?->value($c, $id) ?? '');
            }
            fputcsv($out, $row);
        }
        fclose($out); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- php://output のストリームを閉じる
        exit;
    }

    private function plain(string $label): string
    {
        return trim(wp_strip_all_tags($label));
    }

    /** CSVインジェクション対策（式として解釈される先頭文字を無効化）。 */
    private function safe(string $v): string
    {
        return $v !== '' && !is_numeric($v) && strpbrk($v[0], "=+-@\t\r") !== false ? "'" . $v : $v;
    }
}
