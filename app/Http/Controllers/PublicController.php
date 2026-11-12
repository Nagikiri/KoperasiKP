<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class PublicController extends Controller
{
    /**
     * Halaman informasi publik. Tidak melakukan query database (BR-17).
     */
    public function index(): View
    {
        return view('public.home');
    }
}
