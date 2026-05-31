<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SitePasswordController extends Controller
{
    public function show()
    {
        return view('site-password');
    }

    public function store(Request $request)
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if ($request->input('password') !== config('app.site_password')) {
            return back()->withErrors([
                'password' => 'Het wachtwoord is onjuist.',
            ]);
        }

        session(['site_password_passed' => true]);

        return redirect()->intended('/');
    }
}