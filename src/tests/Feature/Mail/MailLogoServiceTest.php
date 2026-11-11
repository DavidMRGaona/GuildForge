<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Application\Mail\Services\MailLogoServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Mail\Services\MailLogoService;
use App\Infrastructure\QrCodes\RsvgConvertRasterizer;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MailLogoServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100" viewBox="0 0 400 100"><rect width="400" height="100" fill="#d97706"/></svg>';

    /** Browsers draw it; Cloudinary answers 400 «Invalid image file» when asked to rasterize it (#499) */
    private const string FLAWED_SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="210mmpx" height="100mmpx" viewBox="0 0 210 100"><rect width="210" height="100" fill="#d97706"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://asociacion.example', 'cloudinary.cloud_name' => '', 'cloudinary.prefix' => '']);
        Storage::fake('images');
    }

    private function settings(): SettingsServiceInterface
    {
        return app(SettingsServiceInterface::class);
    }

    private function service(?RsvgConvertRasterizer $rasterizer = null): MailLogoServiceInterface
    {
        if (! $rasterizer instanceof RsvgConvertRasterizer) {
            return app(MailLogoServiceInterface::class);
        }

        return new MailLogoService(
            $this->settings(),
            app(CloudinaryImageUrlBuilder::class),
            $rasterizer,
            app(CacheRepository::class),
        );
    }

    public function test_there_is_no_logo_without_one_configured(): void
    {
        $this->assertNull($this->service()->url());
        $this->assertNull($this->service()->png());
    }

    public function test_a_png_email_logo_keeps_its_stored_url(): void
    {
        $this->settings()->set('site_logo_email', 'branding/logo-email.png');

        $this->assertSame(Storage::disk('images')->url('branding/logo-email.png'), $this->service()->url());
        $this->assertNull($this->service()->png());
    }

    public function test_a_jpeg_light_logo_is_the_fallback_with_its_stored_url(): void
    {
        $this->settings()->set('site_logo_light', 'branding/logo-light.JPG');

        $this->assertSame(Storage::disk('images')->url('branding/logo-light.JPG'), $this->service()->url());
    }

    public function test_a_gif_email_logo_keeps_its_stored_url(): void
    {
        $this->settings()->set('site_logo_email', 'branding/logo-email.gif');

        $this->assertSame(Storage::disk('images')->url('branding/logo-email.gif'), $this->service()->url());
    }

    public function test_an_svg_email_logo_links_the_site_route_with_a_version(): void
    {
        Storage::disk('images')->put('branding/logo-email.svg', self::SVG);
        $this->settings()->set('site_logo_email', 'branding/logo-email.svg');

        $png = $this->service()->png();

        $this->assertNotNull($png);
        // From app.url, as every mail link: a queue worker has no request to take the host from
        $this->assertSame('https://asociacion.example'.route('mail.logo', ['v' => $png->version], false), $this->service()->url());
        $this->assertStringStartsWith('https://asociacion.example/correos/logo.png?v=', $this->service()->url());
    }

    public function test_an_svg_logo_is_drawn_as_a_png_the_height_mail_clients_need(): void
    {
        Storage::disk('images')->put('branding/logo-email.svg', self::SVG);
        $this->settings()->set('site_logo_email', 'branding/logo-email.svg');

        $png = $this->service()->png();

        $this->assertNotNull($png);
        $size = getimagesizefromstring($png->bytes);
        $this->assertIsArray($size);
        $this->assertSame(IMAGETYPE_PNG, $size[2]);
        $this->assertSame(MailLogoService::HEIGHT, $size[1]);
        $this->assertSame(MailLogoService::HEIGHT * 4, $size[0]);
    }

    public function test_a_very_wide_svg_logo_fits_the_maximum_width(): void
    {
        Storage::disk('images')->put('branding/logo-email.svg', str_replace('400', '4000', self::SVG));
        $this->settings()->set('site_logo_email', 'branding/logo-email.svg');

        $png = $this->service()->png();

        $this->assertNotNull($png);
        $size = getimagesizefromstring($png->bytes);
        $this->assertIsArray($size);
        $this->assertSame(MailLogoService::MAX_WIDTH, $size[0]);
        $this->assertLessThan(MailLogoService::HEIGHT, $size[1]);
    }

    public function test_an_svg_light_logo_is_drawn_when_there_is_no_email_logo(): void
    {
        Storage::disk('images')->put('branding/logo-light.svg', self::SVG);
        $this->settings()->set('site_logo_light', 'branding/logo-light.svg');

        $this->assertNotNull($this->service()->png());
        $this->assertStringStartsWith('https://asociacion.example/correos/logo.png?v=', (string) $this->service()->url());
    }

    public function test_an_svg_cloudinary_refuses_to_rasterize_is_still_drawn(): void
    {
        Storage::disk('images')->put('branding/logo-email.svg', self::FLAWED_SVG);
        $this->settings()->set('site_logo_email', 'branding/logo-email.svg');

        $png = $this->service()->png();

        $this->assertNotNull($png);
        $this->assertStringStartsWith("\x89PNG", $png->bytes);
    }

    public function test_the_version_changes_with_the_logo(): void
    {
        Storage::disk('images')->put('branding/logo-email-a.svg', self::SVG);
        Storage::disk('images')->put('branding/logo-email-b.svg', self::SVG);

        $this->settings()->set('site_logo_email', 'branding/logo-email-a.svg');
        $first = $this->service()->url();
        $this->settings()->set('site_logo_email', 'branding/logo-email-b.svg');
        $second = $this->service()->url();

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNotSame($first, $second);
    }

    public function test_the_drawn_png_is_cached(): void
    {
        Storage::disk('images')->put('branding/logo-email.svg', self::SVG);
        $this->settings()->set('site_logo_email', 'branding/logo-email.svg');
        $first = $this->service()->png();

        Storage::disk('images')->delete('branding/logo-email.svg');

        $this->assertEquals($first, $this->service()->png());
    }

    public function test_a_logo_that_cannot_be_drawn_leaves_the_mail_without_logo_and_logs_it(): void
    {
        $log = Log::spy();
        Storage::disk('images')->put('branding/logo-email.svg', self::SVG);
        $this->settings()->set('site_logo_email', 'branding/logo-email.svg');

        $service = $this->service(new RsvgConvertRasterizer('/nonexistent/rsvg-convert'));

        $this->assertNull($service->url());
        $this->assertNull($service->png());
        // Tried once: the failure is remembered for a while
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message): bool => str_contains($message, '[MailLogoService]') && str_contains($message, 'rsvg-convert'),
        )->once();
    }

    public function test_a_missing_svg_file_leaves_the_mail_without_logo(): void
    {
        $log = Log::spy();
        $this->settings()->set('site_logo_email', 'branding/gone.svg');

        $this->assertNull($this->service()->url());
        $this->assertNull($this->service()->png());
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message): bool => str_contains($message, 'branding/gone.svg'),
        )->once();
    }

    public function test_a_webp_logo_is_converted_to_png_by_cloudinary(): void
    {
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => 'guildforge']);
        $this->settings()->set('site_logo_email', 'branding/logo-email.webp');

        $this->assertSame(
            'https://res.cloudinary.com/demo/image/upload/h_'.MailLogoService::HEIGHT.',w_'.MailLogoService::MAX_WIDTH.',c_limit,f_png/guildforge/branding/logo-email',
            $this->service()->url(),
        );
    }

    public function test_an_avif_logo_is_converted_to_png_by_cloudinary(): void
    {
        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => 'guildforge']);
        $this->settings()->set('site_logo_email', 'branding/logo-email.avif');

        $this->assertStringEndsWith(',c_limit,f_png/guildforge/branding/logo-email', (string) $this->service()->url());
    }

    public function test_a_webp_logo_keeps_its_stored_url_without_cloudinary(): void
    {
        $this->settings()->set('site_logo_email', 'branding/logo-email.webp');

        $this->assertSame(Storage::disk('images')->url('branding/logo-email.webp'), $this->service()->url());
    }
}
