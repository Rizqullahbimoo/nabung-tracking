<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Goal;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ContributionEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Pair}
     */
    private function pairedUsers(): array
    {
        $one = User::factory()->create(['name' => 'Aku']);
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
            'name' => 'Goal Uji',
            'target_amount' => 10_000_000,
            'status' => 'active',
        ], $overrides));
    }

    private function deposit(Goal $goal, User $user, int $amount, array $overrides = []): Contribution
    {
        return $goal->contributions()->create(array_merge([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'contributed_at' => now()->toDateString(),
        ], $overrides));
    }

    // ---- Edit ------------------------------------------------------------

    public function test_creator_can_edit_their_own_deposit(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $c = $this->deposit($goal, $a, 300_000, ['note' => 'awal']);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])
            ->call('startEdit', $c->id)
            ->set('editAmount', '750000')
            ->set('editNote', 'revisi')
            ->set('editDate', '2026-08-30')
            ->call('saveEdit')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.show', $goal));

        $c->refresh();
        $this->assertSame('750000.00', $c->amount);
        $this->assertSame('revisi', $c->note);
        $this->assertSame('2026-08-30', $c->contributed_at->toDateString());
        $this->assertSame('deposit', $c->type);
    }

    public function test_partner_cannot_edit_someone_elses_deposit(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $c = $this->deposit($goal, $a, 300_000);

        $this->actingAs($b);
        Volt::test('goals.show', ['goal' => $goal])
            ->call('startEdit', $c->id)
            ->assertForbidden();

        $this->assertSame('300000.00', $c->fresh()->amount);
    }

    public function test_a_withdrawal_cannot_be_edited(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $this->deposit($goal, $a, 1_000_000);
        $withdrawal = $goal->contributions()->create([
            'user_id' => $a->id, 'amount' => 200_000, 'type' => 'withdrawal',
            'note' => 'x', 'contributed_at' => now()->toDateString(),
        ]);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])
            ->call('startEdit', $withdrawal->id)
            ->assertForbidden();
    }

    public function test_edit_lowering_amount_reverts_achieved_to_active(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);
        $c = $this->deposit($goal, $a, 1_000_000);
        $goal->syncAchievedStatus();
        $this->assertSame('achieved', $goal->fresh()->status);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])
            ->call('startEdit', $c->id)
            ->set('editAmount', '500000')
            ->set('editDate', now()->toDateString())
            ->call('saveEdit');

        $this->assertSame('active', $goal->fresh()->status);
        $this->assertSame(500_000.0, $goal->fresh()->collectedAmount());
    }

    public function test_edit_raising_amount_can_achieve_the_goal(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);
        $c = $this->deposit($goal, $a, 600_000);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])
            ->call('startEdit', $c->id)
            ->set('editAmount', '1000000')
            ->set('editDate', now()->toDateString())
            ->call('saveEdit');

        $this->assertSame('achieved', $goal->fresh()->status);
    }

    // ---- Delete (soft, via correction) --------------------------------

    public function test_delete_creates_a_negative_correction_and_keeps_the_original(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $c = $this->deposit($goal, $a, 500_000);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])
            ->call('deleteContribution', $c->id)
            ->assertRedirect(route('goals.show', $goal));

        $this->assertNotNull($c->fresh(), 'original row must still exist (no hard delete)');
        $this->assertSame(2, Contribution::count());

        $correction = Contribution::where('type', 'correction')->first();
        $this->assertNotNull($correction);
        $this->assertSame('-500000.00', $correction->amount);
        $this->assertSame($c->id, $correction->corrects_contribution_id);
        $this->assertSame($a->id, $correction->user_id);
    }

    public function test_partner_cannot_delete_someone_elses_deposit(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $c = $this->deposit($goal, $a, 500_000);

        $this->actingAs($b);
        Volt::test('goals.show', ['goal' => $goal])
            ->call('deleteContribution', $c->id)
            ->assertForbidden();

        $this->assertSame(1, Contribution::count());
    }

    public function test_an_already_deleted_deposit_cannot_be_deleted_again(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $c = $this->deposit($goal, $a, 500_000);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])->call('deleteContribution', $c->id);
        $this->assertSame(2, Contribution::count());

        Volt::test('goals.show', ['goal' => $goal])
            ->call('deleteContribution', $c->id)
            ->assertForbidden();
        Volt::test('goals.show', ['goal' => $goal])
            ->call('startEdit', $c->id)
            ->assertForbidden();

        $this->assertSame(2, Contribution::count());
    }

    public function test_collected_amount_and_breakdown_reflect_the_correction(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 100_000_000]);
        $aDeposit = $this->deposit($goal, $a, 600_000);
        $this->deposit($goal, $b, 400_000);

        $this->assertSame(1_000_000.0, $goal->collectedAmount());

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])->call('deleteContribution', $aDeposit->id);

        $goal->refresh();
        $this->assertSame(400_000.0, $goal->collectedAmount());

        $breakdown = $goal->contributionBreakdown()->keyBy('user_id');
        $this->assertSame(0.0, $breakdown[$a->id]['total']);   // 600.000 - 600.000
        $this->assertSame(400_000.0, $breakdown[$b->id]['total']);
    }

    public function test_deleting_a_deposit_reverts_achieved_goal_to_active(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id, ['target_amount' => 1_000_000]);
        $c = $this->deposit($goal, $a, 1_000_000);
        $goal->syncAchievedStatus();
        $this->assertSame('achieved', $goal->fresh()->status);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])->call('deleteContribution', $c->id);

        $goal->refresh();
        $this->assertSame('active', $goal->status);
        $this->assertSame(0.0, $goal->collectedAmount());
    }

    // ---- Rendering -----------------------------------------------------

    public function test_edit_and_delete_controls_appear_only_for_own_deposits(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $mine = $this->deposit($goal, $a, 100_000);
        $theirs = $this->deposit($goal, $b, 100_000);

        $this->actingAs($a)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSeeHtml('wire:click="startEdit('.$mine->id.')"')
            ->assertDontSeeHtml('wire:click="startEdit('.$theirs->id.')"')
            ->assertSeeHtml('wire:click="deleteContribution('.$mine->id.')"');
    }

    public function test_deleted_deposit_is_marked_and_a_correction_row_is_shown(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $c = $this->deposit($goal, $a, 500_000);

        $this->actingAs($a);
        Volt::test('goals.show', ['goal' => $goal])->call('deleteContribution', $c->id);

        $this->actingAs($a)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Dihapus')
            ->assertSee('Koreksi')
            ->assertSee('menghapus kontribusi Rp 500.000')
            ->assertDontSeeHtml('wire:click="startEdit('.$c->id.')"'); // controls gone once voided
    }
}
