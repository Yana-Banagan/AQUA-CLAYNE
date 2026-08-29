<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        $user = User::where(
            'email',
            $credentials['email']
        )->first();

        if (
            !$user ||
            !in_array($user->role, ['admin', 'staff']) ||
            $user->status !== 'active'
        ) {
            return back()->withErrors([
                'email' => 'Invalid admin credentials.'
            ]);
        }

        if (!Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => 'Invalid admin credentials.'
            ]);
        }

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
