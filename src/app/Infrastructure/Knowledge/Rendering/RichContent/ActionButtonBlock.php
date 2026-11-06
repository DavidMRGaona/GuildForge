<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\RichContent;

use App\Infrastructure\Knowledge\Rendering\ActionLinkResolver;
use Filament\Forms\Components\RichEditor\RichContentCustomBlock;

/**
 * The rich editor's counterpart of `[[action:route|Label|if=condition]]`: a button to a page of the
 * application, resolved for the person reading the guide. The panel's editor extends it with its form.
 *
 * Config: `target` (route name, optionally followed by `?key=value&…`), `label`, and optionally `condition`
 * (permission or capability key) with `negate`.
 */
class ActionButtonBlock extends RichContentCustomBlock
{
    public const string ID = 'kb-action';

    public static function getId(): string
    {
        return self::ID;
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $data  `resolver`, `user` and `module` given by the renderer
     */
    public static function toHtml(array $config, array $data): string
    {
        $resolver = $data['resolver'] ?? null;
        $user = $data['user'] ?? null;
        $module = $data['module'] ?? null;
        $target = self::target($config);
        $label = self::label($config);

        if (! $resolver instanceof ActionLinkResolver || ! is_object($user) || $target === null || $label === null) {
            return '';
        }

        $url = $resolver->resolve($target, self::condition($config), is_string($module) ? $module : null, $user);
        if ($url === null) {
            return '';
        }

        return sprintf(
            '<p><a class="kb-action" href="%s">%s</a></p>',
            htmlspecialchars($url, ENT_QUOTES),
            htmlspecialchars($label, ENT_QUOTES),
        );
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function target(array $config): ?string
    {
        return self::text($config, 'target');
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function label(array $config): ?string
    {
        return self::text($config, 'label');
    }

    /**
     * The `if=` condition: a permission or capability key, prefixed with `!` when `negate` is set.
     *
     * @param  array<string, mixed>  $config
     */
    public static function condition(array $config): ?string
    {
        $condition = self::text($config, 'condition');
        if ($condition === null || str_starts_with($condition, '!') || ($config['negate'] ?? false) !== true) {
            return $condition;
        }

        return '!'.$condition;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private static function text(array $config, string $key): ?string
    {
        $value = $config[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
