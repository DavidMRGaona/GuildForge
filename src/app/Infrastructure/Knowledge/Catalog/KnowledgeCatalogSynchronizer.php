<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Catalog;

use App\Application\Knowledge\DTOs\KnowledgeSyncReportDTO;
use App\Application\Knowledge\DTOs\ParsedGuideDTO;
use App\Application\Knowledge\Services\GuideRendererInterface;
use App\Application\Knowledge\Services\KnowledgeCatalogSynchronizerInterface;
use App\Domain\Knowledge\Entities\KnowledgeCategory;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Enums\GuideBodyFormat;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Knowledge\Exceptions\InvalidGuideDocumentException;
use App\Infrastructure\Knowledge\Parsing\GuideDocumentParser;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeCategoryModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideAliasModel;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

final readonly class KnowledgeCatalogSynchronizer implements KnowledgeCatalogSynchronizerInterface
{
    public function __construct(
        private KnowledgeSourceLocator $locator,
        private GuideDocumentParser $parser,
        private GuideRendererInterface $renderer,
    ) {}

    public function sync(): KnowledgeSyncReportDTO
    {
        return DB::transaction(function (): KnowledgeSyncReportDTO {
            $errors = [];
            $roots = $this->locator->roots();

            $this->syncCategories($roots, $errors);

            $isBaseline = ! KnowledgeGuideModel::query()->exists();
            $inserted = 0;
            $updated = 0;
            $seen = [];
            $failedSources = [];

            foreach ($this->collect($roots, $errors, $failedSources) as $entry) {
                $key = $entry['parsed']->id->value().'|'.$entry['locale'];
                if (isset($seen[$key])) {
                    $errors[] = sprintf(
                        '%s: guide id %s (%s) is already provided by %s',
                        $entry['file'],
                        $entry['parsed']->id->value(),
                        $entry['locale'],
                        $seen[$key],
                    );

                    continue;
                }
                $seen[$key] = $entry['file'];

                $result = $this->store($entry, $isBaseline);
                $inserted += $result === 'inserted' ? 1 : 0;
                $updated += $result === 'updated' ? 1 : 0;

                $this->syncAliases($entry['parsed']);
            }

            $deactivated = $this->deactivateMissing($seen, $failedSources);

            return new KnowledgeSyncReportDTO($inserted, $updated, $deactivated, $errors);
        });
    }

    /**
     * @param  list<array{path: string, module: ?string}>  $roots
     * @param  list<string>  $errors
     * @param  array<string, true>  $failedSources  sources (module name, or '' for the core) with an unreadable or invalid file
     * @return list<array{parsed: ParsedGuideDTO, locale: string, module: ?string, file: string}>
     */
    private function collect(array $roots, array &$errors, array &$failedSources): array
    {
        $entries = [];

        foreach ($roots as $root) {
            foreach ($this->localeDirectories($root['path']) as $locale => $directory) {
                foreach ($this->markdownFiles($directory) as $file) {
                    $contents = file_get_contents($file);
                    if ($contents === false) {
                        $errors[] = $file.': could not be read';
                        $failedSources[$root['module'] ?? ''] = true;

                        continue;
                    }

                    try {
                        $parsed = $this->parser->parse($file, $contents);
                    } catch (InvalidGuideDocumentException $e) {
                        $errors[] = $e->getMessage();
                        $failedSources[$root['module'] ?? ''] = true;

                        continue;
                    }

                    $entries[] = ['parsed' => $parsed, 'locale' => $locale, 'module' => $root['module'], 'file' => $file];
                }
            }
        }

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function localeDirectories(string $root): array
    {
        $directories = [];
        $names = scandir($root);
        foreach ($names === false ? [] : $names as $name) {
            if ($name[0] !== '.' && is_dir($root.'/'.$name)) {
                $directories[$name] = $root.'/'.$name;
            }
        }
        ksort($directories);

        return $directories;
    }

    /**
     * @return list<string>
     */
    private function markdownFiles(string $directory): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'md') {
                $files[] = $file->getPathname();
            }
        }
        sort($files);

        return $files;
    }

    /**
     * @param  array{parsed: ParsedGuideDTO, locale: string, module: ?string, file: string}  $entry
     * @return 'inserted'|'updated'|'unchanged'
     */
    private function store(array $entry, bool $isBaseline): string
    {
        $parsed = $entry['parsed'];
        $source = $entry['module'] === null ? GuideSource::Core : GuideSource::Module;

        $attributes = [
            'source' => $source->value,
            'module' => $entry['module'],
            'audience' => $parsed->audience->value,
            'category_id' => $parsed->categoryId,
            'sort_order' => $parsed->order,
            'title' => $parsed->title,
            'summary' => $parsed->summary,
            'keywords' => $parsed->keywords,
            'body_format' => GuideBodyFormat::Markdown->value,
            'body' => $parsed->body,
            'body_text' => $this->plainText($parsed->body, $entry['locale']),
            'conditions' => $parsed->conditions->toArray(),
            'related' => array_map(static fn ($id): string => $id->value(), $parsed->related),
            'featured' => $parsed->featured,
            'content_hash' => $parsed->contentHash,
            'active' => true,
        ];

        $existing = KnowledgeGuideModel::query()
            ->where('guide_id', $parsed->id->value())
            ->where('locale', $entry['locale'])
            ->first();

        if ($existing === null) {
            KnowledgeGuideModel::query()->create([
                ...$attributes,
                'guide_id' => $parsed->id->value(),
                'locale' => $entry['locale'],
                'first_seen_at' => $isBaseline ? null : now(),
                'content_changed_at' => null,
            ]);

            return 'inserted';
        }

        $changed = $existing->content_hash !== $parsed->contentHash
            || $existing->source !== $attributes['source']
            || $existing->module !== $attributes['module']
            || ! $existing->active;

        if (! $changed) {
            // The plain text embeds settings-dependent placeholders, so it is refreshed on every run;
            // this is not a content change and is not counted as an update.
            if ($existing->body_text !== $attributes['body_text']) {
                $existing->update(['body_text' => $attributes['body_text']]);
            }

            return 'unchanged';
        }

        if ($existing->content_hash !== $parsed->contentHash) {
            $attributes['content_changed_at'] = now();
        }
        $existing->update($attributes);

        return 'updated';
    }

    /**
     * Plain text in the guide's own locale, so translated fallbacks do not depend on the sync process.
     */
    private function plainText(string $body, string $locale): string
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return $this->renderer->toPlainText($body);
        } finally {
            App::setLocale($previous);
        }
    }

    private function syncAliases(ParsedGuideDTO $parsed): void
    {
        foreach ($parsed->previousIds as $alias) {
            KnowledgeGuideAliasModel::query()->updateOrCreate(
                ['alias_id' => $alias->value()],
                ['guide_id' => $parsed->id->value()],
            );
        }
    }

    /**
     * Guides of a source with a broken file keep their stored content: the file still exists, so they are not missing.
     *
     * @param  array<string, string>  $seen
     * @param  array<string, true>  $failedSources
     */
    private function deactivateMissing(array $seen, array $failedSources): int
    {
        $deactivated = 0;
        foreach (KnowledgeGuideModel::query()->where('active', true)->get() as $guide) {
            if (isset($failedSources[$guide->module ?? ''])) {
                continue;
            }
            if (! isset($seen[$guide->guide_id.'|'.$guide->locale])) {
                $guide->update(['active' => false]);
                $deactivated++;
            }
        }

        return $deactivated;
    }

    /**
     * @param  list<array{path: string, module: ?string}>  $roots
     * @param  list<string>  $errors
     */
    private function syncCategories(array $roots, array &$errors): void
    {
        $categories = CoreKnowledgeCategories::all();
        foreach ($roots as $root) {
            if ($root['module'] !== null) {
                array_push($categories, ...$this->moduleCategories($root['path'], $root['module'], $errors));
            }
        }

        $kept = [];
        foreach ($categories as $category) {
            if (isset($kept[$category->id])) {
                $errors[] = sprintf("category '%s' of module %s is already defined", $category->id, $category->module ?? 'core');

                continue;
            }
            $kept[$category->id] = true;

            KnowledgeCategoryModel::query()->updateOrCreate(
                ['category_id' => $category->id],
                [
                    'module' => $category->module,
                    'audience' => $category->audience->value,
                    'sort_order' => $category->order,
                    'icon' => $category->icon,
                    'label_key' => $category->labelKey,
                ],
            );
        }

        KnowledgeCategoryModel::query()->whereNotIn('category_id', array_keys($kept))->delete();
    }

    /**
     * @param  list<string>  $errors
     * @return list<KnowledgeCategory>
     */
    private function moduleCategories(string $root, string $module, array &$errors): array
    {
        $file = $root.'/categories.php';
        if (! is_file($file)) {
            return [];
        }

        try {
            $definitions = require $file;
        } catch (Throwable $e) {
            $errors[] = $file.': '.$e->getMessage();

            return [];
        }

        if (! is_array($definitions)) {
            $errors[] = $file.': must return a list of categories';

            return [];
        }

        $categories = [];
        foreach ($definitions as $definition) {
            $category = $this->category($definition, $module);
            if (! $category instanceof KnowledgeCategory) {
                $errors[] = $file.': invalid category definition (needs id, audience, order, icon and label_key)';

                continue;
            }
            $categories[] = $category;
        }

        return $categories;
    }

    private function category(mixed $definition, string $module): ?KnowledgeCategory
    {
        if (! is_array($definition)) {
            return null;
        }

        $id = $definition['id'] ?? null;
        $audience = is_string($definition['audience'] ?? null) ? GuideAudience::tryFrom($definition['audience']) : null;
        $order = $definition['order'] ?? null;
        $icon = $definition['icon'] ?? null;
        $labelKey = $definition['label_key'] ?? null;

        if (! is_string($id) || $id === '' || $audience === null || ! is_int($order)
            || ! is_string($icon) || $icon === '' || ! is_string($labelKey) || $labelKey === '') {
            return null;
        }

        return new KnowledgeCategory($id, $module, $audience, $order, $icon, $labelKey);
    }
}
