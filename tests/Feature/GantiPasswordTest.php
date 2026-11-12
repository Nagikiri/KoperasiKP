<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GantiPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_ganti_password_tampil_untuk_user_login(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('password.edit'))
            ->assertOk()
            ->assertSee('Ganti Password');
    }

    public function test_ganti_password_sukses_lalu_bisa_login_dengan_password_baru(): void
    {
        $user = User::factory()->create(['username' => 'admin']);

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordBaru123',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Kata sandi berhasil diubah.');

        $this->post(route('logout'));
        $this->assertGuest();

        $this->post(route('login'), [
            'username' => 'admin',
            'password' => 'passwordBaru123',
        ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_ganti_password_gagal_jika_password_lama_salah(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'bukan-password-lama',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordBaru123',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasErrorsIn('updatePassword', 'current_password');
    }

    public function test_ganti_password_gagal_jika_konfirmasi_tidak_cocok(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'passwordBaru123',
                'password_confirmation' => 'passwordLain123',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasErrorsIn('updatePassword', 'password');
    }

    public function test_ganti_password_gagal_jika_kurang_dari_delapan_karakter(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('password.edit'))
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertRedirect(route('password.edit'))
            ->assertSessionHasErrorsIn('updatePassword', 'password');
    }
}
