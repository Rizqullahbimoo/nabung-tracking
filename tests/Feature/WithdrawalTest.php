<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Goal;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class WithdrawalTest extends TestCase
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

    private function deposit(Goal $goal, User $user, int $amount): Contribution
    {
        return $goal->contributions()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'contributed_at' => now()->toDateString(),
        ]);
    }

    public function test_withdrawal_reduces_collected_amount(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 5_000_000]);
        $this->deposit($goal, $a, 1_000_000);

        $this->actingAs($b);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '300000')
            ->set('note', 'DP tiket pesawat')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.show', $goal));

        $withdrawal = Contribution::where('type', 'withdrawal')->first();
        $this->assertNotNull($withdrawal);
        $this->assertSame('300000.00', $withdrawal->amount); // stored positive
        $this->assertSame('withdrawal', $withdrawal->type);
        $this->assertSame('DP tiket pesawat', $withdrawal->note);
        $this->assertSame($b->id, $withdrawal->user_id);

        $this->assertSame(700_000.0, $goal->fresh()->collectedAmount());
    }

    public function test_cannot_withdraw_more_than_the_balance(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 5_000_000]);
        $this->deposit($goal, $a, 500_000);

        $this->actingAs($a);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '500001')
            ->set('note', 'kebanyakan')
            ->call('save')
            ->assertHasErrors('amount');

        $this->assertSame(0, Contribution::where('type', 'withdrawal')->count());
        $this->assertSame(500_000.0, $goal->fresh()->collectedAmount());
    }

    public function test_can_withdraw_exactly_the_balance(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 5_000_000]);
        $this->deposit($goal, $a, 500_000);

        $this->actingAs($a);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '500000')
            ->set('note', 'habis semua')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(0.0, $goal->fresh()->collectedAmount());
    }

    public function test_cannot_withdraw_from_an_empty_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $this->actingAs($a);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '100000')
            ->set('note', 'ambil')
            ->call('save')
            ->assertHasErrors('amount');

        $this->assertSame(0, Contribution::where('type', 'withdrawal')->count());
    }

    public function test_withdrawal_note_is_required(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $this->deposit($goal, $a, 800_000);

        $this->actingAs($a);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '100000')
            ->set('note', '')
            ->call('save')
            ->assertHasErrors(['note' => 'required']);

        $this->assertSame(0, Contribution::where('type', 'withdrawal')->count());
    }

    public function test_achieved_goal_reverts_to_active_when_balance_drops_below_target(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);
        $this->deposit($goal, $a, 1_000_000);
        $goal->syncAchievedStatus();
        $this->assertSame('achieved', $goal->fresh()->status);

        $this->actingAs($a);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '200000')
            ->set('note', 'beli cincin')
            ->call('save')
            ->assertHasNoErrors();

        $goal->refresh();
        $this->assertSame('active', $goal->status);
        $this->assertSame(800_000.0, $goal->collectedAmount());
    }

    public function test_goal_stays_achieved_when_withdrawal_keeps_balance_at_or_above_target(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);
        $this->deposit($goal, $a, 1_500_000);
        $goal->syncAchievedStatus();
        $this->assertSame('achieved', $goal->fresh()->status);

        $this->actingAs($a);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '400000')
            ->set('note', 'sebagian')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('achieved', $goal->fresh()->status);
        $this->assertSame(1_100_000.0, $goal->fresh()->collectedAmount());
    }

    public function test_withdrawal_is_allowed_on_an_achieved_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000, 'status' => 'achieved']);
        $this->deposit($goal, $a, 1_000_000);

        $this->actingAs($a)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Tarik Dana');

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '100000')
            ->set('note', 'jajan')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(900_000.0, $goal->fresh()->collectedAmount());
    }

    public function test_withdrawal_is_rejected_for_a_pending_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['status' => 'pending']);

        $this->actingAs($b);

        Volt::test('contributions.withdraw', ['goal' => $goal])
            ->set('amount', '10000')
            ->set('note', 'x')
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, Contribution::where('type', 'withdrawal')->count());
    }

    public function test_withdrawal_from_another_pair_is_forbidden(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $outsider = User::factory()->create();

        $this->actingAs($outsider);

        Volt::test('contributions.withdraw', ['goal' => $goal])->assertForbidden();
    }

    public function test_history_shows_withdrawal_in_red_with_minus_and_reason(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 5_000_000]);
        $this->deposit($goal, $a, 1_000_000);
        $goal->contributions()->create([
            'user_id' => $a->id,
            'amount' => 300_000,
            'type' => 'withdrawal',
            'note' => 'DP tiket pesawat',
            'contributed_at' => now()->toDateString(),
        ]);

        $this->actingAs($a)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('- Rp 300.000')
            ->assertSee('text-accent-red', escape: false)
            ->assertSee('DP tiket pesawat')
            ->assertSee('Penarikan')
            ->assertSee('+ Rp 1.000.000');
    }

    public function test_breakdown_is_net_of_each_persons_withdrawals(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 10_000_000]);
        $this->deposit($goal, $a, 1_000_000);
        $this->deposit($goal, $b, 600_000);
        $goal->contributions()->create([
            'user_id' => $a->id,
            'amount' => 400_000,
            'type' => 'withdrawal',
            'note' => 'beli sesuatu',
            'contributed_at' => now()->toDateString(),
        ]);

        $breakdown = $goal->contributionBreakdown()->keyBy('user_id');

        $this->assertSame(600_000.0, $breakdown[$a->id]['total']); // 1.000.000 - 400.000
        $this->assertSame(600_000.0, $breakdown[$b->id]['total']);
        $this->assertSame(1_200_000.0, $goal->collectedAmount()); // reconciles with the sum
    }

    public function test_active_goal_shows_both_deposit_and_withdraw_buttons(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $this->actingAs($a)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('+ Tambah Kontribusi')
            ->assertSee('Tarik Dana');
    }
}
