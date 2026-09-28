<?php

namespace App\Http\Controllers;

class HomeController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function promociones()
    {
        // Placeholder — acá irá el módulo real de promociones
        return view('promociones');
    }

    public function ventas()
    {
        // Placeholder — acá irá el POS / módulo de ventas
        return view('ventas');
    }
}
