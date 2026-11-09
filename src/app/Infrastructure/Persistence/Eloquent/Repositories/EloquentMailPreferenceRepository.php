<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Mail\Enums\MailDelivery;
use App\Domain\Mail\Repositories\MailPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserMailDigestItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserMailPauseModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserMailPreferenceModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserMailSubjectMuteModel;
use Illuminate\Support\Facades\DB;

final class EloquentMailPreferenceRepository implements MailPreferenceRepositoryInterface
{
    public function disabledFor(UserId $userId): array
    {
        /** @var list<string> */
        return UserMailPreferenceModel::query()
            ->where('user_id', $userId->value())
            ->where('enabled', false)
            ->pluck('category')
            ->all();
    }

    public function digestFor(UserId $userId): array
    {
        /** @var list<string> */
        return UserMailPreferenceModel::query()
            ->where('user_id', $userId->value())
            ->where('enabled', true)
            ->where('digest', true)
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function save(UserId $userId, array $preferences): void
    {
        DB::transaction(function () use ($userId, $preferences): void {
            foreach ($preferences as $category => $choice) {
                $delivery = is_bool($choice) ? MailDelivery::fromSwitch($choice) : $choice;

                UserMailPreferenceModel::query()->updateOrCreate(
                    ['user_id' => $userId->value(), 'category' => $category],
                    ['enabled' => $delivery !== MailDelivery::None, 'digest' => $delivery === MailDelivery::Digest],
                );
            }
        });
    }

    public function deleteForUser(UserId $userId): void
    {
        UserMailPreferenceModel::query()->where('user_id', $userId->value())->delete();
        // The pause is one more of the person's mail choices
        UserMailPauseModel::query()->where('user_id', $userId->value())->delete();
        // And so are the things they muted (core#334)
        UserMailSubjectMuteModel::query()->where('user_id', $userId->value())->delete();
        // And the notices kept for their daily digest (core#333)
        UserMailDigestItemModel::query()->where('user_id', $userId->value())->delete();
    }
}
