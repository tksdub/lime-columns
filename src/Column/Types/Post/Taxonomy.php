<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Formatter\Format;

final class Taxonomy extends AbstractColumnType
{
    public function id(): string { return 'taxonomy'; }
    public function label(): string { return __('Taxonomy', 'lime-columns'); }

    public function fields(): array
    {
        $opts = [];
        foreach (get_taxonomies(['show_ui' => true], 'objects') as $t) {
            $opts[$t->name] = $t->label;
        }
        return [['name' => 'taxonomy', 'label' => __('Taxonomy', 'lime-columns'), 'type' => 'select', 'options' => $opts]];
    }

    public function value(Column $column, int $id): string
    {
        $terms = get_the_terms($id, (string) $column->setting('taxonomy', 'category'));
        return is_array($terms) ? implode(', ', wp_list_pluck($terms, 'name')) : '';
    }

    public function render(Column $column, int $id): string
    {
        $tax = (string) $column->setting('taxonomy', 'category');
        $terms = get_the_terms($id, $tax);
        if (!is_array($terms) || !$terms) {
            return Format::EMPTY_HTML;
        }
        $pt = get_post_type($id) ?: 'post';
        $links = array_map(static fn ($t) => sprintf(
            '<a href="%s">%s</a>',
            esc_url(add_query_arg([$tax === 'category' ? 'cat' : $tax => $tax === 'category' ? $t->term_id : $t->slug, 'post_type' => $pt], admin_url('edit.php'))),
            esc_html($t->name)
        ), $terms);
        return implode(', ', $links);
    }
}
