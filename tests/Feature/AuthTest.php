<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_login_tampil(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Username')
            ->assertSee('Kata Sandi');
    }

    public function test_login_dengan_username_dan_password_benar_masuk_ke_dashboard(): void
    {
        $user = User::factory()->create(['username' => 'admin']);

        $this->post(route('login'), [
            'username' => 'admin',
            'password' => 'password',
        ])->assertRedirect(RouteServiceProvider::HOME);

        $this->assertAuthenticatedAs($user);
    }

    public function test_password_salah_menampilkan_pesan_bahasa_indonesia_dan_tetap_tamu(): void
    {
        User::factory()->create(['username' => 'admin']);

        $this->from(route('login'))
            ->post(route('login'), [
                'username' => 'admin',
                'password' => 'salah-sekali',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'username' => 'Username atau kata sandi yang Anda masukkan salah.',
            ]);

        $this->assertGuest();
    }

    public function test_login_hanya_dengan_field_email_gagal(): void
    {
        User::factory()->create(['username' => 'admin']);

        $this->from(route('login'))
            ->post(route('login'), [
                'email' => 'admin@example.com',
                'password' => 'password',
            ])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('username');

        $this->assertGuest();
    }

    public function test_logout_menjadikan_user_tamu(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect('/');

        $this->assertGuest();
    }

    public function test_tamu_yang_membuka_dashboard_diarahkan_ke_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_route_register_lupa_password_dan_profil_tidak_ada(): void
    {
        $this->get('/register')->assertNotFound();
        $this->get('/forgot-password')->assertNotFound();
        $this->get('/profile')->assertNotFound();
    }
}
