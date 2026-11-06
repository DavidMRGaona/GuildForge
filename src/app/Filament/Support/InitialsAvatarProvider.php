<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Providers\Filament\AdminPanelProvider;
use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * The panel's avatar for records without a picture: their initials in an inline SVG, so the
 * browser asks no third party for it (Filament's default draws them at ui-avatars.com).
 */
final class InitialsAvatarProvider implements AvatarProvider
{
    private const string DATA_URI_PREFIX = 'data:image/svg+xml;base64,';

    public function get(Model|Authenticatable $record): string
    {
        return self::svgDataUri(Filament::getNameForDefaultAvatar($record));
    }

    public static function svgDataUri(string $name): string
    {
        $initials = htmlspecialchars(self::initials($name), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $background = AdminPanelProvider::GRAY_PALETTE[700];

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="'.$background.'"/>'
            .'<text x="50%" y="50%" dy=".35em" fill="#ffffff" font-family="ui-sans-serif, system-ui, sans-serif"'
            .' font-size="26" font-weight="600" text-anchor="middle">'.$initials.'</text>'
            .'</svg>';

        return self::DATA_URI_PREFIX.base64_encode($svg);
    }

    /**
     * The first letter of the first two words, in upper case; a question mark for a blank name.
     */
    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if ($words === false || $words === []) {
            return '?';
        }

        $initials = array_map(
            static fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)),
            array_slice($words, 0, 2),
        );

        return implode('', $initials);
    }
}
