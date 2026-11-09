<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Digest;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailDigestLineDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPreferenceServiceInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Domain\Mail\Repositories\MailDigestItemRepositoryInterface;
use App\Domain\Mail\ValueObjects\MailDigestItem;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\MailDigestNotification;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Builds and sends one person's digest inside the transaction that holds its notices, so they
 * are deleted only once the mail went out. Each notice is checked again: the person may have
 * turned its category off, paused their mail or muted its subject since it was kept. A notice
 * of a category that is no longer registered (a disabled module) is dropped.
 */
final readonly class MailDigestSender
{
    /** Lines shown in one digest; the oldest beyond it are only counted. */
    public const int MAX_LINES = 100;

    public function __construct(
        private MailDigestItemRepositoryInterface $items,
        private MailCategoryRegistryInterface $categories,
        private MailPreferenceServiceInterface $preferences,
        private TimezoneServiceInterface $timezone,
    ) {}

    public function send(string $userId, DateTimeInterface $cutoff): void
    {
        // A deactivated account is left out like an anonymized one: its notices go with the run
        $user = UserModel::query()->find($userId);

        $this->items->takeForUser(UserId::fromString($userId), $cutoff, function (array $items) use ($user): void {
            if (! $user instanceof UserModel || $user->anonymized_at !== null) {
                return;
            }

            $wanted = [];
            foreach ($items as $item) {
                $category = $this->categories->find($item->category);

                if ($category instanceof MailCategoryDTO && $this->preferences->receives($user, $category, $this->subjectOf($item))) {
                    $wanted[] = [$item, $category];
                }
            }

            if ($wanted !== []) {
                $user->notifyNow(new MailDigestNotification(...$this->contentOf($wanted)));
            }
        });
    }

    /**
     * @param  list<array{MailDigestItem, MailCategoryDTO}>  $wanted  Oldest first.
     * @return array{sections: list<array{key: string, label: string, lines: list<array{time: string, text: string, url: string|null}>}>, total: int}
     */
    private function contentOf(array $wanted): array
    {
        $total = count($wanted);
        // The newest ones, when there are too many
        $shown = array_slice($wanted, max(0, $total - self::MAX_LINES));

        $order = array_flip(array_map(static fn (MailCategoryDTO $category): string => $category->key, $this->categories->all()));
        $sections = [];
        foreach ($shown as [$item, $category]) {
            $sections[$category->key] ??= ['key' => $category->key, 'label' => $this->translate($category->label), 'lines' => []];
            $sections[$category->key]['lines'][] = [
                'time' => $this->timezone->format($item->createdAt, 'd/m H:i'),
                'text' => $item->line,
                'url' => $this->linkOf($item),
            ];
        }

        uksort($sections, static fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));

        return ['sections' => array_values($sections), 'total' => $total];
    }

    private function subjectOf(MailDigestItem $item): ?MailSubjectDTO
    {
        if ($item->subjectType === null || $item->subjectId === null) {
            return null;
        }

        try {
            // Only type and id matter to a mute; the line stands in for the name
            return new MailSubjectDTO($item->subjectType, $item->subjectId, $item->line);
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * Checked again: the row is the only thing between a module and a link in the mail.
     */
    private function linkOf(MailDigestItem $item): ?string
    {
        try {
            return (new MailDigestLineDTO($item->line, $item->url))->url;
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function translate(string $key): string
    {
        $text = __($key);

        return is_string($text) ? $text : $key;
    }
}
