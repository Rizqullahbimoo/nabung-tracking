<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class UnpairTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: User, 2: Pair}
     */
    private function couple(string $nameOne = 'Aku', string $nameTwo = 'Pasangan'): array
    {
        $one = User::factory()->create(['name' => $nameOne]);
        $two = User::factory()->create(['name' => $nameTwo]);

        $pair = Pair::create([
            'user_one_id' => $one->id,
            'user_two_id' => $two->id,
            'status' => 'active',
            'paired_at' => now(),
        ]);

        return [$one, $two, $pair];
    }

    private function goalWithContribution(Pair $pair, User $by, string $name): Goal
    {
        $goal = Goal::create([
            'pair_id' => $pair->id,
            'proposed_by' => $by->id,
            'name' => $name,
            'target_amount' => 10_000_000,
            'status' => 'active',
            'approved_by' => $by->id,
            'approved_at' => now(),
        ]);

        $goal->contributions()->create([
            'user_id' => $by->id,
            'amount' => 500_000,
            'type' => 'deposit',
            'contributed_at' => now()->toDateString(),
        ]);

        return $goal;
    }

    public function test_unpair_retires_the_coupled_pair(): void
    {
        [$a, $b, $pair] = $this->couple();

        $this->actingAs($a);
        Volt::test('pairing.status')
            ->call('unpair')
            ->assertRedirect(route('dashboard'));

        $pair->refresh();
        $this->assertSame('unpaired', $pair->status);
        $this->assertNotNull($pair->unpaired_at);
    }

    public function test_unpair_gives_each_user_a_fresh_solo_pair(): void
    {
        [$a, $b, $pair] = $this->couple();

        $this->actingAs($a);
        Volt::test('pairing.status')->call('unpair');

        $aPair = $a->fresh()->activePair();
        $bPair = $b->fresh()->activePair();

        $this->assertTrue($aPair->isSolo());
        $this->assertTrue($bPair->isSolo());
        $this->assertSame($a->id, $aPair->user_one_id);
        $this->assertSame($b->id, $bPair->user_one_id);
        $this->assertNotSame($aPair->id, $bPair->id);
        $this->assertNotSame($pair->id, $aPair->id);

        $this->assertNull($a->fresh()->partner());
        $this->assertFalse($a->fresh()->isPaired());
        $this->assertFalse($b->fresh()->isPaired());
    }

    public function test_old_goals_appear_in_each_users_archive_and_are_not_moved(): void
    {
        [$a, $b, $pair] = $this->couple();
        $goal = $this->goalWithContribution($pair, $a, 'Trip Jepang 2027');

        $this->actingAs($a);
        Volt::test('pairing.status')->call('unpair');

        // Goal & its contribution stay attached to the old coupled pair.
        $this->assertSame($pair->id, $goal->fresh()->pair_id);
        $this->assertSame(1, $goal->contributions()->count());

        $this->actingAs($a)->get(route('goals.archive'))->assertOk()->assertSee('Trip Jepang 2027');
        $this->actingAs($b)->get(route('goals.archive'))->assertOk()->assertSee('Trip Jepang 2027');
    }

    public function test_goals_index_is_empty_after_unpair(): void
    {
        [$a, $b, $pair] = $this->couple();
        $this->goalWithContribution($pair, $a, 'Trip Jepang 2027');

        $this->actingAs($a);
        Volt::test('pairing.status')->call('unpair');

        $this->actingAs($a)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertSee('Belum ada goal')
            ->assertDontSee('Trip Jepang 2027');
    }

    public function test_dashboard_shows_solo_state_after_unpair(): void
    {
        [$a, $b, $pair] = $this->couple('Aku', 'Mantan');
        $this->goalWithContribution($pair, $a, 'Trip Jepang 2027');

        $this->actingAs($a);
        Volt::test('pairing.status')->call('unpair');

        $this->actingAs($a)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Mode solo')
            ->assertSee('Buat Kode Invite')
            ->assertDontSee('Mantan')
            ->assertDontSee('Trip Jepang 2027');
    }

    public function test_a_solo_user_cannot_unpair(): void
    {
        $solo = User::factory()->create();

        $this->actingAs($solo);
        Volt::test('pairing.status')
            ->call('unpair')
            ->assertForbidden();

        $this->assertSame(0, Pair::whereNotNull('user_two_id')->count());
    }

    public function test_unpair_does_not_affect_another_couple(): void
    {
        [$a, $b, $pairAB] = $this->couple('A', 'B');
        [$c, $d, $pairCD] = $this->couple('C', 'D');
        $this->goalWithContribution($pairCD, $c, 'Goal Pasangan CD');

        $this->actingAs($a);
        Volt::test('pairing.status')->call('unpair');

        $this->assertSame('active', $pairCD->fresh()->status);
        $this->assertSame($pairCD->id, $c->fresh()->activePair()->id);
        $this->assertSame($d->id, $c->fresh()->partner()->id);

        $this->actingAs($c)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertSee('Goal Pasangan CD');
    }

    public function test_unpair_button_is_visible_only_for_a_coupled_pair(): void
    {
        [$a, $b, $pair] = $this->couple();

        $this->actingAs($a)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Putuskan pairing');

        $solo = User::factory()->create();
        $this->actingAs($solo)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Putuskan pairing');
    }
}
