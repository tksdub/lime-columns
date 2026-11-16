<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Post;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;
use LimeColumns\Support\SearchClause;

/** 投稿者を任意のプロパティ（ログイン名等）で表示する列。 */
final class AuthorDisplay extends AbstractColumnType
{
    public function id(): string { return 'author_display'; }
    public function label(): string { return __('Author (custom display)', 'lime-columns'); }
    protected function objectTypes(): array { return ['post', 'media']; }

    public function fields(): array
    {
        return [
            ['name' => 'property', 'label' => __('Display as', 'lime-columns'), 'type' => 'select', 'default' => 'display_name',
                'options' => ['display_name' => 'Display name', 'user_login' => 'User login', 'user_email' => 'Email', 'nickname' => 'Nickname', 'ID' => 'ID']],
            ['name' => 'link', 'label' => __('Link to', 'lime-columns'), 'type' => 'select', 'default' => 'edit_user',
                'options' => ['none' => 'None', 'edit_user' => 'Edit user', 'author_posts' => 'Posts by author']],
        ];
    }

    public function capabilities(): array { return self::caps(sort: true, search: true); }
    public function searchClause(Column $column, string $term): ?string { return SearchClause::author($term); }
    public function sortArgs(Column $column): ?array { return ['orderby' => 'author']; }

    public function value(Column $column, int $id): string
    {
        $uid = (int) get_post_field('post_author', $id);
        $user = $uid ? get_userdata($uid) : false;
        return $user ? (string) $user->{(string) $column->setting('property', 'display_name')} : '';
    }

    public function render(Column $column, int $id): string
    {
        $text = $this->value($column, $id);
        if ($text === '') {
            return parent::render($column, $id);
        }
        $uid = (int) get_post_field('post_author', $id);
        $url = match ((string) $column->setting('link', 'edit_user')) {
            'edit_user' => current_user_can('edit_user', $uid) ? get_edit_user_link($uid) : '',
            'author_posts' => add_query_arg(['post_type' => get_post_type($id), 'author' => $uid], admin_url('edit.php')),
            default => '',
        };
        return $url ? sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($text)) : esc_html($text);
    }
}
