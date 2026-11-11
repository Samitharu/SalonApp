<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AdminAuth;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function edit(Request $request)
    {
        return view('admin.account', ['admin' => $request->user('admin')]);
    }

    public function update(Request $request)
    {
        $admin = $request->user('admin');

        $data = $request->validate([
            'name'             => 'required|string|max:120',
            'email'            => ['required', 'email', 'max:120', Rule::unique('admins', 'email')->ignore($admin->id)],
            'current_password' => 'required|current_password:admin',
            'password'         => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
        ]);

        $admin->name  = $data['name'];
        $admin->email = $data['email'];

        if (!empty($data['password'])) {
            $admin->password = Hash::make($data['password']);
        }

        $admin->save();

        // Other devices are signed out by a new password; this one stays signed in.
        $request->session()->put(AdminAuth::SESSION_KEY, AdminAuth::fingerprint($admin));

        $changed = SystemLog::changes($admin);
        if ($changed || !empty($data['password'])) {
            $what = array_keys($changed);
            if (!empty($data['password'])) {
                $what[] = 'password';
            }
            SystemLog::record('auth.account_updated', "{$admin->name} updated their admin account: " . implode(', ', $what), $admin, $changed);
        }

        return back()->with('success', 'Your account has been updated.');
    }
}
