<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaAccountTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    private function client(): User
    {
        return User::factory()->create(['is_admin' => false]);
    }

    private function link(User $actingAs, User $target, ?string $accountId)
    {
        return $this->actingAs($actingAs)->patch(
            route('admin.users.meta-account.update', $target),
            ['meta_ad_account_id' => $accountId]
        );
    }

    public function test_admin_kan_account_koppelen_en_act_wordt_weggehaald(): void
    {
        $client = $this->client();

        $this->link($this->admin(), $client, 'act_123456789')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ad_accounts', [
            'user_id'    => $client->id,
            'platform'   => AdAccount::PLATFORM_META,
            'account_id' => '123456789',
        ]);
    }

    public function test_klant_kan_geen_account_koppelen(): void
    {
        $client = $this->client();

        // Een klant probeert zichzelf te koppelen: de admin-middleware moet dit tegenhouden
        $this->link($client, $client, '123456789');

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->patch(route('admin.users.meta-account.update', $this->client()), [
            'meta_ad_account_id' => '123456789',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_zelfde_account_kan_niet_aan_twee_klanten(): void
    {
        $admin = $this->admin();

        $this->link($admin, $this->client(), '123456789');
        $this->link($admin, $this->client(), 'act_123456789')
            ->assertSessionHasErrors('meta_ad_account_id');

        $this->assertDatabaseCount('ad_accounts', 1);
    }

    public function test_ongeldig_nummer_wordt_geweigerd(): void
    {
        $this->link($this->admin(), $this->client(), 'abc')
            ->assertSessionHasErrors('meta_ad_account_id');

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_leeg_veld_ontkoppelt_het_account(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->link($admin, $client, '123456789');
        $this->link($admin, $client, '')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_nieuw_nummer_vervangt_het_oude(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->link($admin, $client, '111111111');
        $this->link($admin, $client, '222222222');

        $this->assertDatabaseCount('ad_accounts', 1);
        $this->assertDatabaseHas('ad_accounts', ['user_id' => $client->id, 'account_id' => '222222222']);
    }

    public function test_admin_kan_geen_eigen_advertentieaccount_krijgen(): void
    {
        $this->link($this->admin(), $this->admin(), '123456789')
            ->assertNotFound();

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_koppeling_verdwijnt_als_klant_admin_wordt(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->link($admin, $client, '123456789');

        $this->actingAs($admin)->patch(route('admin.users.toggle-admin', $client));

        $this->assertTrue($client->fresh()->is_admin == true);
        $this->assertDatabaseCount('ad_accounts', 0);
    }
}