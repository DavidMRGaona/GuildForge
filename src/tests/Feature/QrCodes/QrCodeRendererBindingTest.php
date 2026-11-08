<?php

declare(strict_types=1);

namespace Tests\Feature\QrCodes;

use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\QrCodes\Services\QrCodeRendererInterface;
use App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException;
use Tests\Support\QrCodes\QrCodeTestImages;
use Tests\TestCase;

final class QrCodeRendererBindingTest extends TestCase
{
    public function test_the_renderer_accepts_urls_of_the_configured_site(): void
    {
        config(['app.url' => 'https://guild.example.org']);

        $png = $this->app->make(QrCodeRendererInterface::class)
            ->render('https://guild.example.org/enlaces', new QrCodeOptionsDTO, QrCodeFormat::Png);

        $this->assertSame('https://guild.example.org/enlaces', QrCodeTestImages::decode($png->contents));
    }

    public function test_the_renderer_rejects_other_sites(): void
    {
        config(['app.url' => 'https://guild.example.org']);

        $this->expectException(InvalidQrCodeContentException::class);

        $this->app->make(QrCodeRendererInterface::class)
            ->render('https://evil.example.com/', new QrCodeOptionsDTO, QrCodeFormat::Svg);
    }

    public function test_http_is_allowed_in_the_testing_environment(): void
    {
        config(['app.url' => 'http://localhost:8080']);

        $svg = $this->app->make(QrCodeRendererInterface::class)
            ->render('http://localhost:8080/enlaces', new QrCodeOptionsDTO, QrCodeFormat::Svg);

        $this->assertStringStartsWith('<svg', $svg->contents);
    }

    public function test_http_is_rejected_in_production(): void
    {
        config(['app.url' => 'http://guild.example.org']);
        $this->app->detectEnvironment(fn (): string => 'production');

        $this->expectException(InvalidQrCodeContentException::class);

        $this->app->make(QrCodeRendererInterface::class)
            ->render('http://guild.example.org/enlaces', new QrCodeOptionsDTO, QrCodeFormat::Svg);
    }
}
