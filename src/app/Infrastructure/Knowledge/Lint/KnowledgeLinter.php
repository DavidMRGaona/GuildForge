<?php

declare(strict_types=1);

namespace App\Infrastructure\Knowledge\Lint;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Knowledge\DTOs\KnowledgeLintIssueDTO;
use App\Application\Knowledge\DTOs\ParsedGuideDTO;
use App\Application\Knowledge\Services\KnowledgeCapabilityRegistryInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Knowledge\Enums\GuideAudience;
use App\Domain\Knowledge\Exceptions\InvalidGuideDocumentException;
use App\Domain\Modules\Entities\Module;
use App\Infrastructure\Knowledge\Catalog\CoreKnowledgeCategories;
use App\Infrastructure\Knowledge\Parsing\GuideDocumentParser;
use App\Infrastructure\Knowledge\Rendering\ActionCondition;
use App\Infrastructure\Knowledge\Rendering\ActionLinkResolver;
use App\Infrastructure\Knowledge\Rendering\Extensions\ActionButtonParser;
use App\Infrastructure\Knowledge\Rendering\KnowledgePlaceholders;
use Illuminate\Support\Facades\Route;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Checks the guides of the core, and of one module when given, the way CI needs them to be.
 */
final readonly class KnowledgeLinter
{
    private const string LOCALE_PATTERN = '/^[a-z]{2}(_[A-Z]{2})?$/';

    public function __construct(
        private string $corePath,
        private ModuleManagerServiceInterface $modules,
        private GuideDocumentParser $parser,
        private PermissionRegistryInterface $permissions,
        private KnowledgeCapabilityRegistryInterface $capabilities,
    ) {}

    /**
     * @return list<KnowledgeLintIssueDTO>
     */
    public function lint(?string $module = null): array
    {
        $issues = [];
        $roots = [['path' => $this->corePath, 'module' => null, 'owner' => 'core']];

        if ($module !== null) {
            $found = $this->modules->findByName($module);
            if (! $found instanceof Module) {
                return [new KnowledgeLintIssueDTO($module, sprintf("module '%s' was not found", $module))];
            }
            $roots[] = ['path' => $found->path().'/resources/knowledge', 'module' => $module, 'owner' => $module];
        }

        foreach ($roots as $root) {
            array_push($issues, ...$this->lintRoot($root['path'], $root['module'], $root['owner']));
        }

        return $issues;
    }

    /**
     * @return list<KnowledgeLintIssueDTO>
     */
    private function lintRoot(string $root, ?string $module, string $owner): array
    {
        if (! is_dir($root)) {
            return [];
        }

        $issues = [];
        $entries = $this->parseAll($root, $issues);
        $categories = $this->categories($root, $module, $issues);

        $ids = [];
        foreach ($entries as $entry) {
            $ids[$entry['parsed']->id->value()] = true;
        }
        $coreIds = $module === null ? [] : $this->coreGuideIds();

        $seen = [];
        $claimed = [];
        foreach ($entries as $entry) {
            $parsed = $entry['parsed'];
            $file = $entry['file'];
            $key = $parsed->id->value().'|'.$entry['locale'];

            if (isset($seen[$key])) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf('guide id %s (%s) is already defined in %s', $parsed->id->value(), $entry['locale'], $seen[$key]));
            }
            $seen[$key] ??= $file;

            if ($parsed->id->prefix() !== $owner) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf("guide id %s has prefix '%s', expected '%s'", $parsed->id->value(), $parsed->id->prefix(), $owner));
            }

            array_push(
                $issues,
                ...$this->checkCategory($parsed, $file, $categories),
                ...$this->checkAliases($parsed, $file, $ids, $claimed),
                ...$this->checkRelated($parsed, $file, $ids + $coreIds),
                ...$this->checkConditions($parsed, $file, $module),
                ...$this->checkBody($parsed->body, $file, $module),
            );
        }

        return $issues;
    }

    /**
     * @param  list<KnowledgeLintIssueDTO>  $issues
     * @return list<array{parsed: ParsedGuideDTO, locale: string, file: string}>
     */
    private function parseAll(string $root, array &$issues): array
    {
        $entries = [];
        $names = scandir($root);

        foreach ($names === false ? [] : $names as $name) {
            if ($name[0] === '.' || ! is_dir($root.'/'.$name)) {
                continue;
            }
            if (preg_match(self::LOCALE_PATTERN, $name) !== 1) {
                $issues[] = new KnowledgeLintIssueDTO($root.'/'.$name, sprintf("locale directory '%s' must look like 'es', 'en' or 'pt_BR'", $name));

                continue;
            }

            foreach ($this->markdownFiles($root.'/'.$name) as $file) {
                $contents = file_get_contents($file);
                if ($contents === false) {
                    $issues[] = new KnowledgeLintIssueDTO($file, 'the file could not be read');

                    continue;
                }

                try {
                    $entries[] = ['parsed' => $this->parser->parse($file, $contents), 'locale' => $name, 'file' => $file];
                } catch (InvalidGuideDocumentException $e) {
                    $issues[] = new KnowledgeLintIssueDTO($file, $this->withoutPath($e->getMessage(), $file));
                }
            }
        }

        return $entries;
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

    private function withoutPath(string $message, string $file): string
    {
        return str_starts_with($message, $file.': ') ? substr($message, strlen($file) + 2) : $message;
    }

    /**
     * @return array<string, true>
     */
    private function coreGuideIds(): array
    {
        $ids = [];
        $discard = [];
        foreach ($this->parseAll($this->corePath, $discard) as $entry) {
            $ids[$entry['parsed']->id->value()] = true;
        }

        return $ids;
    }

    /**
     * @param  list<KnowledgeLintIssueDTO>  $issues
     * @return array<string, GuideAudience>
     */
    private function categories(string $root, ?string $module, array &$issues): array
    {
        $categories = [];
        foreach (CoreKnowledgeCategories::all() as $category) {
            $categories[$category->id] = $category->audience;
        }

        $file = $root.'/categories.php';
        if ($module === null || ! is_file($file)) {
            return $categories;
        }

        try {
            $definitions = require $file;
        } catch (Throwable $e) {
            $issues[] = new KnowledgeLintIssueDTO($file, $e->getMessage());

            return $categories;
        }

        if (! is_array($definitions)) {
            $issues[] = new KnowledgeLintIssueDTO($file, 'must return a list of categories');

            return $categories;
        }

        foreach ($definitions as $definition) {
            $id = is_array($definition) ? ($definition['id'] ?? null) : null;
            $audience = is_array($definition) && is_string($definition['audience'] ?? null) ? GuideAudience::tryFrom($definition['audience']) : null;
            if (! is_string($id) || $id === '' || $audience === null) {
                $issues[] = new KnowledgeLintIssueDTO($file, 'invalid category definition (needs id, audience, order, icon and label_key)');

                continue;
            }
            $categories[$id] = $audience;
        }

        return $categories;
    }

    /**
     * @param  array<string, GuideAudience>  $categories
     * @return list<KnowledgeLintIssueDTO>
     */
    private function checkCategory(ParsedGuideDTO $parsed, string $file, array $categories): array
    {
        if (! isset($categories[$parsed->categoryId])) {
            return [new KnowledgeLintIssueDTO($file, sprintf("category '%s' does not exist", $parsed->categoryId))];
        }
        if ($categories[$parsed->categoryId] !== $parsed->audience) {
            return [new KnowledgeLintIssueDTO($file, sprintf(
                "audience '%s' does not match category '%s' (%s)",
                $parsed->audience->value,
                $parsed->categoryId,
                $categories[$parsed->categoryId]->value,
            ))];
        }

        return [];
    }

    /**
     * @param  array<string, true>  $ids
     * @param  array<string, string>  $claimed  previous id => file that claimed it first
     * @return list<KnowledgeLintIssueDTO>
     */
    private function checkAliases(ParsedGuideDTO $parsed, string $file, array $ids, array &$claimed): array
    {
        $issues = [];
        $inList = [];

        foreach ($parsed->previousIds as $alias) {
            $value = $alias->value();
            if (isset($inList[$value])) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf('duplicate previous_ids entry %s', $value));

                continue;
            }
            $inList[$value] = true;

            if (isset($ids[$value])) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf('previous id %s is the id of an existing guide', $value));
            }

            if (isset($claimed[$value]) && $claimed[$value] !== $this->guideKey($parsed)) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf('previous id %s is already claimed by guide %s', $value, $claimed[$value]));
            }
            $claimed[$value] ??= $this->guideKey($parsed);
        }

        return $issues;
    }

    private function guideKey(ParsedGuideDTO $parsed): string
    {
        return $parsed->id->value();
    }

    /**
     * @param  array<string, true>  $known
     * @return list<KnowledgeLintIssueDTO>
     */
    private function checkRelated(ParsedGuideDTO $parsed, string $file, array $known): array
    {
        $issues = [];
        $seen = [];

        foreach ($parsed->related as $related) {
            $value = $related->value();
            if (isset($seen[$value])) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf('duplicate related entry %s', $value));

                continue;
            }
            $seen[$value] = true;

            if ($related->equals($parsed->id)) {
                $issues[] = new KnowledgeLintIssueDTO($file, 'a guide cannot be related to itself');
            } elseif (! isset($known[$value])) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf('related guide %s does not exist in the core or in this module', $value));
            }
        }

        return $issues;
    }

    /**
     * @return list<KnowledgeLintIssueDTO>
     */
    private function checkConditions(ParsedGuideDTO $parsed, string $file, ?string $module): array
    {
        $issues = [];
        $conditions = $parsed->conditions;

        foreach ([...$conditions->permissionsAll, ...$conditions->permissionsAny] as $permission) {
            if (! $this->permissions->has($permission)) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf("permission '%s' is not registered", $permission));
            }
        }

        $registered = $this->capabilities->keys();
        foreach ($conditions->capabilityKeys() as $capability) {
            if (! in_array($capability, $registered, true)) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf("capability '%s' is not registered", $capability));
            }
            array_push($issues, ...$this->checkCapabilityOwner($capability, $module, $file));
        }

        // Modules do not depend on each other: a module guide may only name its own module
        if ($module !== null) {
            foreach ($conditions->modules as $required) {
                if ($required !== $module) {
                    $issues[] = new KnowledgeLintIssueDTO($file, sprintf("module '%s' is another module: module guides cannot depend on it", $required));
                }
            }
        }

        return $issues;
    }

    /**
     * In a module guide, only core capabilities and the module's own are allowed.
     *
     * @return list<KnowledgeLintIssueDTO>
     */
    private function checkCapabilityOwner(string $capability, ?string $module, string $file): array
    {
        if ($module === null) {
            return [];
        }

        $prefix = explode('.', $capability, 2)[0];
        if ($prefix === 'core' || $prefix === $module) {
            return [];
        }

        return [new KnowledgeLintIssueDTO($file, sprintf("capability '%s' belongs to another module: module guides may use core capabilities and their own", $capability))];
    }

    /**
     * @return list<KnowledgeLintIssueDTO>
     */
    private function checkBody(string $body, string $file, ?string $module): array
    {
        $text = $this->withoutCode($body);
        $issues = [];

        // CommonMark autolinks (<https://…>, <user@host>) are not raw HTML.
        $html = preg_replace(['/<[A-Za-z][A-Za-z0-9+.\-]{1,31}:[^\s<>]*>/', '/<[A-Za-z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[A-Za-z0-9.-]+>/'], '', $text) ?? $text;
        if (preg_match('/<[A-Za-z\/]/', $html) === 1) {
            $issues[] = new KnowledgeLintIssueDTO($file, 'the body contains raw HTML');
        }

        preg_match_all('/\{\{([^{}]*)\}\}/', $text, $markers);
        foreach (array_unique($markers[1]) as $marker) {
            if (! in_array($marker, KnowledgePlaceholders::NAMES, true)) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf("unknown marker '{{%s}}'", $marker));
            }
        }

        preg_match_all('/\[\[action:[^\]]*\]\]/', $text, $buttons);
        foreach ($buttons[0] as $button) {
            if (preg_match('/'.ActionButtonParser::PATTERN.'/', $button, $parts) !== 1 || $parts[0] !== $button) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf("malformed action button '%s'", $button));

                continue;
            }
            array_push($issues, ...$this->checkButton(trim($parts[1]), isset($parts[3]) ? trim($parts[3]) : null, $file, $module));
        }

        return $issues;
    }

    /**
     * @return list<KnowledgeLintIssueDTO>
     */
    private function checkButton(string $routeName, ?string $condition, string $file, ?string $module): array
    {
        $issues = [];
        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            $issues[] = new KnowledgeLintIssueDTO($file, sprintf("route '%s' of an action button does not exist", $routeName));
        } elseif (ActionLinkResolver::hasRequiredParameters($route)) {
            $issues[] = new KnowledgeLintIssueDTO($file, sprintf("route '%s' of an action button has required parameters", $routeName));
        }

        if ($condition !== null) {
            $key = ActionCondition::parse($condition)['key'];
            $isPermission = ActionCondition::kind($key, $this->permissions) === 'permission';
            $known = $isPermission || in_array($key, $this->capabilities->keys(), true);
            if ($key === '' || ! $known) {
                $issues[] = new KnowledgeLintIssueDTO($file, sprintf("condition '%s' of an action button is neither a permission nor a capability", $key));
            } elseif (! $isPermission) {
                array_push($issues, ...$this->checkCapabilityOwner($key, $module, $file));
            }
        }

        return $issues;
    }

    /**
     * Removes fenced code blocks and inline code, where markers and angle brackets are literal text.
     */
    private function withoutCode(string $body): string
    {
        $kept = [];
        $fence = null;

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $match) === 1) {
                if ($fence === null) {
                    $fence = $match[1][0];
                } elseif ($match[1][0] === $fence) {
                    $fence = null;
                }

                continue;
            }
            if ($fence === null) {
                $kept[] = $line;
            }
        }

        return preg_replace('/(`+).+?\1/s', '', implode("\n", $kept)) ?? '';
    }
}
