<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Contracts\ReleasesMediaThroughCoreInterface;
use App\Application\Media\DTOs\MediaUsageDTO;
use App\Application\Media\Services\MediaUrlExtractorInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\Knowledge\Enums\GuideSource;
use App\Domain\Media\ValueObjects\MediaFileId;
use App\Filament\Pages\Settings\AboutPageSettings;
use App\Filament\Pages\Settings\LegalPagesSettings;
use App\Filament\Resources\ArticleResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\KnowledgeGuideResource;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use App\Infrastructure\Media\Sources\StoredMediaIdPrefilter;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Files of this site whose delivery URL sits inside a core rich text: event descriptions, article contents,
 * the body of the site's own guides, the legal texts and the «Nosotros» history. Any URL counts, in an
 * attribute (src, href, srcset) or as bare text, whatever its transformation or version.
 *
 * The record id is `<kind>:<id>` (`events:<uuid>`, `settings:legal_terms_content`). It claims no folder:
 * the images it protects belong to the folders of other sources.
 */
final readonly class RichTextMediaSource implements MediaUsageSourceInterface, ReleasesMediaThroughCoreInterface
{
    public const string TYPE = 'rich_text';

    /** Settings keys holding rich text, with the page that edits them */
    public const array SETTING_PAGES = [
        'legal_privacy_content' => LegalPagesSettings::class,
        'legal_notice_content' => LegalPagesSettings::class,
        'legal_cookies_content' => LegalPagesSettings::class,
        'legal_terms_content' => LegalPagesSettings::class,
        'about_history' => AboutPageSettings::class,
    ];

    /** Both settings pages are behind the same permission */
    private const string SETTINGS_PERMISSION = 'pages.manage';

    private const string SETTINGS_KIND = 'settings';

    /** Rows of text read at a time */
    private const int CHUNK_SIZE = 200;

    public function __construct(
        private MediaUrlExtractorInterface $urls,
        private SettingsServiceInterface $settings,
        private AuthorizationServiceInterface $authorization,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function label(): string
    {
        return __('filament.media_library.sources.rich_text');
    }

    public function folders(): array
    {
        return [];
    }

    public function excludedSubfolders(): array
    {
        return [];
    }

    public function hidden(): bool
    {
        return false;
    }

    /**
     * @return iterable<MediaUsageDTO>
     */
    public function usages(): iterable
    {
        foreach (array_keys($this->models()) as $kind) {
            $column = $this->models()[$kind]['column'];
            $records = $this->query($kind)
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->lazyById(self::CHUNK_SIZE);

            foreach ($records as $record) {
                yield from $this->usagesOfRecord($kind, $record);
            }
        }

        foreach (array_keys(self::SETTING_PAGES) as $key) {
            yield from $this->usagesOfSetting($key);
        }
    }

    /**
     * @return list<MediaUsageDTO>
     */
    public function usagesFor(string $recordId): array
    {
        [$kind, $id] = $this->split($recordId);

        if ($kind === self::SETTINGS_KIND) {
            return array_key_exists($id, self::SETTING_PAGES) ? $this->usagesOfSetting($id) : [];
        }

        $record = $this->find($kind, $id);

        return $record instanceof Model ? $this->usagesOfRecord($kind, $record) : [];
    }

    public function usesFile(MediaFileId $file): bool
    {
        foreach ($this->models() as $kind => $definition) {
            $column = $definition['column'];
            $query = $this->query($kind)->select([$column])->whereNotNull($column);
            $like = StoredMediaIdPrefilter::likeClause($query->getQuery()->getGrammar(), $column, $file);

            if ($like !== null) {
                // @phpstan-ignore argument.type
                $query->whereRaw($like[0], $like[1]);
            }

            foreach ($query->lazy(self::CHUNK_SIZE) as $record) {
                if (isset($this->urls->fileIdsIn($record->getAttribute($column))[$file->value])) {
                    return true;
                }
            }
        }

        return array_any(array_keys(self::SETTING_PAGES), fn (string $key): bool => isset($this->urls->fileIdsIn($this->settings->get($key))[$file->value]));
    }

    public function editUrl(string $recordId, object $user): ?string
    {
        [$kind, $id] = $this->split($recordId);

        if ($kind === self::SETTINGS_KIND) {
            $page = self::SETTING_PAGES[$id] ?? null;

            return $page !== null && $this->authorization->can($user, self::SETTINGS_PERMISSION) ? $page::getUrl() : null;
        }

        $record = $this->find($kind, $id);

        if (! $record instanceof Model || ! Gate::forUser($user)->allows('update', $record)) {
            return null;
        }

        return match ($kind) {
            'events' => EventResource::getUrl('edit', ['record' => $record]),
            'articles' => ArticleResource::getUrl('edit', ['record' => $record]),
            'guides' => KnowledgeGuideResource::getUrl('edit', ['record' => $record]),
            default => null,
        };
    }

    /**
     * The models whose text is read, by the kind that prefixes their record ids.
     *
     * @return array<string, array{model: class-string<Model>, column: string}>
     */
    private function models(): array
    {
        return [
            'events' => ['model' => EventModel::class, 'column' => 'description'],
            'articles' => ['model' => ArticleModel::class, 'column' => 'content'],
            'guides' => ['model' => KnowledgeGuideModel::class, 'column' => 'body'],
        ];
    }

    /**
     * @return Builder<Model>
     */
    private function query(string $kind): Builder
    {
        // Rows hidden by a global scope (drafts kept out of the panel, core#570) still use their files
        $query = $this->models()[$kind]['model']::query()->withoutGlobalScopes();

        // Official guides come from files shipped with the code, never with this site's files
        if ($kind === 'guides') {
            $query->where('source', GuideSource::Site->value);
        }

        return $query;
    }

    private function find(string $kind, string $id): ?Model
    {
        // Every model read here has UUID keys; PostgreSQL rejects a malformed one instead of finding nothing
        if (! array_key_exists($kind, $this->models()) || ! Str::isUuid($id)) {
            return null;
        }

        return $this->query($kind)->whereKey($id)->first();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $recordId): array
    {
        $parts = explode(':', $recordId, 2);

        return count($parts) === 2 ? [$parts[0], $parts[1]] : ['', $recordId];
    }

    /**
     * @return list<MediaUsageDTO>
     */
    private function usagesOfRecord(string $kind, Model $record): array
    {
        $key = $record->getKey();

        return $this->usagesIn(
            $record->getAttribute($this->models()[$kind]['column']),
            $kind.':'.(is_scalar($key) ? (string) $key : ''),
            (string) $record->getAttribute('title'),
        );
    }

    /**
     * @return list<MediaUsageDTO>
     */
    private function usagesOfSetting(string $key): array
    {
        return $this->usagesIn(
            $this->settings->get($key),
            self::SETTINGS_KIND.':'.$key,
            __('filament.media_library.rich_text_titles.'.$key),
        );
    }

    /**
     * @return list<MediaUsageDTO>
     */
    private function usagesIn(mixed $text, string $recordId, string $title): array
    {
        $usages = [];

        foreach ($this->urls->fileIdsIn($text) as $url) {
            $usages[] = new MediaUsageDTO(
                storedPath: $url,
                recordId: $recordId,
                role: __('filament.media_library.roles.in_text'),
                title: $title,
            );
        }

        return $usages;
    }
}
