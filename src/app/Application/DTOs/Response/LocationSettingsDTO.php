<?php

declare(strict_types=1);

namespace App\Application\DTOs\Response;

final readonly class LocationSettingsDTO
{
    public function __construct(
        public string $name,
        public string $address,
        public float $lat,
        public float $lng,
        public int $zoom,
    ) {
    }

    /**
     * Create from array of settings.
     *
     * @param  array{name?: string, address?: string, lat?: float, lng?: float, zoom?: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'] ?? '',
            address: $data['address'] ?? '',
            lat: $data['lat'] ?? 0.0,
            lng: $data['lng'] ?? 0.0,
            zoom: $data['zoom'] ?? 10,
        );
    }

    /**
     * Convert to array for frontend consumption.
     *
     * @return array{name: string, address: string, lat: float, lng: float, zoom: int}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'address' => $this->address,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'zoom' => $this->zoom,
        ];
    }
}
