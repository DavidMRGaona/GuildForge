<?php

declare(strict_types=1);

namespace App\Application\QrCodes\DTOs;

use App\Application\QrCodes\Enums\QrCodeFormat;

final readonly class RenderedQrCodeDTO
{
    /**
     * @param  string  $contents  The SVG document (no XML declaration) or the PNG bytes
     */
    public function __construct(
        public string $contents,
        public QrCodeFormat $format,
    ) {}

    public function mimeType(): string
    {
        return $this->format->mimeType();
    }

    public function extension(): string
    {
        return $this->format->extension();
    }

    public function dataUri(): string
    {
        return 'data:'.$this->mimeType().';base64,'.base64_encode($this->contents);
    }
}
