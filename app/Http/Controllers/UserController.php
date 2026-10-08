<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\UserRequest;
use App\Models\User;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->input('search'));

        $users = User::query()
            ->with('roles:id,name')
            ->tap(fn ($q) => Like::contains($q, ['name', 'email'], $search))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    public function create(): View
    {
        Gate::authorize('create', User::class);

        return view('users.create', ['roles' => $this->assignableRoles()]);
    }

    public function store(UserRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make($request->input('password')),
            ]);

            $user->assignRole($request->input('role'));
        });

        return redirect()->route('users.index')->with('success', 'Usuario creado.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load('roles:id,name'),
            'roles' => $this->assignableRoles(),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            $user->fill($request->only('name', 'email'));

            if ($request->filled('password')) {
                $user->password = Hash::make($request->input('password'));
            }

            $user->save();
            $user->syncRoles([$request->input('role')]);
        });

        return redirect()->route('users.index')->with('success', 'Usuario actualizado.');
    }

    public function destroy(User $user): RedirectResponse
    {
        $response = Gate::inspect('delete', $user);

        if ($response->denied()) {
            return redirect()->route('users.index')->with('error', $response->message());
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', "Usuario «{$user->name}» eliminado.");
    }

    /**
     * Un admin no ve (ni puede elegir) el rol Super Admin.
     */
    private function assignableRoles()
    {
        return Role::query()
            ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('name', '!=', User::ROLE_SUPER_ADMIN))
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
