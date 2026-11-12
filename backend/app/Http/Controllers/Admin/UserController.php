<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        $users = User::whereIn('role', ['admin', 'staff'])
            ->select(['id', 'name', 'email', 'phone', 'role', 'status', 'avatar_path', 'created_at'])
            ->latest()
            ->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'nullable|string|max:30',
            'role' => 'required|in:admin,staff',
            'password' => 'required|string|min:8',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'],
            'status' => 'active',
            'password_hash' => Hash::make($data['password']),
        ]);

        return back()->with('success', "Administrative user {$data['name']} created successfully.");
    }

    public function edit(User $user)
    {
        $this->ensureManagedUser($user);

        return view('admin.users.edit', compact('user'));
    }

    public function update(Request $request, User $user)
    {
        $this->ensureManagedUser($user);

        $data = $request->validate([
            'name' => 'required|string|max:150',
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'phone' => 'nullable|string|max:30',
            'status' => 'required|in:active,inactive',
        ]);

        if ($user->id === $request->user()->id && $data['status'] !== 'active') {
            return back()
                ->withErrors(['status' => 'You cannot deactivate your own active admin session.'])
                ->withInput();
        }

        DB::transaction(function () use ($user, $data, $request) {
            $user->update($data);
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => $user->role.'.updated',
                'table_name' => 'users',
                'record_id' => $user->id,
            ]);
        });

        return redirect()
            ->route('admin.users.index', $request->query())
            ->with('success', "User {$user->name} updated successfully.");
    }

    public function toggleStatus(User $user)
    {
        $this->ensureManagedUser($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own active admin session.');
        }

        $newStatus = $user->status === 'active' ? 'inactive' : 'active';
        $user->update(['status' => $newStatus]);

        return back()->with('success', "User {$user->name} status changed to {$newStatus}.");
    }

    private function ensureManagedUser(User $user): void
    {
        abort_unless(in_array($user->role, ['admin', 'staff'], true), 404);
    }
}
