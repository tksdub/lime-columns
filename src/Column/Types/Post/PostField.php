<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

/** 投稿テーブルの単純なプロパティを出す列（ID・スラッグ・ステータス・更新日・抜粋・本文）。 */
final class PostField extends AbstractColumnType
{
    private const DEFS = [
        'post_id' => ['ID', 'ID', 'ID'],
        'slug' => ['Slug', 'post_name', 'name'],
        'status' => ['Status', 'post_status', 'post_status'],
        'modified' => ['Last modified', 'post_modified', 'modified'],
        'excerpt' => ['Excerpt', 'post_excerpt', null],
        'content' => ['Content', 'post_content', null],
    ];

    public function __construct(private readonly string $kind)
    {
    }

    public function id(): string
    {
        return $this->kind;
    }

    public function label(): string
    {
        return __(self::DEFS[$this->kind][0], 'lime-columns');
    }

    public function fields(): array
    {
        return match ($this->kind) {
            'modified' => [
                ['name' => 'date_mode', 'label' => __('Date format', 'lime-columns'), 'type' => 'select', 'default' => 'wp',
                    'options' => ['wp' => 'WordPress', 'custom' => 'Custom', 'diff' => 'Relative']],
                ['name' => 'date_format', 'label' => 'PHP date format', 'type' => 'text', 'default' => 'Y-m-d H:i', 'show_if' => ['date_mode' => 'custom']],
            ],
            'excerpt', 'content' => [
                ['name' => 'words', 'label' => __('Word limit', 'lime-columns'), 'type' => 'number', 'default' => 30],
            ],
            default => [],
        };
    }

    public function capabilities(): array
    {
        return self::caps(
            sort: self::DEFS[$this->kind][2] !== null,
            search: in_array($this->kind, ['excerpt', 'content'], true),
        );
    }

    public function value(Column $column, int $id): string
    {
        $post = get_post($id);
        if (!$post) {
            return '';
        }
        $prop = self::DEFS[$this->kind][1];
        $raw = (string) $post->{$prop};
        return match ($this->kind) {
            'status' => (string) (get_post_status_object($raw)->label ?? $raw),
            'modified' => Format::date($raw, (string) $column->setting('date_mode', 'wp'), (string) $column->setting('date_format', '')),
            'excerpt', 'content' => Format::words($raw, (int) $column->setting('words', 30)),
            default => $raw,
        };
    }

    public function sortArgs(Column $column): ?array
    {
        $o = self::DEFS[$this->kind][2];
        return $o === null ? null : ['orderby' => $o];
    }
}
