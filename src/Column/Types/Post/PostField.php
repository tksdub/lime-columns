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
        'post_id' => ['ID', 'ID'],
        'slug' => ['post_name', 'name'],
        'status' => ['post_status', 'post_status'],
        'modified' => ['post_modified', 'modified'],
        'excerpt' => ['post_excerpt', null],
        'content' => ['post_content', null],
    ];

    public function __construct(private readonly string $kind)
    {
    }

    public function id(): string
    {
        return $this->kind;
    }

    /** @return list<string> ステータスは添付ファイルでは常に inherit なので、メディアでは出さない。 */
    protected function objectTypes(): array
    {
        return $this->kind === 'status' ? ['post'] : ['post', 'media'];
    }

    public function label(): string
    {
        return match ($this->kind) {
            'post_id' => __('ID', 'lime-columns'),
            'slug' => __('Slug', 'lime-columns'),
            'status' => __('Status', 'lime-columns'),
            'modified' => __('Last modified', 'lime-columns'),
            'excerpt' => __('Excerpt', 'lime-columns'),
            'content' => __('Content', 'lime-columns'),
        };
    }

    public function fields(): array
    {
        return match ($this->kind) {
            'modified' => [
                ['name' => 'date_mode', 'label' => __('Date format', 'lime-columns'), 'type' => 'select', 'default' => 'wp',
                    'options' => ['wp' => 'WordPress', 'custom' => 'Custom', 'diff' => 'Relative']],
                ['name' => 'date_format', 'label' => __('PHP date format', 'lime-columns'), 'type' => 'text', 'default' => 'Y-m-d H:i', 'show_if' => ['date_mode' => 'custom']],
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
            sort: self::DEFS[$this->kind][1] !== null,
            search: in_array($this->kind, ['excerpt', 'content'], true),
        );
    }

    public function value(Column $column, int $id): string
    {
        $post = get_post($id);
        if (!$post) {
            return '';
        }
        $prop = self::DEFS[$this->kind][0];
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
        $o = self::DEFS[$this->kind][1];
        return $o === null ? null : ['orderby' => $o];
    }
}
