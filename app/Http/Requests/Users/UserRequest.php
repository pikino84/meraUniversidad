<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $target */
        $target = $this->route('user');

        return $target
            ? Gate::allows('update', $target)
            : Gate::allows('create', User::class);
    }

    public function rules(): array
    {
        /** @var User|null $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'lowercase', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($target?->id),
            ],
            // U24: sin contraseña; la persona la crea con el link que le llega por correo.
            'role' => [
                'required', 'string', 'exists:roles,name',
                function ($attribute, $value, $fail) use ($target) {
                    // U12: solo roles que dan acceso al panel (o el que ya tenía el usuario).
                    $current = $target?->getRoleNames()->all() ?? [];
                    if (! in_array($value, array_merge(User::PANEL_ROLES, $current), true)) {
                        $fail('Ese rol no da acceso al panel. Elige Administrador o Super administrador.');

                        return;
                    }

                    $response = Gate::inspect('assignRole', [User::class, (string) $value, $target]);

                    if ($response->denied()) {
                        $fail($response->message());
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nombre',
            'email' => 'correo electrónico',
            'role' => 'rol',
        ];
    }
}
