<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Internal-user self-registration was intentionally disabled in BUG-31.
 * This is a closed procurement system: internal users are created by admins
 * via /admin/users, vendors via /vendor/register through the separate
 * Vendor\RegisterController. The /register route Fortify ships by default
 * has no business case here, so Features::registration() is omitted from
 * config/fortify.php. The two tests below pin that behaviour.
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
}
