<?php

namespace App\Http\Requests\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

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
            // En edición es opcional: vacío = conservar la actual.
            'password' => [$target ? 'nullable' : 'required', 'string', Password::min(8)->letters()->numbers()],
            'role' => [
                'required', 'string', 'exists:roles,name',
                function ($attribute, $value, $fail) use ($target) {
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
            'password' => 'contraseña',
            'role' => 'rol',
        ];
    }
}
