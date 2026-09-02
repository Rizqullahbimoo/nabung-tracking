<?php

namespace App\Console\Commands;

use App\Models\Contribution;
use App\Models\Goal;
use App\Models\Notification;
use App\Models\Pair;
use Illuminate\Console\Command;

class WeeklySavingsReminder extends Command
{
    protected $signature = 'reminders:weekly-savings';

    protected $description = 'Nudge pairs that have an active goal but recorded no deposit in the last 7 days (PRD F-08).';

    public function handle(): int
    {
        $since = now()->subDays(7)->startOfDay();
        $reminders = 0;

        Pair::query()
            ->where('status', 'active')
            ->chunkById(100, function ($pairs) use ($since, &$reminders) {
                foreach ($pairs as $pair) {
                    $reminders += $this->remindIfIdle($pair, $since);
                }
            });

        $this->info("Weekly savings reminders sent: {$reminders}");

        return self::SUCCESS;
    }

    /**
     * @return int number of notifications created for this pair
     */
    private function remindIfIdle(Pair $pair, \Illuminate\Support\Carbon $since): int
    {
        $activeGoal = Goal::query()
            ->where('pair_id', $pair->id)
            ->where('status', 'active')
            ->orderBy('id')
            ->first();

        // No active goal -> nothing to save toward, so no reminder.
        if (! $activeGoal) {
            return 0;
        }

        $depositedRecently = Contribution::query()
            ->where('type', 'deposit')
            ->whereDate('contributed_at', '>=', $since)
            ->whereHas('goal', fn ($query) => $query->where('pair_id', $pair->id))
            ->exists();

        if ($depositedRecently) {
            return 0;
        }

        $created = 0;

        foreach (array_filter([$pair->user_one_id, $pair->user_two_id]) as $userId) {
            // Don't stack reminders if the command runs more than once this week.
            $alreadyReminded = Notification::query()
                ->where('user_id', $userId)
                ->where('type', 'savings_reminder')
                ->where('created_at', '>=', now()->subDays(6))
                ->exists();

            if ($alreadyReminded) {
                continue;
            }

            Notification::create([
                'user_id' => $userId,
                'type' => 'savings_reminder',
                'title' => 'Belum nabung minggu ini?',
                'message' => 'Yuk lanjutkan progres goal kalian. Sedikit demi sedikit tetap maju!',
                'data' => ['goal_id' => $activeGoal->id],
            ]);

            $created++;
        }

        return $created;
    }
}
