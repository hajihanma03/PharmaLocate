<?php

namespace Database\Seeders;

use App\Models\Geofence;
use App\Models\Inquiry;
use App\Models\Medicine;
use App\Models\Pharmacy;
use App\Models\Setting;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed a realistic PharmaLocate demo dataset: an admin, two pharmacies
     * near Tarlac Provincial Hospital (SpaRx and Magic 8) with staff,
     * 10 medicines with per-pharmacy stock, a geofence zone, and a sample
     * customer with an inquiry.
     */
    public function run(): void
    {
        // --- Admin ---
        $admin = User::create([
            'name' => 'System Administrator',
            'username' => 'admin',
            'email' => 'admin@pharmalocate.test',
            'password' => 'password',
            'role' => 'admin',
        ]);

        // --- Pharmacies (coordinates near Tarlac Provincial Hospital) ---
        $sparx = Pharmacy::create([
            'name' => 'SpaRx Pharmacy',
            'address' => 'Romulo Blvd, San Vicente, Tarlac City',
            'contact_number' => '+63 900 111 2222',
            'operating_hours' => 'Mon-Sun 7:00 AM - 9:00 PM',
            'latitude' => 15.4890,
            'longitude' => 120.5980,
        ]);

        $magic8 = Pharmacy::create([
            'name' => 'Magic 8 Pharmacy',
            'address' => 'Maliwalo, Tarlac City',
            'contact_number' => '+63 900 333 4444',
            'operating_hours' => 'Mon-Sat 8:00 AM - 8:00 PM',
            'latitude' => 15.4820,
            'longitude' => 120.5905,
        ]);

        // --- Pharmacy staff (belong to a pharmacy) ---
        User::create([
            'name' => 'SpaRx Staff',
            'username' => 'sparx_staff',
            'email' => 'staff@sparx.test',
            'password' => 'password',
            'role' => 'staff',
            'pharmacy_id' => $sparx->id,
        ]);

        User::create([
            'name' => 'Magic 8 Staff',
            'username' => 'magic8_staff',
            'email' => 'staff@magic8.test',
            'password' => 'password',
            'role' => 'staff',
            'pharmacy_id' => $magic8->id,
        ]);

        // --- 10 medicines (scope limits testing to 10) ---
        $medicineNames = [
            ['name' => 'Paracetamol 500mg', 'brand' => 'Biogesic'],
            ['name' => 'Amoxicillin 500mg', 'brand' => 'Amoxil'],
            ['name' => 'Ibuprofen 400mg', 'brand' => 'Advil'],
            ['name' => 'Cetirizine 10mg', 'brand' => 'Virlix'],
            ['name' => 'Losartan 50mg', 'brand' => 'Cozaar'],
            ['name' => 'Metformin 500mg', 'brand' => 'Glucophage'],
            ['name' => 'Amlodipine 5mg', 'brand' => 'Norvasc'],
            ['name' => 'Omeprazole 20mg', 'brand' => 'Losec'],
            ['name' => 'Salbutamol Inhaler', 'brand' => 'Ventolin'],
            ['name' => 'Ascorbic Acid 500mg', 'brand' => 'Cecon'],
        ];

        $medicines = collect($medicineNames)->map(fn ($m) => Medicine::create($m));

        // --- Inventory: give each pharmacy varied stock/availability ---
        foreach ($medicines as $index => $medicine) {
            $sparx->medicines()->attach($medicine->id, [
                'stock_quantity' => $qty = [25, 0, 12, 40, 8, 0, 15, 30, 5, 60][$index],
                'price' => [5.50, 12.00, 8.75, 6.25, 15.00, 9.50, 14.00, 11.00, 250.00, 4.50][$index],
                'availability_status' => $this->statusFor($qty),
            ]);

            $magic8->medicines()->attach($medicine->id, [
                'stock_quantity' => $qty = [10, 20, 0, 5, 18, 22, 0, 9, 3, 45][$index],
                'price' => [5.75, 11.50, 9.00, 6.50, 14.50, 9.75, 13.50, 10.75, 245.00, 4.75][$index],
                'availability_status' => $this->statusFor($qty),
            ]);
        }

        // --- Geofence zone around Tarlac Provincial Hospital ---
        $zone = Geofence::create([
            'name' => 'Tarlac Provincial Hospital Zone',
            'description' => '5km service radius around Tarlac Provincial Hospital.',
            'center_latitude' => 15.4870,
            'center_longitude' => 120.5960,
            'radius_meters' => 5000,
        ]);
        $zone->pharmacies()->attach([$sparx->id, $magic8->id]);

        // --- A sample customer + inquiry ---
        $customer = User::create([
            'name' => 'Maria Santos',
            'username' => 'maria',
            'email' => 'maria@example.com',
            'password' => 'password',
            'role' => 'customer',
        ]);

        Inquiry::create([
            'user_id' => $customer->id,
            'pharmacy_id' => $sparx->id,
            'medicine_id' => $medicines[0]->id,
            'message' => 'Do you still have Biogesic (Paracetamol) in stock today?',
            'status' => 'pending',
        ]);

        Setting::set('low_stock_threshold', 10);
        Setting::set('notification_low_stock', true);
        Setting::set('notification_inquiries', true);
        Setting::set('backup_schedule_enabled', false);
        Setting::set('backup_schedule_time', '02:00');

        $staffUser = User::where('username', 'sparx_staff')->first();
        $paracetamol = $medicines[0];
        $stockRow = DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $sparx->id)
            ->where('medicine_id', $paracetamol->id)
            ->first();
        $saleQty = 2;
        $unitPrice = (float) $stockRow->price;
        $lineTotal = round($unitPrice * $saleQty, 2);
        $newQty = (int) $stockRow->stock_quantity - $saleQty;

        $transaction = Transaction::create([
            'pharmacy_id' => $sparx->id,
            'user_id' => $staffUser->id,
            'total_amount' => $lineTotal,
        ]);

        TransactionItem::create([
            'transaction_id' => $transaction->id,
            'medicine_id' => $paracetamol->id,
            'quantity' => $saleQty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal,
        ]);

        DB::table('pharmacy_medicine')
            ->where('pharmacy_id', $sparx->id)
            ->where('medicine_id', $paracetamol->id)
            ->update([
                'stock_quantity' => $newQty,
                'availability_status' => Setting::statusForQuantity($newQty),
                'updated_at' => now(),
            ]);
    }

    private function statusFor(int $qty): string
    {
        return match (true) {
            $qty <= 0 => 'out_of_stock',
            $qty < 10 => 'low',
            default => 'available',
        };
    }
}
