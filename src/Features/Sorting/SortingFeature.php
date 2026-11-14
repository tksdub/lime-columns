<?php
declare(strict_types=1);

namespace LimeColumns\Features\Sorting;

use LimeColumns\Container;
use LimeColumns\Context;
use LimeColumns\Contracts\Feature;

/** 列クリックによる並び替えをクエリへ反映する。 */
final class SortingFeature implements Feature
{
    public function id(): string { return 'sorting'; }
    public function appliesToListScreens(): bool { return true; }

    public function boot(Container $c): void
    {
        add_action('lime_columns/context_ready', function (Context $ctx): void {
            $ctx->screen->attachQuery(function (object $q) use ($ctx): void {
                $col = $ctx->column($ctx->screen->requestedOrderby($q));
                $args = $col && $col->sort ? $ctx->type($col)?->sortArgs($col) : null;
                if ($args !== null) {
                    $ctx->screen->applyOrder($q, $args);
                }
            });
        });
    }
}
