<?php

namespace App\Http\Controllers\Stylist;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StylistAuthController extends Controller
{
    public function loginForm()
    {
        if (Auth::guard('staff')->check()) {
            return redirect('/stylist/dashboard');
        }

        return view('stylist.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => 'required|string|max:60',
            'password' => 'required|string|max:200',
        ]);

        if (Auth::guard('staff')->attempt([
            'login_username' => $credentials['username'],
            'password'       => $credentials['password'],
            'is_active'      => 1,
        ], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $staff = Auth::guard('staff')->user();
            SystemLog::record('auth.login', "{$staff->name} signed in to the stylist portal", $staff, actor: ['staff', $staff->id, $staff->name]);

            return redirect()->intended('/stylist/dashboard');
        }

        SystemLog::record('auth.login_failed', 'Failed stylist sign-in for username ' . $credentials['username'], null,
            ['username' => $credentials['username']], 'warning', ['guest', null, $credentials['username']]);

        return back()->withInput($request->only('username'))->with('error', 'Incorrect username or password.');
    }

    public function logout(Request $request)
    {
        if ($staff = Auth::guard('staff')->user()) {
            SystemLog::record('auth.logout', "{$staff->name} signed out of the stylist portal", $staff, actor: ['staff', $staff->id, $staff->name]);
        }

        Auth::guard('staff')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/stylist/login')->with('success', 'You have been signed out.');
    }
}
