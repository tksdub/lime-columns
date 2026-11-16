<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Media;

use LimeColumns\Column\AbstractColumnType;
use LimeColumns\Column\Column;

/** メディア（添付ファイル）の情報を出す列: ファイル名・サイズ・寸法・MIME タイプ・代替テキスト。 */
final class MediaField extends AbstractColumnType
{
    private const KINDS = ['file_name', 'file_size', 'dimensions', 'mime_type', 'alt_text'];

    public function __construct(private readonly string $kind)
    {
        if (!in_array($kind, self::KINDS, true)) {
            throw new \InvalidArgumentException('Unknown media field: ' . esc_html($kind));
        }
    }

    public function id(): string { return $this->kind; }

    public function label(): string
    {
        return match ($this->kind) {
            'file_name' => __('File name', 'lime-columns'),
            'file_size' => __('File size', 'lime-columns'),
            'dimensions' => __('Dimensions', 'lime-columns'),
            'mime_type' => __('MIME type', 'lime-columns'),
            'alt_text' => __('Alt text', 'lime-columns'),
        };
    }

    /** @return list<string> */
    protected function objectTypes(): array { return ['media']; }

    public function capabilities(): array
    {
        return self::caps(
            sort: in_array($this->kind, ['file_name', 'mime_type', 'alt_text'], true),
            search: in_array($this->kind, ['file_name', 'alt_text'], true),
        );
    }

    public function sortArgs(Column $column): ?array
    {
        return match ($this->kind) {
            'file_name' => ['meta_key' => '_wp_attached_file', 'orderby' => 'meta_value'],
            'alt_text' => ['meta_key' => '_wp_attachment_image_alt', 'orderby' => 'meta_value'],
            'mime_type' => ['orderby' => 'post_mime_type'],
            default => null,
        };
    }

    public function searchMetaKeys(Column $column): array
    {
        return match ($this->kind) {
            'file_name' => ['_wp_attached_file'],
            'alt_text' => ['_wp_attachment_image_alt'],
            default => [],
        };
    }

    public function value(Column $column, int $id): string
    {
        return match ($this->kind) {
            'file_name' => wp_basename((string) get_post_meta($id, '_wp_attached_file', true)),
            'file_size' => $this->fileSize($id),
            'dimensions' => $this->dimensions($id),
            'mime_type' => (string) get_post_mime_type($id),
            'alt_text' => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
        };
    }

    public function render(Column $column, int $id): string
    {
        $v = $this->value($column, $id);
        if ($v === '') {
            return parent::render($column, $id);
        }
        if ($this->kind === 'file_name' && ($url = wp_get_attachment_url($id))) {
            return sprintf('<a href="%s">%s</a>', esc_url($url), esc_html($v));
        }
        return esc_html($v);
    }

    private function fileSize(int $id): string
    {
        $meta = wp_get_attachment_metadata($id);
        $bytes = is_array($meta) && isset($meta['filesize']) ? (int) $meta['filesize'] : 0;
        if ($bytes <= 0) {
            $path = get_attached_file($id);
            $bytes = ($path && is_file($path)) ? (int) filesize($path) : 0;
        }
        return $bytes > 0 ? (string) size_format($bytes, 1) : '';
    }

    private function dimensions(int $id): string
    {
        $meta = wp_get_attachment_metadata($id);
        if (!is_array($meta) || empty($meta['width']) || empty($meta['height'])) {
            return '';
        }
        return (int) $meta['width'] . ' × ' . (int) $meta['height'];
    }
}
