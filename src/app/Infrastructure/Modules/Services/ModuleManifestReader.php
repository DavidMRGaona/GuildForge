<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\ModuleManifestDTO;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use TypeError;

/**
 * Reads any module.json (installed, staged or extracted from a ZIP) without loading module code.
 */
final readonly class ModuleManifestReader
{
    public function __construct(
        private LoggerInterface $logger = new NullLogger(),
    ) {
    }

    /**
     * A bad repository or requirement never aborts the read: the repository is dropped
     * and the requirement becomes an invalid constraint, which rejects the module.
     *
     * @throws InvalidArgumentException When the file cannot be read, is not a JSON object or lacks required fields
     * @throws TypeError When a required field has the wrong type ("name": 123)
     */
    public function read(string $path): ModuleManifestDTO
    {
        $content = @file_get_contents($path);

        if ($content === false) {
            throw new InvalidArgumentException("Cannot read manifest file: {$path}");
        }

        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidArgumentException('Invalid JSON: '.json_last_error_msg());
        }

        if (! is_array($data) || ($data !== [] && array_is_list($data))) {
            throw new InvalidArgumentException("The manifest is not a JSON object: {$path}");
        }

        // A bad repository only disables updates for this module; it must not block discovery
        if (isset($data['repository']) && ! ModuleManifestDTO::isValidRepository($data['repository'])) {
            $this->logger->warning("Ignoring invalid repository in {$path}, expected 'owner/repo'");
            unset($data['repository']);
        }

        if (array_key_exists('requires', $data)) {
            $normalized = ModuleManifestDTO::normalizeRequires($data['requires']);

            foreach ($normalized['invalid'] as $key) {
                $label = $key === 'requires' ? 'requires' : "requires.{$key}";
                $this->logger->warning("Invalid {$label} in {$path}: the module is treated as incompatible");
            }

            $data['requires'] = $normalized['requires'];
        }

        /** @var array<string, mixed> $data */
        return ModuleManifestDTO::fromArray($data);
    }
}
