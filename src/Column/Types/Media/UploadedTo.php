<?php
declare(strict_types=1);

namespace LimeColumns\Column\Types\Media;

use LimeColumns\Column\Types\Post\NativePostColumn;

/** WP標準の「添付先」列（そのファイルを添付した投稿）。メディア一覧専用。 */
final class UploadedTo extends NativePostColumn
{
    public function id(): string { return 'uploaded_to'; }
    public function label(): string { return __('Uploaded to (WP default)', 'lime-columns'); }
    public function nativeKey(): ?string { return 'parent'; }
    protected function orderby(): ?string { return 'parent'; }
    public function group(): string { return 'wp'; }

    /** @return list<string> */
    protected function objectTypes(): array { return ['media']; }
}
