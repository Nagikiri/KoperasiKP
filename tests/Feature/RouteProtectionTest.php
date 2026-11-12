<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    /**
     * URI yang boleh diakses tanpa login (dicocokkan berdasarkan URI).
     */
    private const URI_PUBLIK = ['/', 'login'];

    /**
     * Prefiks URI route vendor yang dibiarkan.
     */
    private const PREFIKS_VENDOR = ['_ignition', 'sanctum'];

    public function test_semua_route_selain_whitelist_wajib_login(): void
    {
        $tanpaAuth = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (in_array($uri, self::URI_PUBLIK, true) || Str::startsWith($uri, self::PREFIKS_VENDOR)) {
                continue;
            }

            if (! in_array('auth', $route->gatherMiddleware(), true)) {
                $tanpaAuth[] = implode('|', $route->methods()) . ' ' . $uri;
            }
        }

        $this->assertSame([], $tanpaAuth, 'Route berikut tidak dilindungi middleware auth.');
    }
}
