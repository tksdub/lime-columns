<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

/** WP標準列（title/author/date/comments）の共通基底。描画はWPに任せ、ラベル・順序・幅のみ管理。 */
abstract class NativePostColumn extends AbstractColumnType
{
    abstract protected function orderby(): ?string;

    public function capabilities(): array
    {
        return self::caps(sort: $this->orderby() !== null);
    }

    public function sortArgs(Column $column): ?array
    {
        $o = $this->orderby();
        return $o === null ? null : ['orderby' => $o];
    }

    public function value(Column $column, int $id): string
    {
        $post = get_post($id);
        if (!$post) {
            return '';
        }
        return match ($this->nativeKey()) {
            'title' => (string) $post->post_title,
            'author' => (string) (get_userdata((int) $post->post_author)->display_name ?? ''),
            'date' => Format::date($post->post_date, 'wp'),
            'comments' => (string) get_comments_number($id),
            default => '',
        };
    }
}
