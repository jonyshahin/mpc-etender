<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * No self-registration of any kind exists in this system.
 *
 * Internal users: /register was disabled in BUG-31 — Features::registration()
 * is omitted from config/fortify.php; admins create users via /admin/users.
 *
 * Vendors: /vendor/register was removed along with Vendor\RegisterController;
 * admins onboard vendors via /admin/vendors (Admin\VendorController::store).
 * See tests/Feature/Admin/VendorCreationTest.php for that flow.
 *
 * The tests below pin both routes as absent so neither can be reintroduced
 * without a deliberate decision.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_get_route_does_not_exist()
    {
        $this->get('/register')->assertNotFound();
    }

    public function test_register_post_route_does_not_exist()
    {
        $this->post('/register', [
            'name' => 'Stranger',
            'email' => 'stranger@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_vendor_register_get_route_does_not_exist()
    {
        $this->get('/vendor/register')->assertNotFound();
    }

    public function test_vendor_register_post_route_does_not_exist()
    {
        $this->post('/vendor/register', [
            'company_name' => 'Walk-in Contracting',
            'email' => 'walkin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();
    }

    public function test_vendor_register_route_name_is_not_registered()
    {
        $this->assertFalse(
            app('router')->has('vendor.register'),
            'The vendor.register route name should not exist — vendors are onboarded by admins.',
        );
    }
}
