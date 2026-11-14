<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\User;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;

/** WP標準のユーザー列（username/name/email/role/posts）。描画はWPに任せる。 */
final class NativeUserColumn extends AbstractColumnType
{
    /** id => [label, nativeKey, orderby] */
    private const DEFS = [
        'username' => ['Username', 'username', 'login'],
        'user_name' => ['Name', 'name', 'display_name'],
        'email' => ['Email', 'email', 'email'],
        'role' => ['Role', 'role', null],
        'posts' => ['Posts count (WP default)', 'posts', null],
    ];

    public function __construct(private readonly string $kind)
    {
    }

    public function id(): string { return $this->kind; }
    public function label(): string { return __(self::DEFS[$this->kind][0], 'lime-columns'); }
    public function group(): string { return 'wp'; }
    public function nativeKey(): ?string { return self::DEFS[$this->kind][1]; }
    protected function objectTypes(): array { return ['user']; }
    public function capabilities(): array { return self::caps(sort: self::DEFS[$this->kind][2] !== null); }

    public function sortArgs(Column $column): ?array
    {
        $o = self::DEFS[$this->kind][2];
        return $o === null ? null : ['orderby' => $o];
    }

    public function value(Column $column, int $id): string
    {
        $u = get_userdata($id);
        if (!$u) {
            return '';
        }
        return match ($this->kind) {
            'username' => (string) $u->user_login,
            'user_name' => (string) $u->display_name,
            'email' => (string) $u->user_email,
            'role' => implode(', ', array_map(static fn ($r) => translate_user_role(wp_roles()->roles[$r]['name'] ?? $r), (array) $u->roles)),
            'posts' => (string) count_user_posts($id),
            default => '',
        };
    }
}
