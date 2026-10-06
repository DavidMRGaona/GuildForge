<?php

declare(strict_types=1);

namespace App\View\Modules;

use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Modules\ValueObjects\CompatibilityResult;

/**
 * Turns compatibility issues (objects or their persisted arrays) into the admin's language.
 * Shared by the module pages, the panel banner and the module commands.
 */
final class CompatibilityIssueFormatter
{
    /**
     * @param  CompatibilityIssue|array<string, mixed>  $issue
     */
    public function format(CompatibilityIssue|array $issue): string
    {
        $issue = $this->issue($issue);

        return $this->translate($issue->reasonKey, $this->replacements($issue));
    }

    /**
     * Without the installed version: "requiere core ^3.0".
     *
     * @param  CompatibilityIssue|array<string, mixed>  $issue
     */
    public function formatShort(CompatibilityIssue|array $issue): string
    {
        $issue = $this->issue($issue);

        if ($issue->reasonKey === CompatibilityIssue::UNSATISFIED) {
            return $this->translate('modules.compatibility.short.unsatisfied', $this->replacements($issue));
        }

        return $this->format($issue);
    }

    /**
     * @param  array<int, CompatibilityIssue|array<string, mixed>>  $issues
     */
    public function summary(array $issues, bool $short = false): string
    {
        return implode('; ', array_map(
            fn (CompatibilityIssue|array $issue): string => $short ? $this->formatShort($issue) : $this->format($issue),
            $issues,
        ));
    }

    /**
     * "sí", or "no: <first reason>" with "(+N)" when there are more.
     */
    public function listLabel(CompatibilityResult $result): string
    {
        if ($result->isCompatible()) {
            return $this->translate('modules.compatibility.list.yes');
        }

        $label = $this->translate('modules.compatibility.list.no', ['reason' => $this->formatShort($result->issues[0])]);
        $more = count($result->issues) - 1;

        return $more > 0 ? "{$label} (+{$more})" : $label;
    }

    /**
     * @param  array<int, CompatibilityIssue|array<string, mixed>>  $issues
     */
    public function cannotEnable(string $name, array $issues): string
    {
        return $this->translate('modules.filament.notifications.incompatible', ['name' => $name, 'reasons' => $this->summary($issues)]);
    }

    /**
     * @param  array<int, CompatibilityIssue|array<string, mixed>>  $issues
     */
    public function cannotSeed(string $name, array $issues): string
    {
        return $this->translate('modules.compatibility.cannot_seed', ['name' => $name, 'reasons' => $this->summary($issues)]);
    }

    /**
     * @param  array<int, CompatibilityIssue|array<string, mixed>>  $issues
     */
    public function incompatiblePackage(string $name, ?string $version, array $issues): string
    {
        return $this->translate('modules.filament.notifications.incompatible_package', [
            'name' => $name,
            'version' => $version ?? '',
            'reasons' => $this->summary($issues),
        ]);
    }

    /**
     * @param  CompatibilityIssue|array<string, mixed>  $issue
     */
    private function issue(CompatibilityIssue|array $issue): CompatibilityIssue
    {
        return $issue instanceof CompatibilityIssue ? $issue : CompatibilityIssue::fromArray($issue);
    }

    /**
     * @return array<string, string>
     */
    private function replacements(CompatibilityIssue $issue): array
    {
        return [
            'requirement' => $this->translate('modules.compatibility.requirements.'.$issue->requirement->value),
            'required' => $issue->required,
            'found' => $issue->found ?? '',
        ];
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function translate(string $key, array $replace = []): string
    {
        $translated = __($key, $replace);

        return is_string($translated) ? $translated : $key;
    }
}
