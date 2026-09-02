<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Goal;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ContributionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Pair}
     */
    private function pairedUsers(): array
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

    private function makeGoal(int $pairId, int $proposedBy, array $overrides = []): Goal
    {
        return Goal::create(array_merge([
            'pair_id' => $pairId,
            'proposed_by' => $proposedBy,
            'name' => 'Goal Uji',
            'target_amount' => 1_000_000,
            'status' => 'active',
        ], $overrides));
    }

    private function contribute(Goal $goal, User $user, int $amount, array $overrides = []): Contribution
    {
        return $goal->contributions()->create(array_merge([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'contributed_at' => now()->toDateString(),
        ], $overrides));
    }

    public function test_contribution_is_stored_for_an_active_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $this->actingAs($a);

        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '500000')
            ->set('note', 'Gajian bulan ini')
            ->set('contributed_at', '2026-08-30')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.show', $goal));

        $contribution = Contribution::first();

        $this->assertNotNull($contribution);
        $this->assertSame($goal->id, $contribution->goal_id);
        $this->assertSame($a->id, $contribution->user_id);
        $this->assertSame('500000.00', $contribution->amount);
        $this->assertSame('deposit', $contribution->type);
        $this->assertSame('Gajian bulan ini', $contribution->note);
        $this->assertSame('2026-08-30', $contribution->contributed_at->toDateString());
    }

    public function test_solo_user_can_contribute_to_their_own_goal(): void
    {
        $solo = User::factory()->create();
        $pair = $solo->activePair();
        $goal = $this->makeGoal($pair->id, $solo->id);

        $this->actingAs($solo);

        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '750000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.show', $goal));

        $this->assertSame('750000.00', Contribution::first()->amount);
        $this->assertSame($solo->id, Contribution::first()->user_id);
    }

    public function test_contribution_rejected_for_a_non_active_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['status' => 'pending']);

        $this->actingAs($b);

        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '500000')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, Contribution::count());
    }

    public function test_contribution_from_another_pair_is_forbidden(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $outsider = User::factory()->create();
        $this->actingAs($outsider);

        Volt::test('contributions.create', ['goal' => $goal])
            ->assertForbidden();
    }

    public function test_contribution_validates_amount(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $this->actingAs($a);

        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '')
            ->call('save')
            ->assertHasErrors(['amount' => 'required']);

        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '0')
            ->call('save')
            ->assertHasErrors('amount');

        $this->assertSame(0, Contribution::count());
    }

    public function test_contribution_amount_accepts_formatted_thousands(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 10_000_000]);

        $this->actingAs($a);

        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '1.500.000')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('1500000.00', Contribution::first()->amount);
    }

    public function test_goal_total_collected_is_the_sum_of_contributions(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 100_000_000]);

        $this->contribute($goal, $a, 3_000_000);
        $this->contribute($goal, $b, 1_500_000);
        $this->contribute($goal, $a, 500_000);

        $this->assertSame(5_000_000.0, $goal->collectedAmount());
        $this->assertSame(5, $goal->fresh()->progressPercent());
    }

    public function test_goal_becomes_achieved_when_target_is_reached(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);

        $this->actingAs($a);
        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '600000')
            ->call('save');

        $this->assertSame('active', $goal->fresh()->status);

        $this->actingAs($b);
        Volt::test('contributions.create', ['goal' => $goal->fresh()])
            ->set('amount', '400000')
            ->call('save');

        $this->assertSame('achieved', $goal->fresh()->status);
    }

    public function test_single_contribution_over_target_also_achieves_the_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);

        $this->actingAs($a);
        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '1500000')
            ->call('save');

        $this->assertSame('achieved', $goal->fresh()->status);
    }

    public function test_goal_stays_active_when_below_target(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);

        $this->actingAs($a);
        Volt::test('contributions.create', ['goal' => $goal])
            ->set('amount', '999999')
            ->call('save');

        $this->assertSame('active', $goal->fresh()->status);
    }

    public function test_contribution_breakdown_is_accurate_per_user(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 100_000_000]);

        $this->contribute($goal, $a, 5_000_000);
        $this->contribute($goal, $a, 3_000_000);
        $this->contribute($goal, $b, 4_500_000);

        $breakdown = $goal->contributionBreakdown();

        $this->assertCount(2, $breakdown);

        $byUser = $breakdown->keyBy('user_id');
        $this->assertSame(8_000_000.0, $byUser[$a->id]['total']);
        $this->assertSame(4_500_000.0, $byUser[$b->id]['total']);
        $this->assertSame($a->name, $byUser[$a->id]['name']);

        // Sorted by total descending.
        $this->assertSame($a->id, $breakdown->first()['user_id']);
    }

    public function test_show_page_lists_history_and_add_button_for_active_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $this->contribute($goal, $a, 250_000, ['note' => 'Nabung awal']);

        $this->actingAs($b)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Tambah Kontribusi')
            ->assertSee('Kontribusi per orang')
            ->assertSee('Rp 250.000')
            ->assertSee('Nabung awal')
            ->assertSee($a->name);
    }

    public function test_add_button_hidden_for_non_active_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['status' => 'pending']);

        $this->actingAs($a)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertDontSee('Tambah Kontribusi');
    }

    public function test_dashboard_summary_shows_totals_and_recent_contributions(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();

        $g1 = $this->makeGoal($pair->id, $a->id, ['name' => 'Dana Nikah', 'target_amount' => 50_000_000]);
        $this->makeGoal($pair->id, $b->id, ['name' => 'Liburan', 'target_amount' => 10_000_000]);
        $this->makeGoal($pair->id, $a->id, ['name' => 'DP Rumah', 'status' => 'pending']);

        $this->contribute($g1, $a, 700_000, ['note' => 'Gajian']);
        $this->contribute($g1, $b, 300_000);

        $this->actingAs($a)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Total Tabungan')
            ->assertSee('Rp 1.000.000')
            ->assertSeeText('2 goal aktif')
            ->assertSeeText('1 menunggu persetujuan')
            ->assertSee('Dana Nikah')
            ->assertSee('Kontribusi terbaru');
    }
}
