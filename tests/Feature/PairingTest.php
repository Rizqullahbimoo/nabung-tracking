<?php

namespace Tests\Feature;

use App\Models\Goal;
use App\Models\Invite;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Manually couple two users (bypassing the invite flow).
     */
    private function couple(User $one, User $two): Pair
    {
        return Pair::create([
            'user_one_id' => $one->id,
            'user_two_id' => $two->id,
            'status' => 'active',
            'paired_at' => now(),
        ]);
    }

    /**
     * Number of active two-member pairs (solo pairs are auto-created, so a bare
     * Pair::count() is not meaningful for these assertions).
     */
    private function coupledPairCount(): int
    {
        return Pair::query()->where('status', 'active')->whereNotNull('user_two_id')->count();
    }

    public function test_user_can_generate_an_invite_code(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('pairing.create-invite')->call('generate');

        $component->assertHasNoErrors();

        $code = $component->get('code');
        $this->assertIsString($code);
        $this->assertSame(8, strlen($code));

        $invite = Invite::firstWhere('code', $code);
        $this->assertNotNull($invite);
        $this->assertSame($user->id, $invite->created_by);
        $this->assertSame('pending', $invite->status);
        $this->assertNull($invite->accepted_by);
        $this->assertTrue($invite->expires_at->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_generating_an_invite_is_blocked_when_already_in_a_couple(): void
    {
        $user = User::factory()->create();
        $this->couple($user, User::factory()->create());

        $this->actingAs($user);

        $component = Volt::test('pairing.create-invite');
        $this->assertTrue($component->get('paired'));

        $component->call('generate')->assertHasErrors('code');

        $this->assertSame(0, Invite::where('created_by', $user->id)->count());
    }

    public function test_user_cannot_redeem_their_own_invite_code(): void
    {
        $user = User::factory()->create();
        $invite = Invite::create([
            'created_by' => $user->id,
            'code' => 'SELF0001',
            'status' => 'pending',
            'expires_at' => now()->addHours(24),
        ]);

        $this->actingAs($user);

        Volt::test('pairing.accept-invite')
            ->set('code', $invite->code)
            ->call('redeem')
            ->assertHasErrors('code')
            ->assertNoRedirect();

        $this->assertSame(0, $this->coupledPairCount());
        $this->assertNull($user->fresh()->partner());
    }

    public function test_unknown_invite_code_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('pairing.accept-invite')
            ->set('code', 'ZZZZ9999')
            ->call('redeem')
            ->assertHasErrors('code')
            ->assertNoRedirect();

        $this->assertSame(0, $this->coupledPairCount());
    }

    public function test_expired_invite_code_is_rejected(): void
    {
        $inviter = User::factory()->create();
        $acceptor = User::factory()->create();

        Invite::create([
            'created_by' => $inviter->id,
            'code' => 'EXPIRED1',
            'status' => 'pending',
            'expires_at' => now()->subMinute(),
        ]);

        $this->actingAs($acceptor);

        Volt::test('pairing.accept-invite')
            ->set('code', 'EXPIRED1')
            ->call('redeem')
            ->assertHasErrors('code')
            ->assertNoRedirect();

        $this->assertSame(0, $this->coupledPairCount());
    }

    public function test_partner_can_redeem_invite_and_couple_pair_is_created(): void
    {
        $inviter = User::factory()->create();
        $acceptor = User::factory()->create();

        $this->actingAs($inviter);
        $code = Volt::test('pairing.create-invite')->call('generate')->get('code');

        $this->actingAs($acceptor);
        Volt::test('pairing.accept-invite')
            ->set('code', $code)
            ->call('redeem')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $pair = Pair::query()->whereNotNull('user_two_id')->first();
        $this->assertNotNull($pair);
        $this->assertSame($inviter->id, $pair->user_one_id);
        $this->assertSame($acceptor->id, $pair->user_two_id);
        $this->assertSame('active', $pair->status);
        $this->assertNotNull($pair->paired_at);

        // Both former solo pairs are retired, not deleted.
        $this->assertSame(2, Pair::where('status', 'unpaired')->whereNull('user_two_id')->count());

        $invite = Invite::firstWhere('code', $code);
        $this->assertSame('accepted', $invite->status);
        $this->assertSame($acceptor->id, $invite->accepted_by);

        $this->assertTrue($inviter->fresh()->isPaired());
        $this->assertTrue($acceptor->fresh()->isPaired());
        $this->assertSame($acceptor->id, $inviter->fresh()->partner()->id);
        $this->assertSame($inviter->id, $acceptor->fresh()->partner()->id);
    }

    public function test_accepting_invite_supersedes_both_solo_pairs_and_keeps_their_goals(): void
    {
        $inviter = User::factory()->create();
        $acceptor = User::factory()->create();

        // Each starts solo with their own goal.
        $inviterSolo = $inviter->activePair();
        $acceptorSolo = $acceptor->activePair();
        $inviterGoal = Goal::create([
            'pair_id' => $inviterSolo->id,
            'proposed_by' => $inviter->id,
            'name' => 'Goal Solo Inviter',
            'target_amount' => 1_000_000,
            'status' => 'active',
        ]);

        $this->actingAs($inviter);
        $code = Volt::test('pairing.create-invite')->call('generate')->get('code');

        $this->actingAs($acceptor);
        Volt::test('pairing.accept-invite')->set('code', $code)->call('redeem')->assertHasNoErrors();

        $inviterSolo->refresh();
        $acceptorSolo->refresh();

        $this->assertSame('unpaired', $inviterSolo->status);
        $this->assertNotNull($inviterSolo->unpaired_at);
        $this->assertSame('unpaired', $acceptorSolo->status);

        // The solo goal is untouched and still belongs to the old solo pair.
        $this->assertSame($inviterSolo->id, $inviterGoal->fresh()->pair_id);
        $this->assertSame('active', $inviterGoal->fresh()->status);

        // Active pair is now the couple.
        $this->assertNotSame($inviterSolo->id, $inviter->fresh()->activePair()->id);
        $this->assertTrue($inviter->fresh()->isPaired());
    }

    public function test_invite_code_cannot_be_reused(): void
    {
        $inviter = User::factory()->create();
        $acceptor = User::factory()->create();
        $third = User::factory()->create();

        $this->actingAs($inviter);
        $code = Volt::test('pairing.create-invite')->call('generate')->get('code');

        $this->actingAs($acceptor);
        Volt::test('pairing.accept-invite')->set('code', $code)->call('redeem');

        $this->actingAs($third);
        Volt::test('pairing.accept-invite')
            ->set('code', $code)
            ->call('redeem')
            ->assertHasErrors('code')
            ->assertNoRedirect();

        $this->assertSame(1, $this->coupledPairCount());
    }

    public function test_dashboard_shows_solo_and_pairing_options_for_a_solo_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeVolt('pairing.status')
            ->assertSeeVolt('pairing.create-invite')
            ->assertSeeVolt('pairing.accept-invite')
            ->assertSee('Mode solo')
            ->assertSee('Buat Kode Invite');
    }

    public function test_dashboard_shows_partner_when_in_a_couple(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create(['name' => 'Pasangan Uji']);
        $this->couple($user, $partner);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Terhubung')
            ->assertSee('Pasangan Uji')
            ->assertDontSee('Buat Kode Invite');
    }
}
