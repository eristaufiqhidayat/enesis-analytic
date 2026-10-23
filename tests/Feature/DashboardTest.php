<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MotorisSnapshotSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private const PERIOD = ['date_from' => '2026-10-01', 'date_to' => '2026-10-23'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MotorisSnapshotSeeder::class);
    }

    public function test_spa_shell_is_served(): void
    {
        $this->withoutVite()->get('/dashboard')->assertOk()->assertSee('id="app"', false);
    }

    public function test_api_requires_login(): void
    {
        $this->getJson('/api/v1/dashboard')->assertUnauthorized();
    }

    public function test_user_can_log_in_with_username(): void
    {
        User::factory()->create(['username' => 'user', 'password' => 'secret-pass']);

        $this->postJson('/api/v1/auth/login', ['username' => 'user', 'password' => 'secret-pass'])
            ->assertOk()
            ->assertJsonPath('data.username', 'user');
    }

    public function test_dashboard_matches_source_snapshot(): void
    {
        $data = $this->actingAs(User::factory()->create())
            ->getJson('/api/v1/dashboard?'.http_build_query(self::PERIOD))
            ->assertOk()
            ->json('data');

        $this->assertSame(20, $data['period']['hk_nasional']);
        $this->assertSame(6526706120, $data['kpi']['total_sales']);
        $this->assertSame(449, $data['pagination']['total']);

        $byRegion = collect($data['by_region'])->keyBy('region');
        $this->assertSame('TIMUR UTARA PUSAT', $data['by_region'][0]['region']);
        $this->assertSame(31464, $byRegion['TIMUR UTARA PUSAT']['call']);
        $this->assertSame(21451, $byRegion['TIMUR UTARA PUSAT']['ec']);
        $this->assertSame(806070500, $byRegion['TIMUR UTARA PUSAT']['total_sales']);
        $this->assertSame(7944, $byRegion['Cimahi']['call']);
        $this->assertSame(6317, $byRegion['Parakan Muncang']['call']);

        $quota = collect($data['motoris_quota'])->keyBy('depo');
        $this->assertSame(431, $quota->sum('kuota'));
        $this->assertSame(405, $quota->sum('actual'));
        $this->assertSame(7, $quota['Banjarmasin']['actual']);

        $call = collect($data['call_by_depo'])->keyBy('depo');
        $this->assertEqualsWithDelta(40.7489539748954, $call['Balaraja']['actual'], 1e-9);
        $this->assertEqualsWithDelta(37.649289099526065, $call['Cimahi']['actual'], 1e-9);
        $ec = collect($data['ec_by_depo'])->keyBy('depo');
        $this->assertEqualsWithDelta(24.701421800947866, $ec['Cimahi']['actual'], 1e-9);
        $ipt = collect($data['ipt_by_depo'])->keyBy('depo');
        $this->assertEqualsWithDelta(1.9322472200672356, $ipt['Bogor-Depok']['actual'], 1e-9);
        $omset = collect($data['omset_by_depo'])->keyBy('depo');
        $this->assertEqualsWithDelta(1357433.1797235024, $omset['Denpasar']['actual'], 1e-6);

        $sku = collect($data['sku_performance'])->keyBy('produk');
        $this->assertSame(1225187000, $sku['Adem Sari RTD Guava Less Sugar can 320 ml']['omset']);
        $this->assertEqualsWithDelta(16489.02, $sku['Adem Sari Hanger Sparkling Lime']['qty'], 0.001);
        $this->assertSame(153049, $sku['All Products']['eb']);
        $this->assertSame(6526706120, $sku['All Products']['omset']);

        $top = $data['frontliner_table'][0];
        $this->assertSame(233, $top['id_frontliner']);
        $this->assertSame(19, $top['hari_hadir']);
        $this->assertSame(31529500, $top['total_sales']);
        $this->assertEqualsWithDelta(113.55, $top['sr_call'], 0.01);

        $bigCity = collect($data['by_big_city'])->keyBy('name');
        $this->assertSame(53250, $bigCity['Jakarta']['call']);
        $this->assertSame(88408, $bigCity['Bodetabekten']['call']);
    }

    public function test_filters_narrow_the_data(): void
    {
        $this->actingAs(User::factory()->create());

        $depo = $this->getJson('/api/v1/dashboard?'.http_build_query(self::PERIOD + ['warehouse_id' => 48]))->json('data');
        $this->assertCount(1, $depo['by_region']);
        $this->assertSame(3556750, $depo['kpi']['total_sales']);
        $this->assertSame(3, $depo['pagination']['total']);

        $brand = $this->getJson('/api/v1/dashboard?'.http_build_query(self::PERIOD + ['product_id' => 20]))->json('data');
        $this->assertSame(1225187000, $brand['kpi']['total_sales']);

        $this->getJson('/api/v1/dashboard/filters')->assertOk()->assertJsonCount(27, 'data.warehouses');
        $this->getJson('/api/v1/dashboard/motoris?'.http_build_query(self::PERIOD))->assertOk()->assertJsonCount(449, 'data.rows');
    }
}
