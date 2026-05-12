<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
       // We pakken de ingelogde gebruiker en vragen naar zijn projecten
    $projects = auth()->user()->projects;

    // We sturen de variabele $projects mee naar de view 'dashboard'
    return view('dashboard', [
        'projects' => $projects
    ]);
    }
}
