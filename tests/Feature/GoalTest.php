<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class GoalTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Pair} proposer, partner, pair
     */
    private function pairedUsers(): array
    {
        $proposer = User::factory()->create();
        $partner = User::factory()->create();

        $pair = Pair::create([
            'user_one_id' => $proposer->id,
            'user_two_id' => $partner->id,
            'status' => 'active',
            'paired_at' => now(),
        ]);

        return [$proposer, $partner, $pair];
    }

    private function makeGoal(int $pairId, int $proposedBy, array $overrides = []): Goal
    {
        return Goal::create(array_merge([
            'pair_id' => $pairId,
            'proposed_by' => $proposedBy,
            'name' => 'Goal Uji',
            'target_amount' => 10_000_000,
            'status' => 'pending',
        ], $overrides));
    }

    public function test_couple_goal_is_proposed_as_pending(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();

        $this->actingAs($proposer);

        Volt::test('goals.create')
            ->set('name', 'Dana Nikah')
            ->set('category', 'Pernikahan')
            ->set('target_amount', '50000000')
            ->set('target_date', now()->addYear()->toDateString())
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.index'));

        $goal = Goal::first();

        $this->assertNotNull($goal);
        $this->assertSame($pair->id, $goal->pair_id);
        $this->assertSame($proposer->id, $goal->proposed_by);
        $this->assertSame('pending', $goal->status);
        $this->assertNull($goal->approved_by);
        $this->assertNull($goal->approved_at);
        $this->assertSame('Pernikahan', $goal->category);
        $this->assertSame('50000000.00', $goal->target_amount);
    }

    public function test_solo_user_goal_is_created_active_and_self_approved(): void
    {
        $solo = User::factory()->create();

        $this->actingAs($solo);

        Volt::test('goals.create')
            ->set('name', 'Nabung Sendiri')
            ->set('target_amount', '5000000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.index'));

        $goal = Goal::first();

        $this->assertNotNull($goal);
        $this->assertSame($solo->activePair()->id, $goal->pair_id);
        $this->assertSame('active', $goal->status);
        $this->assertSame($solo->id, $goal->approved_by);
        $this->assertNotNull($goal->approved_at);
    }

    public function test_goal_proposal_validates_input(): void
    {
        [$proposer] = $this->pairedUsers();

        $this->actingAs($proposer);

        Volt::test('goals.create')
            ->set('name', '')
            ->set('target_amount', '0')
            ->call('save')
            ->assertHasErrors(['name', 'target_amount']);

        $this->assertSame(0, Goal::count());
    }

    public function test_goal_proposal_rejects_empty_nominal(): void
    {
        [$proposer] = $this->pairedUsers();

        $this->actingAs($proposer);

        Volt::test('goals.create')
            ->set('name', 'Hangout')
            ->set('target_amount', '')
            ->call('save')
            ->assertHasErrors(['target_amount' => 'required']);

        $this->assertSame(0, Goal::count());
    }

    public function test_goal_nominal_accepts_formatted_thousands(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();

        $this->actingAs($proposer);

        // The input sends what the user sees; separators must be tolerated.
        Volt::test('goals.create')
            ->set('name', 'Hangout')
            ->set('category', 'Lainnya')
            ->set('target_amount', '1.000.000')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.index'));

        $this->assertSame('1000000.00', Goal::first()->target_amount);
    }

    public function test_partner_can_approve_a_pending_goal(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $this->actingAs($partner);

        Volt::test('goals.show', ['goal' => $goal])
            ->call('approve')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.show', $goal));

        $goal->refresh();
        $this->assertSame('active', $goal->status);
        $this->assertSame($partner->id, $goal->approved_by);
        $this->assertNotNull($goal->approved_at);
    }

    public function test_partner_can_reject_a_pending_goal(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $this->actingAs($partner);

        Volt::test('goals.show', ['goal' => $goal])
            ->call('reject')
            ->assertHasNoErrors()
            ->assertRedirect(route('goals.show', $goal));

        $goal->refresh();
        $this->assertSame('rejected', $goal->status);
        $this->assertNull($goal->approved_by);
    }

    public function test_proposer_cannot_approve_their_own_goal(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $this->actingAs($proposer);

        Volt::test('goals.show', ['goal' => $goal])
            ->call('approve')
            ->assertForbidden();

        $this->assertSame('pending', $goal->refresh()->status);
    }

    public function test_proposer_sees_waiting_message_and_no_decision_buttons(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $this->actingAs($proposer)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Menunggu persetujuan pasangan')
            ->assertDontSee('Setujui');
    }

    public function test_partner_sees_decision_buttons(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $this->actingAs($partner)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Setujui')
            ->assertSee('Tolak');
    }

    public function test_goal_from_another_pair_is_forbidden(): void
    {
        [$proposer, $partner, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $proposer->id);

        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('goals.show', $goal))
            ->assertForbidden();
    }

    public function test_index_shows_only_goals_of_the_users_pair(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $this->makeGoal($pair->id, $a->id, ['name' => 'Goal Kami']);

        [$c, $d, $otherPair] = $this->pairedUsers();
        $this->makeGoal($otherPair->id, $c->id, ['name' => 'Goal Mereka']);

        $this->actingAs($a)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertSee('Goal Kami')
            ->assertDontSee('Goal Mereka');
    }

    public function test_index_shows_empty_state_for_a_solo_user_with_no_goals(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertSee('Belum ada goal')
            ->assertDontSee('Belum terhubung dengan pasangan');
    }
}
