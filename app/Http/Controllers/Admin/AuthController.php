<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminAuth;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function loginForm()
    {
        if (Auth::guard('admin')->check()) {
            return redirect('/admin');
        }

        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email|max:120',
            'password' => 'required|string|max:200',
        ]);

        // A deactivated user gets the same answer as a wrong password.
        if (Auth::guard('admin')->attempt($credentials + ['is_active' => true], $request->boolean('remember'))) {
            $request->session()->regenerate();
            $admin = Auth::guard('admin')->user();
            $admin->forceFill(['last_login_at' => now()])->save();
            $request->session()->put(AdminAuth::SESSION_KEY, AdminAuth::fingerprint($admin));
            SystemLog::record('auth.login', "{$admin->name} signed in to the admin panel", $admin, actor: ['admin', $admin->id, $admin->name]);

            return redirect()->intended('/admin');
        }

        SystemLog::record('auth.login_failed', 'Failed admin sign-in for ' . $credentials['email'], null,
            ['email' => $credentials['email']], 'warning', ['guest', null, $credentials['email']]);

        return back()->withInput($request->only('email'))->with('error', 'Incorrect email or password.');
    }

    public function logout(Request $request)
    {
        if ($admin = Auth::guard('admin')->user()) {
            SystemLog::record('auth.logout', "{$admin->name} signed out of the admin panel", $admin, actor: ['admin', $admin->id, $admin->name]);
        }

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/admin/login')->with('success', 'You have been signed out.');
    }
}
