<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Goal;
use App\Models\Notification;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WeeklySavingsReminderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Pair}
     */
    private function couple(): array
    {
        $one = User::factory()->create();
        $two = User::factory()->create();

        $pair = Pair::create([
            'user_one_id' => $one->id,
            'user_two_id' => $two->id,
            'status' => 'active',
            'paired_at' => now(),
        ]);

        return [$one, $two, $pair];
    }

    private function goal(Pair $pair, string $status = 'active'): Goal
    {
        return Goal::create([
            'pair_id' => $pair->id,
            'proposed_by' => $pair->user_one_id,
            'name' => 'Goal Uji',
            'target_amount' => 10_000_000,
            'status' => $status,
        ]);
    }

    private function deposit(Goal $goal, int $daysAgo): Contribution
    {
        return $goal->contributions()->create([
            'user_id' => $goal->pair->user_one_id,
            'amount' => 100_000,
            'type' => 'deposit',
            'contributed_at' => now()->subDays($daysAgo)->toDateString(),
        ]);
    }

    private function reminders(User $user): int
    {
        return Notification::where('user_id', $user->id)->where('type', 'savings_reminder')->count();
    }

    public function test_reminds_a_couple_with_an_active_goal_and_no_recent_deposit(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->goal($pair);
        $this->deposit($goal, daysAgo: 10);

        $this->artisan('reminders:weekly-savings')->assertSuccessful();

        $this->assertSame(1, $this->reminders($a));
        $this->assertSame(1, $this->reminders($b));

        $notification = Notification::where('type', 'savings_reminder')->first();
        $this->assertSame($goal->id, $notification->data['goal_id']);
    }

    public function test_reminds_a_solo_pair_too(): void
    {
        $solo = User::factory()->create();
        $goal = $this->goal($solo->activePair());

        $this->artisan('reminders:weekly-savings')->assertSuccessful();

        $this->assertSame(1, $this->reminders($solo));
    }

    public function test_does_not_remind_a_pair_that_deposited_within_the_last_7_days(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->goal($pair);
        $this->deposit($goal, daysAgo: 3);

        $this->artisan('reminders:weekly-savings')->assertSuccessful();

        $this->assertSame(0, $this->reminders($a));
        $this->assertSame(0, $this->reminders($b));
    }

    public function test_does_not_remind_a_pair_without_an_active_goal(): void
    {
        [$a, $b, $pair] = $this->couple();
        // Only non-active goals + no recent deposit.
        $this->goal($pair, status: 'pending');
        $achieved = $this->goal($pair, status: 'achieved');
        $this->deposit($achieved, daysAgo: 30);

        $this->artisan('reminders:weekly-savings')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'savings_reminder')->count());
    }

    public function test_does_not_remind_a_pair_with_no_goals_at_all(): void
    {
        [$a, $b, $pair] = $this->couple();

        $this->artisan('reminders:weekly-savings')->assertSuccessful();

        $this->assertSame(0, Notification::where('type', 'savings_reminder')->count());
    }

    public function test_running_twice_in_a_week_does_not_duplicate_reminders(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->goal($pair);
        $this->deposit($goal, daysAgo: 20);

        $this->artisan('reminders:weekly-savings')->assertSuccessful();
        $this->artisan('reminders:weekly-savings')->assertSuccessful();

        $this->assertSame(1, $this->reminders($a));
        $this->assertSame(1, $this->reminders($b));
    }
}
