<?php
declare(strict_types=1);

namespace LimeColumns\Features\Display;

use LimeColumns\Container;
use LimeColumns\Context;
use LimeColumns\Contracts\Feature;

/** 列の追加・順序・ラベル・幅・セル描画。 */
final class DisplayFeature implements Feature
{
    public function id(): string { return 'display'; }
    public function appliesToListScreens(): bool { return true; }

    public function boot(Container $c): void
    {
        add_action('lime_columns/context_ready', function (Context $ctx): void {
            add_filter($ctx->screen->columnsFilter(), fn ($cols) => $this->columns($ctx, (array) $cols), 1000);
            add_filter($ctx->screen->sortableFilter(), fn ($s) => $this->sortable($ctx, (array) $s), 1000);
            $ctx->screen->attachCellRenderer(function (string $name, int $id) use ($ctx): ?string {
                $col = $ctx->column($name);
                if (!$col || $ctx->usesExistingKey($col)) {
                    return null;
                }
                return $ctx->type($col)?->render($col, $id);
            });
            add_action('admin_head', fn () => $this->printWidths($ctx));
        });
    }

    /** @param array<string,string> $incoming */
    private function columns(Context $ctx, array $incoming): array
    {
        $this->rememberForeign($ctx, $incoming);
        $out = isset($incoming['cb']) ? ['cb' => $incoming['cb']] : [];
        foreach ($ctx->set->columns as $col) {
            if ($ctx->usesExistingKey($col)) {
                if (!isset($incoming[$col->name])) {
                    continue; // 元の列が消えている（プラグイン無効化など）
                }
                $out[$col->name] = $col->label !== '' ? $this->label($col->label) : $incoming[$col->name];
            } elseif ($ctx->type($col)) {
                $out[$col->name] = $this->label($col->label);
            }
        }
        return $out;
    }

    /** @param array<string,mixed> $incoming */
    private function sortable(Context $ctx, array $incoming): array
    {
        $out = [];
        foreach ($ctx->set->columns as $col) {
            $type = $ctx->type($col);
            if ($col->type === 'foreign') {
                if (isset($incoming[$col->name])) {
                    $out[$col->name] = $incoming[$col->name];
                }
            } elseif ($col->sort && $type && $type->sortArgs($col) !== null) {
                $out[$col->name] = [$col->name, false];
            }
        }
        return $out;
    }

    private function label(string $label): string
    {
        return wp_kses($label, ['span' => ['class' => true, 'title' => true], 'strong' => [], 'em' => [], 'br' => []]);
    }

    /** 他プラグインが追加した列を設定UIで選べるよう、キーとラベルを記録する。 */
    private function rememberForeign(Context $ctx, array $incoming): void
    {
        $known = array_keys($ctx->screen->nativeColumns());
        $foreign = [];
        foreach ($incoming as $key => $label) {
            if ($key !== 'cb' && !in_array($key, $known, true)) {
                $foreign[(string) $key] = trim(wp_strip_all_tags((string) $label)) ?: (string) $key;
            }
        }
        $opt = 'lime_columns_foreign_' . $ctx->screen->key();
        if (get_option($opt) !== $foreign) {
            update_option($opt, $foreign, false);
        }
    }

    private function printWidths(Context $ctx): void
    {
        $css = '';
        foreach ($ctx->set->columns as $col) {
            if ($col->width !== '' && is_numeric($col->width)) {
                $css .= sprintf('.wp-list-table th.column-%s,.wp-list-table td.column-%1$s{width:%s%s}', esc_attr($col->name), (float) $col->width, $col->widthUnit);
            }
        }
        if ($css !== '') {
            echo '<style id="lime-columns-widths">' . $css . '</style>'; // phpcs:ignore WordPress.Security.EscapeOutput
        }
    }
}
