<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Services;

use App\Application\Mail\DTOs\Response\MailLogoPngDTO;
use App\Application\Mail\Services\MailLogoServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\QrCodes\RsvgConvertRasterizer;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * PNG, JPEG and GIF logos are linked as uploaded. An SVG is drawn here with rsvg-convert,
 * not by Cloudinary, which refuses some SVG files browsers draw (#499), and the site serves
 * the PNG itself (route `mail.logo`), so no extra file has to be kept in the media library.
 * Any other format (WebP, AVIF…) is a raster image Cloudinary converts reliably.
 */
final readonly class MailLogoService implements MailLogoServiceInterface
{
    /** Twice the 64 px the mail header shows, for high density screens */
    public const int HEIGHT = 128;

    public const int MAX_WIDTH = 512;

    private const array MAIL_SAFE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif'];

    private const string SETTING_EMAIL_LOGO = 'site_logo_email';

    private const string SETTING_LIGHT_LOGO = 'site_logo_light';

    private const string CACHE_PREFIX = 'mail_logo_png:';

    private const int CACHE_SECONDS = 30 * 24 * 3600;

    /** A logo that cannot be drawn is not tried again on every mail or image request */
    private const int FAILURE_CACHE_SECONDS = 600;

    private const string FAILED = 'failed';

    private const string PNG_SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function __construct(
        private SettingsServiceInterface $settings,
        private CloudinaryImageUrlBuilder $images,
        private RsvgConvertRasterizer $rasterizer,
        private CacheRepository $cache,
    ) {}

    public function url(): ?string
    {
        $path = $this->path();

        if ($path === null) {
            return null;
        }

        $extension = $this->extension($path);

        if (in_array($extension, self::MAIL_SAFE_EXTENSIONS, true)) {
            return $this->storedUrl($path);
        }

        if ($extension === 'svg') {
            $png = $this->drawn($path);

            return $png instanceof MailLogoPngDTO
                ? rtrim((string) config('app.url'), '/').route('mail.logo', ['v' => $png->version], false)
                : null;
        }

        return $this->images->emailLogo($path, self::HEIGHT, self::MAX_WIDTH) ?? $this->storedUrl($path);
    }

    public function png(): ?MailLogoPngDTO
    {
        $path = $this->path();

        return $path !== null && $this->extension($path) === 'svg' ? $this->drawn($path) : null;
    }

    private function drawn(string $path): ?MailLogoPngDTO
    {
        // Every upload gets a new file name, so the path names the logo
        $version = substr(hash('sha256', $path), 0, 16);
        $key = self::CACHE_PREFIX.$version;
        $cached = $this->cache->get($key);

        if ($cached === self::FAILED) {
            return null;
        }

        if (is_string($cached) && str_starts_with($cached, self::PNG_SIGNATURE)) {
            return new MailLogoPngDTO($cached, $version);
        }

        try {
            $svg = Storage::disk('images')->get($path);

            if ($svg === null || $svg === '') {
                throw new RuntimeException('the file is missing or empty');
            }

            $png = $this->rasterizer->rasterize($svg, self::MAX_WIDTH, self::HEIGHT);
        } catch (Throwable $e) {
            Log::warning(sprintf('[MailLogoService] The mail logo %s cannot be drawn as a PNG, so mails go without logo: %s', $path, $e->getMessage()));
            $this->cache->put($key, self::FAILED, self::FAILURE_CACHE_SECONDS);

            return null;
        }

        $this->cache->put($key, $png, self::CACHE_SECONDS);

        return new MailLogoPngDTO($png, $version);
    }

    private function path(): ?string
    {
        foreach ([self::SETTING_EMAIL_LOGO, self::SETTING_LIGHT_LOGO] as $key) {
            $value = $this->settings->get($key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function extension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    private function storedUrl(string $path): string
    {
        return Storage::disk('images')->url($path);
    }
}
