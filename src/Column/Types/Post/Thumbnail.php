<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

final class Thumbnail extends AbstractColumnType
{
    public function id(): string { return 'thumbnail'; }
    public function label(): string { return __('Featured image', 'lime-columns'); }

    public function fields(): array
    {
        return [['name' => 'size', 'label' => __('Image size', 'lime-columns'), 'type' => 'select', 'default' => 'thumbnail',
            'options' => array_combine(get_intermediate_image_sizes(), get_intermediate_image_sizes())]];
    }

    public function value(Column $column, int $id): string
    {
        return (string) (get_the_post_thumbnail_url($id, (string) $column->setting('size', 'thumbnail')) ?: '');
    }

    public function render(Column $column, int $id): string
    {
        $url = $this->value($column, $id);
        return $url === '' ? Format::EMPTY_HTML : sprintf('<img src="%s" alt="" style="max-width:60px;height:auto">', esc_url($url));
    }
}
