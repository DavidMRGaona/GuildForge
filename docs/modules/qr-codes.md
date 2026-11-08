# QR codes

Modules have no Composer dependencies of their own, so the core draws QR codes for them behind a contract. Any module can print a code for one of the site's pages: a link page, an event sign-up, a table poster. For the rest of the module SDK, see the [module development guide](./README.md).

Requirement: set `requires.core` in `module.json` to the core release that introduces the renderer (the one with core#350). An older core has no `QrCodeRendererInterface` binding. SVG logos need the release with core#410; before it, `QrCodeLogoDTO` rejects them.

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

`QrCodeLogoDTO` takes the logo **bytes** (PNG, JPEG, WebP or SVG, at most 1 MB; raster images at most 2048×2048 px), never a path or a URL, and `widthRatio` (0.10 to 0.25 of the code's width, margin excluded; 0.20 by default). The logo keeps its proportions; the modules under it are replaced by the background colour.

### SVG logos

An SVG logo (core#410) works in both formats, so the site logo, usually an SVG, can go straight in:

- **Size**: taken from the `viewBox`, or else from `width` and `height` in absolute units (`px` or none, `pt`, `pc`, `mm`, `cm`, `in`). Without either, or with percentages, the DTO throws. `mimeType` is `image/svg+xml`, `isSvg()` is true, and `width`/`height` give only the proportion, with the longer side at 2048.
- **Sanitized in the DTO** (`App\Application\QrCodes\SvgLogoSanitizer`), so `contents` holds the clean markup, never the uploaded one. A `DOCTYPE` or entity, broken XML or a root other than `<svg>` in the SVG namespace throws. Everything else outside an allowlist is dropped and the rest of the logo stays: elements other than static SVG (`script`, `foreignObject`, `a`, `feImage`, animation, editor metadata, XInclude…), event handlers (`on*`), attributes of other namespaces, comments and processing instructions, and every reference outside the document. `href`/`xlink:href` may only point to a `#id` (or, on `<image>`, to an embedded PNG, JPEG or WebP `data:` URI); `url()` only to a `#id`; `@import`, other resource functions (`image-set()`…) and CSS escapes drop the attribute or style sheet. The markup keeps only its `viewBox`, so it fills whatever box it is drawn in.
- **SVG output**: embedded as a `data:image/svg+xml` `<image>` with explicit width and height in the logo's proportion, so its styles and ids stay apart from the code's.
- **PNG output**: GD reads no SVG, so `rsvg-convert` (librsvg) draws the sanitized markup at the exact size the logo takes. It runs in a process of its own, with the SVG on stdin (no base path for relative references), an empty environment, at most 10 s and 1 GB of address space. If it is missing or fails, `render()` throws `QrCodeRenderingException`; the SVG output does not need it.
- **Text**: the images bring no fonts, so `<text>` may draw in a fallback font or not at all. Convert text to paths in the editor before uploading.

Error correction stays `High` and the colour checks stay the same as with a raster logo.

Let a form validate colours and sizes first, then wrap the DTO in a `try` to turn `InvalidQrCodeOptionsException` into a field error.

## Failures

`QrCodeRenderingException` means valid content and options that still could not be drawn, usually a URL too long for the size with `High` correction. Use a shorter URL, a larger size or a lower level.

## Testing

`khanamiryan/qrcode-detector-decoder` is a development dependency of the core. `Tests\Support\QrCodes\QrCodeTestImages::decode($png)` returns the text of a PNG code, and `png()`, `jpeg()`, `webp()` and `svg()` build logos. The renderer only uses GD, as in production, so the PNG path is the one tested. `rsvg-convert` is in the development image, and the core and module CI install it (`.github/actions/svg-rasterizer`, `librsvg2-bin`), so tests with SVG logos run everywhere. `decode()` reads with GD too, even where Imagick is loaded (the CI runners load it): the decoder would otherwise pick Imagick, which needs about four times the memory (over 300 MB for a 2048 px code) and ran paratest workers out of memory. Pass `useImagickIfAvailable: false` if a module test builds its own `QrReader`.
