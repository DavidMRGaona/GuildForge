# QR codes

Modules have no Composer dependencies of their own, so the core draws QR codes for them behind a contract. Any module can print a code for one of the site's pages: a link page, an event sign-up, a table poster. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces the renderer (the one with core#350). An older core has no `QrCodeRendererInterface` binding.

## The contract

Resolve `App\Application\QrCodes\Services\QrCodeRendererInterface` from the container and call `render()`:

```php
use App\Application\QrCodes\DTOs\QrCodeLogoDTO;
use App\Application\QrCodes\DTOs\QrCodeOptionsDTO;
use App\Application\QrCodes\Enums\QrCodeErrorCorrection;
use App\Application\QrCodes\Enums\QrCodeFormat;
use App\Application\QrCodes\Services\QrCodeRendererInterface;

$qr = app(QrCodeRendererInterface::class)->render(
    route('linkpage.show', $page->slug),
    new QrCodeOptionsDTO(
        size: 1024,
        margin: 32,
        errorCorrection: QrCodeErrorCorrection::Medium,
        foregroundColor: '#1F2937',
        backgroundColor: '#FFFFFF',
        logo: new QrCodeLogoDTO($logoBytes, widthRatio: 0.2),
    ),
    QrCodeFormat::Svg,
);

return response($qr->contents, 200, [
    'Content-Type' => $qr->mimeType(),
    'Content-Disposition' => 'attachment; filename="qr.'.$qr->extension().'"',
]);
```

`RenderedQrCodeDTO` carries `contents` (the SVG document without XML declaration, or the PNG bytes), `format`, `mimeType()`, `extension()` and `dataUri()` (for an `<img src>`).

## Content

Only URLs of the site itself are encoded (`QrCodeContentPolicy`):

- `https`, with the same host and port as `app.url`; no user info, whitespace, control characters or backslashes; at most 1024 characters.
- Plain `http` only when the environment is `local` or `testing`.
- Without a readable `app.url`, every URL is rejected.

Anything else throws `App\Domain\QrCodes\Exceptions\InvalidQrCodeContentException`. Build URLs with `route()` or `url()`, never from user input.

## Options

`QrCodeOptionsDTO` validates itself and throws `InvalidQrCodeOptionsException` (in `App\Domain\QrCodes\Exceptions`):

| Option | Default | Rule |
|--------|---------|------|
| `size` | `512` | Width and height of the whole image in pixels, margin included: 128 to 2048. SVG scales anyway; pick a large PNG for print |
| `margin` | `16` | Quiet zone in pixels: 0 to a quarter of the size |
| `errorCorrection` | `Medium` | `Low`, `Medium`, `Quartile` or `High` (about 7, 15, 25 and 30 % recoverable) |
| `foregroundColor` | `#000000` | `#RRGGBB`, darker than the background |
| `backgroundColor` | `#FFFFFF` | `#RRGGBB`; WCAG contrast with the foreground of at least 4.5:1 |
| `logo` | `null` | `QrCodeLogoDTO`; forces `High` (see `effectiveErrorCorrection()`) |

`QrCodeLogoDTO` takes the logo **bytes** (PNG, JPEG or WebP, at most 1 MB and 2048×2048 px), never a path or a URL, and `widthRatio` (0.10 to 0.25 of the code's width, margin excluded; 0.20 by default). The logo keeps its proportions; the modules under it are replaced by the background colour.

Let a form validate colours and sizes first, then wrap the DTO in a `try` to turn `InvalidQrCodeOptionsException` into a field error.

## Failures

`QrCodeRenderingException` means valid content and options that still could not be drawn, usually a URL too long for the size with `High` correction. Use a shorter URL, a larger size or a lower level.

## Testing

`khanamiryan/qrcode-detector-decoder` is a development dependency of the core. `Tests\Support\QrCodes\QrCodeTestImages::decode($png)` returns the text of a PNG code, and `png()`, `jpeg()` and `webp()` build logos. In CI only GD is available, as in production, so the PNG path is the one tested.
