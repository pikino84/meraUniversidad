<?php

namespace App\Http\Controllers;

use App\Http\Requests\Users\UserRequest;
use App\Models\User;
use App\Services\Users\AccountInvitations;
use App\Support\Like;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(private readonly AccountInvitations $invitations) {}

    public function index(Request $request): View
    {
        Gate::authorize('viewAny', User::class);

        $search = trim((string) $request->input('search'));

        $users = User::query()
            ->with('roles:id,name')
            ->tap(fn ($q) => Like::contains($q, ['name', 'email'], $search))
            ->orderBy('name')
            ->paginate(20)
            ->onEachSide(1)
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
        // U24: el admin no define la contraseña. Se guarda una aleatoria que nadie conoce y
        // la persona crea la suya con el link que le llega por correo.
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'password' => Hash::make(Str::password(40)),
            ]);

            $user->assignRole($request->input('role'));

            return $user;
        });

        return $this->invitations->send($user, true)
            ? redirect()->route('users.index')->with('success', "Usuario creado. Enviamos a {$user->email} el link para crear su contraseña.")
            : redirect()->route('users.index')->with('error', "Usuario creado, pero no se pudo enviar el correo a {$user->email}. Revisa la configuración de correo y usa «Enviar link» en la lista.");
    }

    /**
     * Reenvía el link para crear o cambiar la contraseña (U24).
     */
    public function invite(User $user): RedirectResponse
    {
        Gate::authorize('update', $user);

        return $this->invitations->send($user, false)
            ? back()->with('success', "Enviamos a {$user->email} un link para cambiar su contraseña.")
            : back()->with('error', 'No se pudo enviar el correo. Revisa la configuración de correo del servidor.');
    }

    public function edit(User $user): View
    {
        Gate::authorize('update', $user);

        return view('users.edit', [
            'user' => $user->load('roles:id,name'),
            'roles' => $this->assignableRoles($user),
        ]);
    }

    public function update(UserRequest $request, User $user): RedirectResponse
    {
        DB::transaction(function () use ($request, $user) {
            // U24: la contraseña ya no se edita aquí; se usa «Enviar link».
            $user->fill($request->only('name', 'email'))->save();
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
     * Roles que se pueden asignar (U12): solo los que dan acceso al panel, porque el acceso
     * se decide por rol. Un admin no ve "super admin". Si el usuario ya tiene otro rol
     * (p. ej. "user"), se conserva en la lista para no perderlo al editar.
     */
    private function assignableRoles(?User $target = null)
    {
        $current = $target?->getRoleNames()->all() ?? [];

        return Role::query()
            ->where(fn ($q) => $q->whereIn('name', User::PANEL_ROLES)->orWhereIn('name', $current))
            ->when(! auth()->user()->isSuperAdmin(), fn ($q) => $q->where('name', '!=', User::ROLE_SUPER_ADMIN))
            ->get(['id', 'name'])
            ->sortBy(fn ($r) => array_search($r->name, User::PANEL_ROLES, true) === false ? 99 : array_search($r->name, User::PANEL_ROLES, true))
            ->values();
    }
}
