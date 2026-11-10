<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Preferences;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Whether a person sees a category in their profile (and may change it). It never decides
 * whether a mail or a site notice goes out.
 */
final class MailCategoryAudience
{
    public static function includes(MailCategoryDTO $category, UserModel $user): bool
    {
        if (! $category->audience instanceof Closure) {
            return true;
        }

        try {
            return ($category->audience)($user) === true;
        } catch (Throwable $exception) {
            // Hidden from the list only: whether the mail goes out never depends on it
            Log::warning('The audience check of a mail category failed; it is not shown.', [
                'category' => $category->key,
                'user_id' => $user->id,
                'exception' => $exception,
            ]);

            return false;
        }
    }
}
