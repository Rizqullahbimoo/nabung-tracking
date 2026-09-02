<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Notification;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Pair}
     */
    private function couple(): array
    {
        $one = User::factory()->create(['name' => 'Rizqullah']);
        $two = User::factory()->create(['name' => 'Pasangan']);

        $pair = Pair::create([
            'user_one_id' => $one->id,
            'user_two_id' => $two->id,
            'status' => 'active',
            'paired_at' => now(),
        ]);

        return [$one, $two, $pair];
    }

    private function makeGoal(int $pairId, int $proposedBy, array $overrides = []): Goal
    {
        return Goal::create(array_merge([
            'pair_id' => $pairId,
            'proposed_by' => $proposedBy,
            'name' => 'Dana Nikah',
            'target_amount' => 1_000_000,
            'status' => 'pending',
        ], $overrides));
    }

    private function notifs(User $user, string $type): int
    {
        return Notification::where('user_id', $user->id)->where('type', $type)->count();
    }

    // ---- Event 1: goal proposed ---------------------------------------------

    public function test_proposing_a_goal_notifies_the_partner_only(): void
    {
        [$proposer, $partner, $pair] = $this->couple();

        $this->actingAs($proposer);
        Volt::test('goals.create')
            ->set('name', 'Liburan')
            ->set('target_amount', '5000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->notifs($partner, 'goal_proposed'));
        $this->assertSame(0, $this->notifs($proposer, 'goal_proposed'));

        $notification = Notification::where('type', 'goal_proposed')->first();
        $this->assertSame($partner->id, $notification->user_id);
        $this->assertSame(Goal::first()->id, $notification->data['goal_id']);
    }

    public function test_proposing_a_goal_in_solo_mode_creates_no_notification(): void
    {
        $solo = User::factory()->create();

        $this->actingAs($solo);
        Volt::test('goals.create')
            ->set('name', 'Nabung Sendiri')
            ->set('target_amount', '3000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, Notification::count());
    }

    // ---- Event 2: goal approved / rejected ---------------------------------

    public function test_approving_a_goal_notifies_the_proposer_only(): void
    {
        [$proposer, $partner, $pair] = $this->couple();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $this->actingAs($partner);
        Volt::test('goals.show', ['goal' => $goal])->call('approve')->assertHasNoErrors();

        $this->assertSame(1, $this->notifs($proposer, 'goal_approved'));
        $this->assertSame(0, $this->notifs($partner, 'goal_approved'));
    }

    public function test_rejecting_a_goal_notifies_the_proposer_only(): void
    {
        [$proposer, $partner, $pair] = $this->couple();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $this->actingAs($partner);
        Volt::test('goals.show', ['goal' => $goal])->call('reject')->assertHasNoErrors();

        $this->assertSame(1, $this->notifs($proposer, 'goal_rejected'));
        $this->assertSame(0, $this->notifs($partner, 'goal_rejected'));
    }

    // ---- Event 3: contribution added -------------------------------------

    public function test_adding_a_contribution_notifies_the_partner_only(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->makeGoal($pair->id, $a->id, ['status' => 'active', 'target_amount' => 10_000_000]);

        $this->actingAs($a);
        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '250000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->notifs($b, 'contribution_added'));
        $this->assertSame(0, $this->notifs($a, 'contribution_added'));
    }

    public function test_adding_a_contribution_in_solo_mode_creates_no_notification(): void
    {
        $solo = User::factory()->create();
        $goal = $this->makeGoal($solo->activePair()->id, $solo->id, ['status' => 'active', 'target_amount' => 10_000_000]);

        $this->actingAs($solo);
        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '250000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, Notification::count());
    }

    // ---- Event 4: withdrawal recorded ---------------------------------------

    public function test_recording_a_withdrawal_notifies_the_partner_only(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->makeGoal($pair->id, $a->id, ['status' => 'active', 'target_amount' => 10_000_000]);
        $goal->contributions()->create([
            'user_id' => $b->id, 'amount' => 1_000_000, 'type' => 'deposit', 'contributed_at' => now()->toDateString(),
        ]);

        $this->actingAs($a);
        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '300000')
            ->set('note', 'DP tiket')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, $this->notifs($b, 'withdrawal_added'));
        $this->assertSame(0, $this->notifs($a, 'withdrawal_added'));
    }

    public function test_withdrawal_in_solo_mode_creates_no_notification(): void
    {
        $solo = User::factory()->create();
        $goal = $this->makeGoal($solo->activePair()->id, $solo->id, ['status' => 'active', 'target_amount' => 10_000_000]);
        $goal->contributions()->create([
            'user_id' => $solo->id, 'amount' => 1_000_000, 'type' => 'deposit', 'contributed_at' => now()->toDateString(),
        ]);

        $this->actingAs($solo);
        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '300000')
            ->set('note', 'DP tiket')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0, Notification::where('type', 'withdrawal_added')->count());
    }

    // ---- Event 5: goal achieved -----------------------------------------

    public function test_goal_achieved_notifies_both_members_of_a_couple(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->makeGoal($pair->id, $a->id, ['status' => 'active', 'target_amount' => 1_000_000]);

        $this->actingAs($b);
        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '1000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('achieved', $goal->fresh()->status);
        $this->assertSame(1, $this->notifs($a, 'goal_achieved'));
        $this->assertSame(1, $this->notifs($b, 'goal_achieved'));
    }

    public function test_goal_achieved_in_solo_mode_notifies_only_the_user(): void
    {
        $solo = User::factory()->create();
        $goal = $this->makeGoal($solo->activePair()->id, $solo->id, ['status' => 'active', 'target_amount' => 1_000_000]);

        $this->actingAs($solo);
        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '1000000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('achieved', $goal->fresh()->status);
        $this->assertSame(1, Notification::where('type', 'goal_achieved')->count());
        $this->assertSame(1, $this->notifs($solo, 'goal_achieved'));
    }

    // ---- Navbar badge + dropdown -----------------------------------------

    public function test_navbar_shows_unread_count(): void
    {
        [$a, $b, $pair] = $this->couple();
        Notification::create([
            'user_id' => $a->id, 'type' => 'goal_proposed', 'title' => 'x', 'message' => 'y', 'data' => ['goal_id' => 1],
        ]);

        $this->actingAs($a);
        $this->assertSame(1, Volt::test('layout.navigation')->get('unreadCount'));

        // Recipient B has none.
        $this->actingAs($b);
        $this->assertSame(0, Volt::test('layout.navigation')->get('unreadCount'));
    }

    public function test_opening_a_notification_marks_it_read_and_redirects_to_the_goal(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->makeGoal($pair->id, $a->id, ['status' => 'active']);
        $notification = Notification::create([
            'user_id' => $b->id, 'type' => 'contribution_added', 'title' => 'x', 'message' => 'y',
            'data' => ['goal_id' => $goal->id],
        ]);

        $this->actingAs($b);
        Volt::test('layout.navigation')
            ->call('openNotification', $notification->id)
            ->assertRedirect(route('goals.show', $goal));

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_all_read_clears_the_unread_count(): void
    {
        [$a, $b, $pair] = $this->couple();
        foreach (range(1, 3) as $i) {
            Notification::create([
                'user_id' => $a->id, 'type' => 'goal_proposed', 'title' => 'x', 'message' => 'y', 'data' => [],
            ]);
        }

        $this->actingAs($a);
        $component = Volt::test('layout.navigation');
        $this->assertSame(3, $component->get('unreadCount'));

        $component->call('markAllRead');

        $this->assertSame(0, Notification::where('user_id', $a->id)->whereNull('read_at')->count());
        $this->assertSame(0, Volt::test('layout.navigation')->get('unreadCount'));
    }
}
