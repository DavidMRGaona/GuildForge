<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Channels;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailFooterDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailUnsubscribeLinksInterface;
use App\Infrastructure\Mail\Footer\CurrentMailFooter;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Mail\Markdown;
use Illuminate\Mail\Message;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Laravel's mail channel, plus what every mail to an account says about the mail the person
 * receives: the footer of the core template and, for optional categories, the RFC 8058
 * List-Unsubscribe headers. Notifications (core and modules) need nothing of their own.
 *
 * Mail to a plain address (Notification::route(), guests) carries neither. Neither does a
 * Mailable returned from toMail(): it may be addressed to anyone.
 */
final class PreferenceAwareMailChannel extends MailChannel
{
    private ?MailFooterDTO $pending = null;

    public function __construct(
        MailFactory $mailer,
        Markdown $markdown,
        private readonly MailCategoryRegistryInterface $categories,
        private readonly MailUnsubscribeLinksInterface $links,
        private readonly CurrentMailFooter $footer,
    ) {
        parent::__construct($mailer, $markdown);
    }

    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification)
    {
        $previous = $this->footer->current();
        $this->pending = $this->footerFor($notifiable, $notification);

        try {
            return parent::send($notifiable, $notification);
        } finally {
            $this->pending = null;
            $this->footer->set($previous);
        }
    }

    /**
     * Only reached for a MailMessage, so a Mailable never gets the footer of the account.
     *
     * @param  MailMessage  $message
     * @return string|array<string, mixed>
     */
    protected function buildView($message)
    {
        $this->footer->set($this->pending);

        return parent::buildView($message);
    }

    /**
     * @param  Message  $mailMessage
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @param  MailMessage  $message
     */
    protected function buildMessage($mailMessage, $notifiable, $notification, $message)
    {
        parent::buildMessage($mailMessage, $notifiable, $notification, $message);

        $footer = $this->footer->current();

        if ($footer?->oneClickUrl === null) {
            return;
        }

        $headers = $mailMessage->getSymfonyMessage()->getHeaders();
        $headers->addTextHeader('List-Unsubscribe', '<'.$footer->oneClickUrl.'>');
        $headers->addTextHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
    }

    private function footerFor(mixed $notifiable, Notification $notification): ?MailFooterDTO
    {
        if (! $notifiable instanceof UserModel || $notifiable->anonymized_at !== null) {
            return null;
        }

        try {
            $preferencesUrl = $this->links->preferencesUrl();
            $category = $this->categories->forNotification($notification);

            if (! $category instanceof MailCategoryDTO) {
                return MailFooterDTO::withoutCategory($preferencesUrl);
            }

            if ($category->isMandatory()) {
                return MailFooterDTO::mandatory($preferencesUrl);
            }

            $label = __($category->label);

            return MailFooterDTO::optional(
                categoryLabel: is_string($label) ? $label : $category->label,
                unsubscribeUrl: $this->links->unsubscribeUrl($notifiable->id, $category->key),
                oneClickUrl: $this->links->oneClickUrl($notifiable->id, $category->key),
                preferencesUrl: $preferencesUrl,
            );
        } catch (Throwable $exception) {
            // The footer never keeps a mail from going out
            Log::warning('Could not build the mail preferences footer; the mail is sent without it.', [
                'notification' => $notification::class,
                'user_id' => $notifiable->id,
                'exception' => $exception,
            ]);

            return null;
        }
    }
}
