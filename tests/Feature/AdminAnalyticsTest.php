<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deze tests praten nooit echt met Google: de service wordt nagebootst (mock).
 */
class AdminAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeDashboard(): array
    {
        return [
            'kpis' => [
                'active_users' => ['value' => 281, 'change' => 12.5],
                'sessions' => ['value' => 374, 'change' => -3.0],
                'key_events' => ['value' => 0, 'change' => null],
                'avg_session_duration' => ['value' => 138, 'change' => 0.0],
                'engagement_rate' => ['value' => 0.55, 'change' => 2.1],
            ],
            'daily' => ['labels' => ['1 sep', '2 sep'], 'values' => [10, 12]],
            'channels' => [['name' => 'Organic Search', 'sessions' => 200]],
            'top_pages' => [['path' => '/contact', 'views' => 50, 'engagement_rate' => 0.3]],
            'period' => ['start' => '2026-09-01', 'end' => '2026-09-28'],
            'fetched_at' => now()->toIso8601String(),
        ];
    }

    public function test_gasten_worden_naar_de_loginpagina_gestuurd(): void
    {
        $this->get(route('admin.analytics.index'))->assertRedirect(route('login'));
    }

    public function test_klanten_kunnen_de_analytics_van_gkr_niet_zien(): void
    {
        // Er mag voor een klant ook geen enkele aanvraag naar Google gaan.
        $this->mock(GoogleAnalyticsService::class, function ($mock) {
            $mock->shouldNotReceive('getDashboard');
        });

        $client = User::factory()->create(['is_admin' => false]);

        $this->actingAs($client)
            ->get(route('admin.analytics.index'))
            ->assertRedirect();
    }

    public function test_admins_zien_de_cijfers(): void
    {
        $this->mock(GoogleAnalyticsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getDashboard')->once()->andReturn($this->fakeDashboard());
        });

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('281')
            ->assertSee('/contact')
            ->assertSee('Er worden nog geen conversies gemeten');
    }

    public function test_bij_een_fout_van_google_blijft_de_pagina_werken(): void
    {
        $this->mock(GoogleAnalyticsService::class, function ($mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('getDashboard')->andThrow(new \RuntimeException('PERMISSION_DENIED'));
        });

        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('De cijfers konden niet worden opgehaald')
            ->assertDontSee('PERMISSION_DENIED');
    }

    public function test_cijfers_vernieuwen_is_beperkt_tot_drie_keer_per_tien_minuten(): void
    {
        \Illuminate\Support\Facades\RateLimiter::clear('ga4-refresh');

        // De cache mag maar drie keer geleegd worden; de vierde klik doet niets.
        $this->mock(GoogleAnalyticsService::class, function ($mock) {
            $mock->shouldReceive('clearCache')->times(3);
        });

        $admin = User::factory()->create(['is_admin' => true]);

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($admin)
                ->post(route('admin.analytics.refresh'))
                ->assertSessionHas('success');
        }

        $this->actingAs($admin)
            ->post(route('admin.analytics.refresh'))
            ->assertRedirect(route('admin.analytics.index'))
            ->assertSessionHas('warning');
    }
}