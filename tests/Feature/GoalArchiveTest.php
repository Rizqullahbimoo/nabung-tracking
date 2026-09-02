<?php

namespace Tests\Feature;

use App\Models\Contribution;
use App\Models\Goal;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalArchiveTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Create an "unpaired" pair for the user (solo or coupled) plus a goal on it.
     */
    private function archivedGoal(User $user, bool $solo = true, array $goalOverrides = []): Goal
    {
        $pair = Pair::create([
            'user_one_id' => $user->id,
            'user_two_id' => $solo ? null : User::factory()->create()->id,
            'status' => 'unpaired',
            'paired_at' => $solo ? null : now()->subMonth(),
            'unpaired_at' => now(),
        ]);

        return Goal::create(array_merge([
            'pair_id' => $pair->id,
            'proposed_by' => $user->id,
            'name' => 'Goal Arsip',
            'target_amount' => 1_000_000,
            'status' => 'active',
            'approved_by' => $user->id,
            'approved_at' => now()->subMonth(),
        ], $goalOverrides));
    }

    private function activeGoal(User $user, array $goalOverrides = []): Goal
    {
        return Goal::create(array_merge([
            'pair_id' => $user->activePair()->id,
            'proposed_by' => $user->id,
            'name' => 'Goal Aktif',
            'target_amount' => 1_000_000,
            'status' => 'active',
        ], $goalOverrides));
    }

    public function test_archive_shows_goals_from_the_users_unpaired_pairs(): void
    {
        $user = User::factory()->create();
        $this->archivedGoal($user, solo: true, goalOverrides: ['name' => 'Nabung Solo Lama']);

        $this->actingAs($user)
            ->get(route('goals.archive'))
            ->assertOk()
            ->assertSee('Nabung Solo Lama')
            ->assertDontSee('Belum ada goal terarsip');
    }

    public function test_archive_excludes_goals_from_the_active_pair(): void
    {
        $user = User::factory()->create();
        $this->activeGoal($user, ['name' => 'Goal Masih Aktif']);
        $this->archivedGoal($user, solo: true, goalOverrides: ['name' => 'Goal Sudah Diarsipkan']);

        $this->actingAs($user)
            ->get(route('goals.archive'))
            ->assertOk()
            ->assertSee('Goal Sudah Diarsipkan')
            ->assertDontSee('Goal Masih Aktif');
    }

    public function test_archive_does_not_show_another_users_archived_goals(): void
    {
        $owner = User::factory()->create();
        $this->archivedGoal($owner, solo: true, goalOverrides: ['name' => 'Rahasia Milik Owner']);

        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('goals.archive'))
            ->assertOk()
            ->assertDontSee('Rahasia Milik Owner')
            ->assertSee('Belum ada goal terarsip');
    }

    public function test_archive_has_no_action_buttons(): void
    {
        $user = User::factory()->create();
        $goal = $this->archivedGoal($user, solo: true);
        Contribution::create([
            'goal_id' => $goal->id,
            'user_id' => $user->id,
            'amount' => 400_000,
            'type' => 'deposit',
            'contributed_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('goals.archive'))
            ->assertOk()
            ->assertDontSee('Tambah Kontribusi')
            ->assertDontSee('Setujui')
            ->assertDontSee('Tolak')
            ->assertDontSee('Buat Goal')
            ->assertDontSee('Kirim Usulan');
    }

    public function test_archive_shows_collected_total_and_solo_origin_badge(): void
    {
        $user = User::factory()->create();
        $goal = $this->archivedGoal($user, solo: true);
        Contribution::create([
            'goal_id' => $goal->id,
            'user_id' => $user->id,
            'amount' => 400_000,
            'type' => 'deposit',
            'contributed_at' => now()->subMonth()->toDateString(),
        ]);

        $this->actingAs($user)
            ->get(route('goals.archive'))
            ->assertOk()
            ->assertSee('Dari mode solo')
            ->assertSee('Rp 400.000');
    }

    public function test_archive_shows_couple_origin_badge_for_unpaired_couples(): void
    {
        $user = User::factory()->create();
        $this->archivedGoal($user, solo: false, goalOverrides: ['name' => 'Goal Eks Pasangan']);

        $this->actingAs($user)
            ->get(route('goals.archive'))
            ->assertOk()
            ->assertSee('Goal Eks Pasangan')
            ->assertSee('Dari pairing sebelumnya');
    }

    public function test_archive_empty_state_when_nothing_is_archived(): void
    {
        $user = User::factory()->create();
        $this->activeGoal($user); // only an active-pair goal

        $this->actingAs($user)
            ->get(route('goals.archive'))
            ->assertOk()
            ->assertSee('Belum ada goal terarsip');
    }

    public function test_archive_requires_authentication(): void
    {
        $this->get(route('goals.archive'))->assertRedirect(route('login'));
    }
}
