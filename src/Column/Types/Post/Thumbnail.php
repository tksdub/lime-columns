<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

final class Thumbnail extends AbstractColumnType
{
    /** 一覧用の小さな表示。画像は thumbnail を使い、表示幅だけ 60px に絞る。 */
    private const SMALL = 'lime-small';
    private const SMALL_WIDTH = 60;

    public function id(): string { return 'thumbnail'; }
    public function label(): string { return __('Featured image', 'lime-columns'); }

    public function fields(): array
    {
        return [['name' => 'size', 'label' => __('Image size', 'lime-columns'), 'type' => 'select', 'default' => 'thumbnail',
            'options' => [self::SMALL => sprintf('small (%dpx)', self::SMALL_WIDTH)]
                + array_combine(get_intermediate_image_sizes(), get_intermediate_image_sizes())]];
    }

    public function value(Column $column, int $id): string
    {
        return (string) (get_the_post_thumbnail_url($id, $this->source($column)) ?: '');
    }

    public function render(Column $column, int $id): string
    {
        $src = wp_get_attachment_image_src((int) get_post_thumbnail_id($id), $this->source($column));
        if (!$src) {
            return Format::EMPTY_HTML;
        }
        // 表示幅は選んだサイズの実寸に合わせる（列が狭いときは列幅に収める）。
        $width = $column->setting('size', 'thumbnail') === self::SMALL ? self::SMALL_WIDTH : (int) $src[1];
        return sprintf('<img src="%s" alt="" style="max-width:min(100%%,%dpx);height:auto">', esc_url($src[0]), $width);
    }

    /** 実際に読み込む画像サイズ名。 */
    private function source(Column $column): string
    {
        $size = (string) $column->setting('size', 'thumbnail');
        return $size === self::SMALL ? 'thumbnail' : $size;
    }
}
