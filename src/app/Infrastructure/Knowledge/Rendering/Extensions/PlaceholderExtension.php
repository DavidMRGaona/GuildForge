<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

final readonly class PlaceholderExtension implements ExtensionInterface
{
    /**
     * @param  array<string, string>  $values
     */
    public function __construct(private array $values) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new PlaceholderParser($this->values), 100);
    }
}
