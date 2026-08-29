<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('admin.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $credentials['email'])->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'Invalid admin credentials.'
            ])->withInput();
        }

        if (!in_array($user->role, ['admin', 'staff'])) {
            return back()->withErrors([
                'email' => 'You are not authorized to access the admin panel.'
            ])->withInput();
        }

        if ($user->status !== 'active') {
            return back()->withErrors([
                'email' => 'Your account is inactive.'
            ])->withInput();
        }

        if (!Hash::check($credentials['password'], $user->password_hash)) {
            return back()->withErrors([
                'email' => 'Invalid admin credentials.'
            ])->withInput();
        }

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
