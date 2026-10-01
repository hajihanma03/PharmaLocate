<?php

namespace Tests\Feature;

use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sprint 7 automated execution — maps to Documentation v1.3 API, white-box, and black-box cases.
 */
class Sprint7Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function bearer(string $username): array
    {
        $user = User::where('username', $username)->firstOrFail();

        return ['Authorization' => 'Bearer '.$user->createToken('sprint7')->plainTextToken];
    }

    // --- API-01, API-02, BB-01 ---

    public function test_api_01_health_returns_ok(): void
    {
        $response = $this->getJson('/api/health');

        $response->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonPath('app', 'PharmaLocate API')
            ->assertJsonStructure(['tile38']);
    }

    public function test_bb_01_prototype_ui_loads_at_root(): void
    {
        $this->get('/')->assertOk();
    }

    // --- API-03, API-04, BB-08, BB-10, BB-11 ---

    public function test_api_03_login_returns_token(): void
    {
        $this->postJson('/api/login', ['login' => 'admin', 'password' => 'password'])
            ->assertOk()
            ->assertJsonStructure(['token', 'user']);
    }

    public function test_bb_08_invalid_login_rejected(): void
    {
        $this->postJson('/api/login', ['login' => 'admin', 'password' => 'wrong'])
            ->assertStatus(422);
    }

    public function test_api_04_register_creates_customer(): void
    {
        $this->postJson('/api/register', [
            'name' => 'New Customer',
            'username' => 'newcust',
            'email' => 'newcust@example.com',
            'password' => 'password123',
        ])->assertCreated();
    }

    // --- API-05, BB-03 ---

    public function test_api_05_availability_returns_stock_rows(): void
    {
        $response = $this->getJson('/api/availability');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(10, count($response->json()));
    }

    // --- API-06, API-07, BB-05, BB-17 ---

    public function test_api_06_pharmacies_inside_geofence_returns_two(): void
    {
        $response = $this->getJson('/api/pharmacies?lat=15.487&lng=120.596');

        $response->assertOk();
        $this->assertCount(2, $response->json());
    }

    public function test_api_07_pharmacies_outside_geofence_returns_empty(): void
    {
        $response = $this->getJson('/api/pharmacies?lat=14.5995&lng=120.9842');

        $response->assertOk();
        $this->assertSame([], $response->json());
    }

    // --- API-08 ---

    public function test_api_08_geofences_public_index(): void
    {
        $this->getJson('/api/geofences')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Tarlac Provincial Hospital Zone']);
    }

    // --- API-09, API-10, BB-14, BB-15 ---

    public function test_api_09_customer_sees_own_inquiries_only(): void
    {
        $headers = $this->bearer('maria');

        $this->withHeaders($headers)->getJson('/api/inquiries')
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_api_10_customer_can_submit_inquiry(): void
    {
        $pharmacy = Pharmacy::first();
        $medicine = Medicine::first();
        $headers = $this->bearer('maria');

        $this->withHeaders($headers)->postJson('/api/inquiries', [
            'pharmacy_id' => $pharmacy->id,
            'medicine_id' => $medicine->id,
            'message' => 'Sprint 7 test inquiry',
        ])->assertCreated();
    }

    // --- API-11, BB-22 ---

    public function test_api_11_staff_can_reply_to_inquiry(): void
    {
        $inquiryId = DB::table('inquiries')->value('id');
        $headers = $this->bearer('sparx_staff');

        $this->withHeaders($headers)->patchJson("/api/inquiries/{$inquiryId}", [
            'response' => 'Yes, we have stock available.',
            'status' => 'resolved',
        ])->assertOk();
    }

    // --- API-12, BB-21 ---

    public function test_api_12_admin_dashboard_stats(): void
    {
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->getJson('/api/admin/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'pharmacies_count',
                'pending_inquiries',
                'sales_today',
                'low_stock_count',
            ]);
    }

    // --- API-13, API-14, BB-30, BB-31, WB-07, WB-08 ---

    public function test_api_13_pos_products_for_pharmacy(): void
    {
        $pharmacy = Pharmacy::where('name', 'SpaRx Pharmacy')->first();
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->getJson("/api/admin/pos/products?pharmacy_id={$pharmacy->id}")
            ->assertOk()
            ->assertJsonCount(10);
    }

    public function test_api_14_pos_sale_decrements_stock(): void
    {
        $pharmacy = Pharmacy::where('name', 'SpaRx Pharmacy')->first();
        $medicine = Medicine::where('name', 'Ibuprofen 400mg')->first();
        $before = DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicine->id)
            ->value('stock_quantity');

        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->postJson('/api/admin/transactions', [
            'pharmacy_id' => $pharmacy->id,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 1]],
        ])->assertCreated();

        $after = DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $pharmacy->id)
            ->where('medicine_id', $medicine->id)
            ->value('stock_quantity');

        $this->assertSame($before - 1, (int) $after);
    }

    public function test_wb_08_pos_rejects_over_sell(): void
    {
        $pharmacy = Pharmacy::where('name', 'SpaRx Pharmacy')->first();
        $medicine = Medicine::where('name', 'Amoxicillin 500mg')->first();
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->postJson('/api/admin/transactions', [
            'pharmacy_id' => $pharmacy->id,
            'items' => [['medicine_id' => $medicine->id, 'quantity' => 99]],
        ])->assertStatus(422);
    }

    // --- API-15, API-16, WB-09, BB-35 ---

    public function test_api_15_staff_cannot_list_users(): void
    {
        $headers = $this->bearer('sparx_staff');

        $this->withHeaders($headers)->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_api_16_admin_lists_users(): void
    {
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->getJson('/api/admin/users')
            ->assertOk()
            ->assertJsonCount(4);
    }

    // --- API-17, BB-37, WB-03 (threshold via settings) ---

    public function test_api_17_admin_updates_settings(): void
    {
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->patchJson('/api/admin/settings', [
            'low_stock_threshold' => 5,
        ])->assertOk()
            ->assertJsonPath('low_stock_threshold', '5');
    }

    // --- API-18, BB-40 ---

    public function test_api_18_admin_exports_inventory_csv(): void
    {
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->get('/api/admin/export?scope=inventory&format=csv')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    // --- API-19, API-20, WB-02, BB-42, BB-46 ---

    public function test_api_19_staff_pharmacies_scoped(): void
    {
        $headers = $this->bearer('sparx_staff');

        $response = $this->withHeaders($headers)->getJson('/api/admin/pharmacies');

        $response->assertOk();
        $this->assertCount(1, $response->json());
        $this->assertSame('SpaRx Pharmacy', $response->json()[0]['name']);
    }

    public function test_api_20_staff_cannot_create_pharmacy(): void
    {
        $headers = $this->bearer('sparx_staff');

        $this->withHeaders($headers)->postJson('/api/admin/pharmacies', [
            'name' => 'Blocked Pharmacy',
            'address' => 'Test',
            'latitude' => 15.49,
            'longitude' => 120.59,
        ])->assertForbidden();
    }

    public function test_wb_02_staff_cannot_update_other_pharmacy_stock(): void
    {
        $magic8 = Pharmacy::where('name', 'Magic 8 Pharmacy')->first();
        $medicine = Medicine::first();
        $headers = $this->bearer('sparx_staff');

        $this->withHeaders($headers)->patchJson("/api/admin/stock/{$magic8->id}/{$medicine->id}", [
            'stock_quantity' => 50,
        ])->assertForbidden();
    }

    public function test_bb_42_staff_stock_scoped_to_own_pharmacy(): void
    {
        $headers = $this->bearer('sparx_staff');

        $rows = $this->withHeaders($headers)->getJson('/api/admin/stock')->json();

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertSame('SpaRx Pharmacy', $row['pharmacy']);
        }
    }

    // --- WB-10 ---

    public function test_wb_10_cannot_demote_only_admin(): void
    {
        $admin = User::where('username', 'admin')->first();
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->patchJson("/api/admin/users/{$admin->id}", [
            'role' => 'customer',
        ])->assertStatus(422);
    }

    // --- BB-24 stock update ---

    public function test_bb_24_admin_updates_stock_quantity(): void
    {
        $pharmacy = Pharmacy::first();
        $medicine = Medicine::first();
        $headers = $this->bearer('admin');

        $this->withHeaders($headers)->patchJson("/api/admin/stock/{$pharmacy->id}/{$medicine->id}", [
            'stock_quantity' => 7,
        ])->assertOk()
            ->assertJsonPath('stock_quantity', 7);
    }

    public function test_panel_inside_tph_pharmacies_are_hidden_from_public_locator(): void
    {
        $hidden = Pharmacy::create([
            'name' => 'TPH Compound Pharmacy',
            'address' => 'Inside Tarlac Provincial Hospital',
            'latitude' => 15.4870,
            'longitude' => 120.5960,
            'is_active' => true,
            'inside_tph' => true,
        ]);

        $list = $this->getJson('/api/pharmacies?lat=15.487&lng=120.596')->json();
        $ids = collect($list)->pluck('id')->all();

        $this->assertNotContains($hidden->id, $ids);

        $availability = $this->getJson('/api/availability')->json();
        foreach ($availability as $row) {
            $this->assertNotSame('TPH Compound Pharmacy', $row['pharmacy'] ?? null);
        }
    }
}
