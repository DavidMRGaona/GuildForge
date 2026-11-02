<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Rendering\Extensions;

use App\Infrastructure\Knowledge\Rendering\ActionLinkResolver;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;

final readonly class ActionButtonExtension implements ExtensionInterface
{
    public function __construct(
        private ?ActionLinkResolver $resolver,
        private ?object $user,
        private ?string $module,
    ) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser(new ActionButtonParser, 100);
        $environment->addRenderer(ActionButton::class, new ActionButtonRenderer($this->resolver, $this->user, $this->module));
    }
}
