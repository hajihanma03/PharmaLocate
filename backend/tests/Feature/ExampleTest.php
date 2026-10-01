<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_render_with_seeded_data(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertStatus(200)->assertSee('PharmaLocate');
        $this->get('/pharmacies')->assertStatus(200)->assertSee('SpaRx Pharmacy');
        $this->get('/medicines')->assertStatus(200)->assertSee('Paracetamol 500mg');
    }

    public function test_guests_are_redirected_from_inquiries_to_login(): void
    {
        $this->get('/inquiries')->assertRedirect('/login');
    }
}
