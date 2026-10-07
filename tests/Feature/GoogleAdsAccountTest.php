<?php

namespace Tests\Feature;

use App\Models\AdAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAdsAccountTest extends TestCase
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

    private function link(User $actor, User $client, ?string $customerId)
    {
        return $this->actingAs($actor)
            ->from(route('admin.users.index'))
            ->patch(route('admin.users.google-ads-account.update', $client), [
                'google_ads_customer_id' => $customerId,
            ]);
    }

    public function test_admin_kan_klantnummer_koppelen_en_streepjes_worden_weggehaald(): void
    {
        $client = $this->client();

        $this->link($this->admin(), $client, '123-456-7890')
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('ad_accounts', [
            'user_id'    => $client->id,
            'platform'   => AdAccount::PLATFORM_GOOGLE_ADS,
            'account_id' => '1234567890',
        ]);
    }

    public function test_klant_kan_geen_account_koppelen(): void
    {
        $client = $this->client();

        $this->link($client, $client, '123-456-7890');

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_gast_wordt_naar_login_gestuurd(): void
    {
        $this->patch(route('admin.users.google-ads-account.update', $this->client()), [
            'google_ads_customer_id' => '123-456-7890',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_zelfde_account_kan_niet_aan_twee_klanten(): void
    {
        $admin = $this->admin();
        $this->link($admin, $this->client(), '123-456-7890');

        $this->link($admin, $this->client(), '1234567890')
            ->assertSessionHasErrors('google_ads_customer_id');

        $this->assertDatabaseCount('ad_accounts', 1);
    }

    public function test_ongeldig_nummer_wordt_geweigerd(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        foreach (['12345', '123-456-78901', 'abc-def-ghij', 'act_1234567890'] as $invalid) {
            $this->link($admin, $client, $invalid)->assertSessionHasErrors('google_ads_customer_id');
        }

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_leeg_veld_ontkoppelt_het_account(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->link($admin, $client, '123-456-7890');
        $this->link($admin, $client, '')->assertSessionHasNoErrors();

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_nieuw_nummer_vervangt_het_oude(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->link($admin, $client, '123-456-7890');
        $this->link($admin, $client, '987-654-3210');

        $this->assertDatabaseCount('ad_accounts', 1);
        $this->assertSame('9876543210', $client->fresh()->googleAdsAccount->account_id);
    }

    public function test_meta_koppeling_blijft_staan(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $client->adAccounts()->create(['platform' => AdAccount::PLATFORM_META, 'account_id' => '111111111']);

        // Google koppelen en weer ontkoppelen mag het Meta-account niet raken
        $this->link($admin, $client, '123-456-7890');
        $this->link($admin, $client, '');

        $this->assertSame('111111111', $client->fresh()->metaAdAccount?->account_id);
        $this->assertNull($client->fresh()->googleAdsAccount);
    }

    public function test_admin_kan_geen_eigen_advertentieaccount_krijgen(): void
    {
        $admin = $this->admin();

        $this->link($admin, $this->admin(), '123-456-7890')->assertNotFound();

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_koppeling_verdwijnt_als_klant_admin_wordt(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->link($admin, $client, '123-456-7890');

        $this->actingAs($admin)->patch(route('admin.users.toggle-admin', $client));

        $this->assertDatabaseCount('ad_accounts', 0);
    }

    public function test_gebruikersbeheer_toont_klantnummer_met_streepjes(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->link($admin, $client, '1234567890');

        $this->actingAs($admin)->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('123-456-7890');
    }
}