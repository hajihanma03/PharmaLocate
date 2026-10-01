<?php

namespace Tests\Unit;

use App\Models\Geofence;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** Sprint 7 white-box cases WB-01, WB-03, WB-04 (Documentation v1.3). */
class Sprint7WhiteBoxTest extends TestCase
{
    use RefreshDatabase;

    public function test_wb_01_passwords_are_hashed_on_registration(): void
    {
        User::create([
            'name' => 'Test User',
            'username' => 'testhash',
            'email' => 'testhash@example.com',
            'password' => 'password',
            'role' => 'customer',
        ]);

        $user = User::where('username', 'testhash')->first();
        $this->assertNotSame('password', $user->password);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_wb_03_stock_status_respects_threshold(): void
    {
        Setting::set('low_stock_threshold', 5);

        $this->assertSame('out_of_stock', Setting::statusForQuantity(0));
        $this->assertSame('low', Setting::statusForQuantity(4));
        $this->assertSame('available', Setting::statusForQuantity(5));
        $this->assertSame('available', Setting::statusForQuantity(10));
    }

    public function test_wb_04_geofence_contains_point_inside_and_outside(): void
    {
        $zone = new Geofence([
            'center_latitude' => 15.4870,
            'center_longitude' => 120.5960,
            'radius_meters' => 5000,
        ]);

        $this->assertTrue($zone->containsPoint(15.4890, 120.5980));
        $this->assertFalse($zone->containsPoint(14.5995, 120.9842));
    }
}
