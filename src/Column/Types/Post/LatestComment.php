<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

/** 最新コメントの日時・投稿者・本文を表示。並び替えは最新コメント日時のサブクエリで行う。 */
final class LatestComment extends AbstractColumnType
{
    public function id(): string { return 'latest_comment'; }
    public function label(): string { return __('Latest comment', 'lime-columns'); }

    public function fields(): array
    {
        return [
            ['name' => 'property', 'label' => __('Display', 'lime-columns'), 'type' => 'select', 'default' => 'date',
                'options' => ['date' => 'Date', 'author' => 'Author', 'comment' => 'Comment']],
            ['name' => 'date_format', 'label' => 'PHP date format', 'type' => 'text', 'default' => 'Y-m-d H:i:s', 'show_if' => ['property' => 'date']],
        ];
    }

    public function capabilities(): array { return self::caps(sort: true); }

    public function sortArgs(Column $column): ?array
    {
        global $wpdb;
        return ['orderby_sql' => "(SELECT MAX(c.comment_date_gmt) FROM {$wpdb->comments} c WHERE c.comment_post_ID = {$wpdb->posts}.ID AND c.comment_approved = '1')"];
    }

    public function value(Column $column, int $id): string
    {
        $c = get_comments(['post_id' => $id, 'number' => 1, 'status' => 'approve', 'orderby' => 'comment_date_gmt', 'order' => 'DESC']);
        if (!$c) {
            return '';
        }
        return match ((string) $column->setting('property', 'date')) {
            'author' => (string) $c[0]->comment_author,
            'comment' => Format::words((string) $c[0]->comment_content, 20),
            default => Format::date((string) $c[0]->comment_date, 'custom', (string) $column->setting('date_format', 'Y-m-d H:i:s')),
        };
    }
}
