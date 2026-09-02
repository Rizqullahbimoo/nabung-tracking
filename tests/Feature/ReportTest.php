<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze "now" so "current month" is deterministic.
        $this->travelTo(Carbon::create(2026, 9, 15, 12));
    }

    /**
     * @return array{0: User, 1: User, 2: Pair}
     */
    private function pairedUsers(): array
    {
        $one = User::factory()->create(['name' => 'Rizqullah Bimo']);
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
            'target_amount' => 100_000_000,
            'status' => 'active',
        ], $overrides));
    }

    private function contribute(Goal $goal, User $user, int $amount, string $date): void
    {
        $goal->contributions()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'deposit',
            'contributed_at' => $date,
        ]);
    }

    public function test_report_only_counts_contributions_in_the_selected_month(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $this->contribute($goal, $a, 700_000, '2026-09-10');
        $this->contribute($goal, $b, 300_000, '2026-09-20');
        $this->contribute($goal, $a, 500_000, '2026-08-15'); // previous month

        $this->actingAs($a);

        $component = Volt::test('reports.monthly');
        $this->assertSame(1_000_000.0, $component->get('total'));

        $component->call('previousMonth');
        $this->assertSame(2026, $component->get('year'));
        $this->assertSame(8, $component->get('month'));
        $this->assertSame(500_000.0, $component->get('total'));
    }

    public function test_per_person_breakdown_is_accurate(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        $this->contribute($goal, $a, 400_000, '2026-09-05');
        $this->contribute($goal, $a, 300_000, '2026-09-25');
        $this->contribute($goal, $b, 300_000, '2026-09-18');
        $this->contribute($goal, $b, 999_000, '2026-10-01'); // next month, ignored

        $this->actingAs($a);
        $perPerson = Volt::test('reports.monthly')->get('perPerson')->keyBy('user_id');

        $this->assertSame(700_000.0, $perPerson[$a->id]['total']);
        $this->assertSame(300_000.0, $perPerson[$b->id]['total']);
        $this->assertSame('Rizqullah Bimo', $perPerson[$a->id]['name']);
    }

    public function test_per_goal_breakdown_is_accurate_and_lists_zero_goals(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $g1 = $this->makeGoal($pair->id, $a->id, ['name' => 'Dana Nikah']);
        $g2 = $this->makeGoal($pair->id, $a->id, ['name' => 'Liburan']);
        $this->makeGoal($pair->id, $a->id, ['name' => 'DP Rumah']); // no contributions this month

        $this->contribute($g1, $a, 200_000, '2026-09-08');
        $this->contribute($g1, $b, 100_000, '2026-09-12');
        $this->contribute($g2, $a, 50_000, '2026-09-15');

        $this->actingAs($a);
        $perGoal = Volt::test('reports.monthly')->get('perGoal')->keyBy('name');

        $this->assertSame(300_000.0, $perGoal['Dana Nikah']['total']);
        $this->assertSame(50_000.0, $perGoal['Liburan']['total']);
        $this->assertSame(0.0, $perGoal['DP Rumah']['total']);

        $nikahByName = collect($perGoal['Dana Nikah']['people'])->keyBy('name');
        $this->assertSame(200_000.0, $nikahByName['Rizqullah Bimo']['total']);
        $this->assertSame(100_000.0, $nikahByName['Pasangan']['total']);

        $liburanByName = collect($perGoal['Liburan']['people'])->keyBy('name');
        $this->assertSame(50_000.0, $liburanByName['Rizqullah Bimo']['total']);
        $this->assertSame(0.0, $liburanByName['Pasangan']['total']);
    }

    public function test_empty_state_when_no_contributions_that_month(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $this->contribute($goal, $a, 500_000, '2026-08-10'); // August only

        $this->actingAs($a)
            ->get(route('reports.monthly'))
            ->assertOk()
            ->assertSee('Belum ada kontribusi')
            ->assertDontSee('Rincian per goal');
    }

    public function test_report_is_scoped_to_the_viewers_own_pair(): void
    {
        [$a, $b, $pairA] = $this->pairedUsers();
        $goalA = $this->makeGoal($pairA->id, $a->id, ['name' => 'Rahasia Pair A']);
        $this->contribute($goalA, $a, 1_234_000, '2026-09-09');

        // A second, unrelated pair.
        $c = User::factory()->create();
        $d = User::factory()->create();
        $pairB = Pair::create([
            'user_one_id' => $c->id,
            'user_two_id' => $d->id,
            'status' => 'active',
            'paired_at' => now(),
        ]);

        $this->actingAs($c);
        $component = Volt::test('reports.monthly');

        $this->assertSame(0.0, $component->get('total'));

        $this->actingAs($c)
            ->get(route('reports.monthly'))
            ->assertOk()
            ->assertDontSee('Rahasia Pair A')
            ->assertDontSee('1.234.000');
    }

    public function test_solo_user_sees_their_own_report(): void
    {
        $solo = User::factory()->create(['name' => 'Bimo Solo']);
        $pair = $solo->activePair();
        $goal = $this->makeGoal($pair->id, $solo->id, ['name' => 'Nabung Sendiri']);

        $this->contribute($goal, $solo, 450_000, '2026-09-07');
        $this->contribute($goal, $solo, 50_000, '2026-09-20');
        $this->contribute($goal, $solo, 999_000, '2026-08-01'); // previous month

        $this->actingAs($solo)
            ->get(route('reports.monthly'))
            ->assertOk()
            ->assertDontSee('Belum ada kontribusi'); // September has data

        $this->actingAs($solo);
        $component = Volt::test('reports.monthly');

        $this->assertSame(500_000.0, $component->get('total')); // Sept only
        $perPerson = $component->get('perPerson');
        $this->assertCount(1, $perPerson);
        $this->assertSame('Bimo Solo', $perPerson->first()['name']);
        $this->assertSame(500_000.0, $perPerson->first()['total']);
    }

    public function test_trend_buckets_deposits_and_withdrawals_over_the_last_six_months(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);

        // September (the month being viewed).
        $this->contribute($goal, $a, 700_000, '2026-09-05');
        $goal->contributions()->create([
            'user_id' => $a->id,
            'amount' => 200_000,
            'type' => 'withdrawal',
            'note' => 'DP tiket',
            'contributed_at' => '2026-09-10',
        ]);
        // July.
        $this->contribute($goal, $b, 300_000, '2026-07-15');
        // March - outside the Apr..Sep window, must be excluded.
        $this->contribute($goal, $a, 999_000, '2026-03-01');

        $this->actingAs($a);
        $trend = Volt::test('reports.monthly')->get('trend')->keyBy('key');

        $this->assertCount(6, $trend);
        $this->assertFalse($trend->has('2026-03'));
        $this->assertSame(700_000.0, $trend['2026-09']['deposit']);
        $this->assertSame(200_000.0, $trend['2026-09']['withdrawal']);
        $this->assertSame(300_000.0, $trend['2026-07']['deposit']);
        $this->assertSame(0.0, $trend['2026-07']['withdrawal']);
        $this->assertSame(0.0, $trend['2026-08']['deposit']);

        // Oldest bucket first, newest (viewed month) last.
        $this->assertSame('2026-04', $trend->keys()->first());
        $this->assertSame('2026-09', $trend->keys()->last());
    }

    public function test_report_page_renders_the_trend_chart(): void
    {
        [$a, $b, $pair] = $this->pairedUsers();
        $goal = $this->makeGoal($pair->id, $a->id);
        $this->contribute($goal, $a, 500_000, '2026-09-05');

        $this->actingAs($a)
            ->get(route('reports.monthly'))
            ->assertOk()
            ->assertSee('Tren 6 bulan terakhir')
            ->assertSee('Kontribusi')
            ->assertSee('Penarikan');
    }
}
