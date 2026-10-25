<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Ses;

use App\Application\Services\TimezoneServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\SesUsageRecordModel;
use Illuminate\Support\Carbon;

final readonly class SesUsageTracker
{
    private const float COST_PER_1000_EMAILS = 0.10;

    public function __construct(private ?TimezoneServiceInterface $timezone = null) {}

    public function incrementEmailsSent(int $count = 1): void
    {
        $record = $this->getOrCreateTodayRecord();
        $record->emails_sent += $count;
        $record->estimated_cost = $this->getEstimatedCost($record->emails_sent);
        $record->save();
    }

    public function incrementBounces(int $count = 1): void
    {
        $record = $this->getOrCreateTodayRecord();
        $record->bounces_count += $count;
        $record->save();
    }

    public function incrementComplaints(int $count = 1): void
    {
        $record = $this->getOrCreateTodayRecord();
        $record->complaints_count += $count;
        $record->save();
    }

    public function getTodayUsage(): SesUsageRecordModel
    {
        return SesUsageRecordModel::query()
            ->whereDate('date', $this->today())
            ->first() ?? $this->makeEmptyRecord();
    }

    /**
     * @return array{emails_sent: int, estimated_cost: float, bounces_count: int, complaints_count: int}
     */
    public function getMonthlyUsage(): array
    {
        $records = SesUsageRecordModel::query()
            ->whereDate('date', '>=', $this->monthStartDay())
            ->whereDate('date', '<=', $this->monthEndDay())
            ->get();

        return [
            'emails_sent' => (int) $records->sum('emails_sent'),
            'estimated_cost' => (float) $records->sum('estimated_cost'),
            'bounces_count' => (int) $records->sum('bounces_count'),
            'complaints_count' => (int) $records->sum('complaints_count'),
        ];
    }

    public function getEstimatedCost(int $emailCount): float
    {
        return round($emailCount / 1000 * self::COST_PER_1000_EMAILS, 4);
    }

    private function getOrCreateTodayRecord(): SesUsageRecordModel
    {
        $existing = SesUsageRecordModel::query()
            ->whereDate('date', $this->today())
            ->first();

        if ($existing instanceof SesUsageRecordModel) {
            return $existing;
        }

        /** @var SesUsageRecordModel */
        return SesUsageRecordModel::query()->create([
            'date' => $this->today(),
            'emails_sent' => 0,
            'estimated_cost' => 0,
            'bounces_count' => 0,
            'complaints_count' => 0,
        ]);
    }

    private function makeEmptyRecord(): SesUsageRecordModel
    {
        $record = new SesUsageRecordModel;
        $record->date = Carbon::parse($this->today());
        $record->emails_sent = 0;
        $record->estimated_cost = 0;
        $record->bounces_count = 0;
        $record->complaints_count = 0;

        return $record;
    }

    /** The `date` column holds the display calendar day, so it is written and compared as a Y-m-d string. */
    private function today(): string
    {
        return $this->timezone()->today()->format('Y-m-d');
    }

    private function monthStartDay(): string
    {
        return $this->timezone()->today()->format('Y-m-01');
    }

    private function monthEndDay(): string
    {
        return $this->timezone()->today()->format('Y-m-t');
    }

    private function timezone(): TimezoneServiceInterface
    {
        return $this->timezone ?? app(TimezoneServiceInterface::class);
    }
}
