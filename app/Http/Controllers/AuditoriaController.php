<?php

namespace App\Http\Controllers;

use App\Models\Auditoria;

class AuditoriaController extends Controller
{
    public function index()
    {
        $auditorias = Auditoria::with('user')->latest()->take(500)->get();
        return view('auditorias.index', compact('auditorias'));
    }
}
