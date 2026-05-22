<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // We pakken de ingelogde gebruiker en laden zijn projecten direct INCLUSIEF de documenten
        $projects = auth()->user()->projects()->with('documents')->get();

        // We sturen de variabele $projects mee naar de view 'dashboard'
        return view('dashboard', [
            'projects' => $projects
        ]);
    }
}