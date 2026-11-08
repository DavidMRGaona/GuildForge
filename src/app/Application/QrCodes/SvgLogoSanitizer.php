<?php

declare(strict_types=1);

namespace App\Application\QrCodes;

use App\Domain\QrCodes\Exceptions\InvalidQrCodeOptionsException;
use DOMAttr;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Turns an uploaded SVG logo into static markup that refers to nothing outside itself, before
 * it is embedded in a QR code or rasterized. SVG renderers have read local files and made
 * network requests through entities, href, url() and @import, so only an allowlist survives:
 * static SVG elements, attributes without event handlers, and references to #ids of the same
 * document (plus embedded PNG, JPEG or WebP images). Anything else is dropped; a DTD, broken
 * XML or a logo without a usable size is rejected.
 */
final class SvgLogoSanitizer
{
    private const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const string XLINK_NAMESPACE = 'http://www.w3.org/1999/xlink';

    private const string XML_NAMESPACE = 'http://www.w3.org/XML/1998/namespace';

    /**
     * Static SVG: structure, shapes, text, paint servers, clipping, masking and filters. Not
     * here on purpose: script, foreignObject, a, switch, feImage, animation and anything
     * outside the SVG namespace (editor metadata, XInclude, XHTML).
     */
    private const array ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'style',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'image',
        'text', 'tspan', 'textPath',
        'linearGradient', 'radialGradient', 'stop', 'pattern', 'clipPath', 'mask', 'marker',
        'filter', 'feBlend', 'feColorMatrix', 'feComponentTransfer', 'feComposite', 'feConvolveMatrix',
        'feDiffuseLighting', 'feDisplacementMap', 'feDistantLight', 'feDropShadow', 'feFlood',
        'feFuncA', 'feFuncB', 'feFuncG', 'feFuncR', 'feGaussianBlur', 'feMerge', 'feMergeNode',
        'feMorphology', 'feOffset', 'fePointLight', 'feSpecularLighting', 'feSpotLight', 'feTile',
        'feTurbulence',
    ];

    /** CSS functions that load a resource; url() is allowed for #ids only */
    private const array RESOURCE_FUNCTIONS = ['image', 'image-set', '-webkit-image-set', 'src', 'element', 'cross-fade', '-webkit-cross-fade'];

    private const string FRAGMENT = '/\A#[A-Za-z_][\w.:-]*\z/';

    private const string RASTER_DATA_URI = '#\Adata:image/(?:png|jpeg|webp);base64,[A-Za-z0-9+/=\s]*\z#';

    /** CSS pixels per unit of an absolute length */
    private const array PIXELS_PER_UNIT = [
        '' => 1.0,
        'px' => 1.0,
        'pt' => 96 / 72,
        'pc' => 16.0,
        'mm' => 96 / 25.4,
        'cm' => 96 / 2.54,
        'in' => 96.0,
    ];

    /**
     * @throws InvalidQrCodeOptionsException when the SVG cannot be used safely
     */
    public static function sanitize(string $contents): SanitizedSvgLogo
    {
        // Entities read files or expand without bound; a logo never needs a DTD
        if (stripos($contents, '<!DOCTYPE') !== false || stripos($contents, '<!ENTITY') !== false) {
            throw InvalidQrCodeOptionsException::because('the SVG logo must not have a DOCTYPE or entities');
        }

        $root = self::parse($contents);
        [$width, $height] = self::size($root);

        self::clean($root);

        // Only the viewBox gives the proportion, so the logo fills whatever box it is drawn in
        if (! $root->hasAttribute('viewBox')) {
            $root->setAttribute('viewBox', sprintf('0 0 %s %s', self::number($width), self::number($height)));
        }
        $root->removeAttribute('width');
        $root->removeAttribute('height');

        $markup = $root->ownerDocument?->saveXML($root);
        if (! is_string($markup) || $markup === '') {
            throw InvalidQrCodeOptionsException::because('the SVG logo cannot be written back');
        }

        return new SanitizedSvgLogo($markup, $width, $height);
    }

    private static function parse(string $contents): DOMElement
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);

        try {
            // No LIBXML_NOENT or LIBXML_DTDLOAD: nothing is substituted or fetched
            $loaded = $contents !== '' && $document->loadXML($contents, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $document->doctype instanceof \DOMDocumentType) {
            throw InvalidQrCodeOptionsException::because('the SVG logo is not well-formed XML');
        }

        $root = $document->documentElement;
        if (! $root instanceof DOMElement || $root->namespaceURI !== self::SVG_NAMESPACE || $root->localName !== 'svg') {
            throw InvalidQrCodeOptionsException::because('the SVG logo must have an <svg> root in the SVG namespace');
        }

        return $root;
    }

    /**
     * From the viewBox, or else from absolute width and height.
     *
     * @return array{float, float}
     */
    private static function size(DOMElement $root): array
    {
        if ($root->hasAttribute('viewBox')) {
            $values = preg_split('/[\s,]+/', trim($root->getAttribute('viewBox')));
            if ($values === false || count($values) !== 4 || array_filter($values, is_numeric(...)) !== $values) {
                throw InvalidQrCodeOptionsException::because('the SVG logo has an invalid viewBox');
            }

            return self::positive((float) $values[2], (float) $values[3]);
        }

        $width = self::length($root->getAttribute('width'));
        $height = self::length($root->getAttribute('height'));
        if ($width === null || $height === null) {
            throw InvalidQrCodeOptionsException::because('the SVG logo needs a viewBox, or a width and a height in absolute units');
        }

        return self::positive($width, $height);
    }

    /**
     * @return array{float, float}
     */
    private static function positive(float $width, float $height): array
    {
        if (! is_finite($width) || ! is_finite($height) || $width <= 0 || $height <= 0) {
            throw InvalidQrCodeOptionsException::because('the SVG logo must have a positive width and height');
        }

        return [$width, $height];
    }

    private static function length(string $value): ?float
    {
        if (preg_match('/\A\s*\+?((?:\d+(?:\.\d*)?|\.\d+)(?:e[+-]?\d+)?)\s*([a-z]*)\s*\z/i', $value, $matches) !== 1) {
            return null;
        }

        // Percentages and font-relative units have no size outside a page
        $unit = strtolower($matches[2]);
        if (! isset(self::PIXELS_PER_UNIT[$unit])) {
            return null;
        }

        return (float) $matches[1] * self::PIXELS_PER_UNIT[$unit];
    }

    private static function clean(DOMElement $element): void
    {
        self::cleanAttributes($element);

        // A style sheet that could load something goes as a whole
        if ($element->localName === 'style' && ! self::isSafeCss($element->textContent)) {
            $element->parentNode?->removeChild($element);

            return;
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            if ($child instanceof DOMElement && self::isAllowedElement($child)) {
                self::clean($child);
            } elseif (! $child instanceof DOMText) {
                // Unknown elements, comments, processing instructions and entity references;
                // text and CDATA (DOMCdataSection is a DOMText) stay
                self::remove($child);
            }
        }
    }

    private static function cleanAttributes(DOMElement $element): void
    {
        /** @var list<DOMAttr> $attributes */
        $attributes = iterator_to_array($element->attributes, false);

        foreach ($attributes as $attribute) {
            if (! self::isAllowedAttribute($element, $attribute)) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    private static function isAllowedElement(DOMElement $element): bool
    {
        return $element->namespaceURI === self::SVG_NAMESPACE && in_array($element->localName, self::ELEMENTS, true);
    }

    private static function isAllowedAttribute(DOMElement $element, DOMAttr $attribute): bool
    {
        $name = (string) $attribute->localName;
        $value = $attribute->value;

        return match ($attribute->namespaceURI) {
            // A colon here is an undeclared prefix (libxml only warns), e.g. a stray xlink:href
            null => preg_match('/\A[A-Za-z][\w.-]*\z/', $name) === 1
                && ! str_starts_with(strtolower($name), 'on')
                && ($name === 'href' ? self::isSafeReference($element, $value) : self::isSafeCss($value)),
            self::XLINK_NAMESPACE => $name === 'href' && self::isSafeReference($element, $value),
            self::XML_NAMESPACE => in_array($name, ['space', 'lang'], true),
            default => false,
        };
    }

    private static function isSafeReference(DOMElement $element, string $value): bool
    {
        $value = trim($value);

        if (preg_match(self::FRAGMENT, $value) === 1) {
            return true;
        }

        return $element->localName === 'image' && preg_match(self::RASTER_DATA_URI, $value) === 1;
    }

    /**
     * Any attribute value or style sheet: url() only to #ids, no other resource function, no
     *
     * @import, and no backslash, since CSS escapes could spell a function name in disguise.
     */
    private static function isSafeCss(string $value): bool
    {
        if (str_contains($value, '\\') || stripos($value, '@import') !== false) {
            return false;
        }

        preg_match_all('/([a-z-]+)\s*\(/i', $value, $functions);
        foreach ($functions[1] as $function) {
            if (in_array(strtolower($function), self::RESOURCE_FUNCTIONS, true)) {
                return false;
            }
        }

        $urls = preg_match_all('/url\s*\(/i', $value);
        $fragmentUrls = preg_match_all('/url\s*\(\s*([\'"]?)\s*#[A-Za-z_][\w.:-]*\s*\1\s*\)/i', $value);

        return $urls === $fragmentUrls;
    }

    private static function remove(DOMNode $node): void
    {
        $node->parentNode?->removeChild($node);
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }
}
