<?php

namespace Tests\Feature;

use App\Models\Invite;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use RefreshDatabase;

    private function pair(User $one, User $two): Pair
    {
        return Pair::create([
            'user_one_id' => $one->id,
            'user_two_id' => $two->id,
            'status' => 'active',
            'paired_at' => now(),
        ]);
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

    public function test_generating_an_invite_is_blocked_when_already_paired(): void
    {
        $user = User::factory()->create();
        $this->pair($user, User::factory()->create());

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

        $this->assertSame(0, Pair::count());
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

        $this->assertSame(0, Pair::count());
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

        $this->assertSame(0, Pair::count());
    }

    public function test_partner_can_redeem_invite_and_pair_is_created(): void
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

        $pair = Pair::first();
        $this->assertNotNull($pair);
        $this->assertSame($inviter->id, $pair->user_one_id);
        $this->assertSame($acceptor->id, $pair->user_two_id);
        $this->assertSame('active', $pair->status);
        $this->assertNotNull($pair->paired_at);

        $invite = Invite::firstWhere('code', $code);
        $this->assertSame('accepted', $invite->status);
        $this->assertSame($acceptor->id, $invite->accepted_by);

        $this->assertTrue($inviter->fresh()->isPaired());
        $this->assertTrue($acceptor->fresh()->isPaired());
        $this->assertSame($acceptor->id, $inviter->fresh()->partner()->id);
        $this->assertSame($inviter->id, $acceptor->fresh()->partner()->id);
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

        $this->assertSame(1, Pair::count());
    }

    public function test_dashboard_shows_pairing_options_when_unpaired(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSeeVolt('pairing.status')
            ->assertSeeVolt('pairing.create-invite')
            ->assertSeeVolt('pairing.accept-invite')
            ->assertSee('Buat Kode Invite');
    }

    public function test_dashboard_shows_partner_when_paired(): void
    {
        $user = User::factory()->create();
        $partner = User::factory()->create(['name' => 'Pasangan Uji']);
        $this->pair($user, $partner);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Terhubung')
            ->assertSee('Pasangan Uji')
            ->assertDontSee('Buat Kode Invite');
    }
}
