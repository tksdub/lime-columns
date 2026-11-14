<?php
declare(strict_types=1);

namespace LimeColumns\Features\DefaultSort;

use LimeColumns\Container;
use LimeColumns\Context;
use LimeColumns\Contracts\Feature;

/** 並び替え指定がないときの既定ソート（一覧設定 default_orderby / default_order）。 */
final class DefaultSortFeature implements Feature
{
    public function id(): string { return 'default_sort'; }
    public function appliesToListScreens(): bool { return true; }

    public function boot(Container $c): void
    {
        add_action('lime_columns/context_ready', function (Context $ctx): void {
            $name = (string) $ctx->set->setting('default_orderby', '');
            if ($name === '') {
                return;
            }
            $ctx->screen->attachQuery(function (object $q) use ($ctx, $name): void {
                if ($ctx->screen->requestedOrderby($q) !== '') {
                    return;
                }
                $col = $ctx->column($name);
                $args = $col ? $ctx->type($col)?->sortArgs($col) : null;
                if ($args === null) {
                    return;
                }
                $q->set('order', strtolower((string) $ctx->set->setting('default_order', 'desc')) === 'asc' ? 'ASC' : 'DESC');
                $ctx->screen->applyOrder($q, $args);
            });
        });
    }
}
