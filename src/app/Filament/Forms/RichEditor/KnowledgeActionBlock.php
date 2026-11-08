<?php

declare(strict_types=1);

namespace App\Filament\Forms\RichEditor;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Filament\Support\KnowledgeActionTargets;
use App\Infrastructure\Knowledge\Rendering\RichContent\ActionButtonBlock;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

/**
 * The guide editor's "button to a page": the form that fills the block, and its preview.
 * Rendering for readers is ActionButtonBlock's job.
 */
final class KnowledgeActionBlock extends ActionButtonBlock
{
    public static function getLabel(): string
    {
        return __('filament.knowledge_guides.blocks.action.label');
    }

    public static function getIcon(): string
    {
        return 'heroicon-o-cursor-arrow-rays';
    }

    public static function configureEditorAction(Action $action): Action
    {
        return $action
            ->modalHeading(__('filament.knowledge_guides.blocks.action.heading'))
            ->modalDescription(__('filament.knowledge_guides.blocks.action.description'))
            ->schema([
                Select::make('target')
                    ->label(__('filament.knowledge_guides.blocks.action.target'))
                    ->options(KnowledgeActionTargets::grouped())
                    ->formatStateUsing(static fn (?string $state): ?string => $state === null ? null : KnowledgeActionTargets::canonical($state))
                    ->searchable()
                    ->native(false)
                    ->required(),
                TextInput::make('label')
                    ->label(__('filament.knowledge_guides.blocks.action.text'))
                    ->required()
                    ->maxLength(80),
                Select::make('condition')
                    ->label(__('filament.knowledge_guides.blocks.action.condition'))
                    ->helperText(__('filament.knowledge_guides.blocks.action.condition_help'))
                    ->options(self::conditionOptions())
                    ->searchable()
                    ->native(false)
                    ->nullable(),
                Toggle::make('negate')
                    ->label(__('filament.knowledge_guides.blocks.action.negate')),
            ]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function getPreviewLabel(array $config): string
    {
        return __('filament.knowledge_guides.blocks.action.preview', ['label' => self::label($config) ?? '']);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function toPreviewHtml(array $config): string
    {
        $target = self::target($config) ?? '';
        $page = KnowledgeActionTargets::label($target)
            ?? __('filament.knowledge_guides.blocks.action.missing_page', ['target' => $target]);

        return sprintf(
            '<span class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-3 py-1.5 text-sm font-semibold text-white">%s</span> <span class="text-sm text-gray-500 dark:text-gray-400">→ %s</span>',
            e(self::label($config) ?? ''),
            e($page),
        );
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function conditionOptions(): array
    {
        $permissions = [];
        foreach (resolve(PermissionRegistryInterface::class)->all() as $permission) {
            $permissions[$permission->key] = $permission->label;
        }
        asort($permissions, SORT_NATURAL | SORT_FLAG_CASE);

        $capabilities = [];
        foreach (resolve(KnowledgeCapabilityRegistryInterface::class)->keys() as $key) {
            $capabilities[$key] = $key;
        }
        ksort($capabilities);

        return array_filter([
            (string) __('filament.knowledge_guides.blocks.action.permissions') => $permissions,
            (string) __('filament.knowledge_guides.blocks.action.capabilities') => $capabilities,
        ]);
    }
}
