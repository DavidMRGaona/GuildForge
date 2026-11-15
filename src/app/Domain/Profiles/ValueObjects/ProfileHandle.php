<?php

declare(strict_types=1);

namespace App\Domain\Profiles\ValueObjects;

use App\Domain\Profiles\Exceptions\InvalidProfileHandleException;
use Stringable;

/**
 * The address of a public profile, `/miembros/<handle>` (core#739): 3 to 30 lowercase letters and digits,
 * single hyphens between them, and never a reserved word. Every handle ever used is kept, as its hash, in
 * the history, so an old link never points to another person.
 */
final readonly class ProfileHandle implements Stringable
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 30;

    public const string PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * Words of the profile routes and of the site, and names that would pass for the site itself.
     *
     * @var list<string>
     */
    public const array RESERVED = [
        // Profile routes (core#741, #743, #744)
        'editar', 'tarjeta', 'banner', 'disponibilidad', 'disponible', 'nuevo', 'nueva', 'crear', 'borrar', 'eliminar',
        // Site and framework paths
        'admin', 'api', 'app', 'up', 'livewire', 'broadcasting', 'storage', 'build', 'static', 'assets',
        'miembros', 'miembro', 'perfil', 'perfiles', 'ajustes', 'cuenta', 'configuracion', 'registro', 'login',
        'logout', 'entrar', 'salir', 'buscar', 'avisos', 'ayuda', 'foro', 'eventos', 'articulos', 'galeria',
        'calendario', 'nosotros', 'contacto', 'legal', 'privacidad', 'cookies', 'enlaces',
        // Names that would pass for the site or its staff
        'administrador', 'administradora', 'administracion', 'moderador', 'moderadora', 'moderacion', 'staff',
        'soporte', 'sistema', 'root', 'guildforge', 'anonimo', 'anonima', 'yo', 'me', 'null', 'undefined',
    ];

    private function __construct(
        public string $value,
    ) {}

    /**
     * @throws InvalidProfileHandleException
     */
    public static function fromString(string $value): self
    {
        $normalised = self::normalise($value);
        $length = strlen($normalised);

        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH || preg_match(self::PATTERN, $normalised) !== 1) {
            throw InvalidProfileHandleException::malformed();
        }

        if (self::isReserved($normalised)) {
            throw InvalidProfileHandleException::reserved();
        }

        return new self($normalised);
    }

    public static function tryFrom(string $value): ?self
    {
        try {
            return self::fromString($value);
        } catch (InvalidProfileHandleException) {
            return null;
        }
    }

    public static function isReserved(string $value): bool
    {
        return in_array(self::normalise($value), self::RESERVED, true);
    }

    /**
     * What the history stores: the SHA-256 of the normalised handle.
     */
    public static function hashOf(string $value): string
    {
        return hash('sha256', self::normalise($value));
    }

    public function hash(): string
    {
        return self::hashOf($this->value);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private static function normalise(string $value): string
    {
        return strtolower(trim($value));
    }
}
