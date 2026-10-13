<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Eine neue E-Mail verlangt das aktuelle Passwort (sofern das Konto eins
     * hat) – sonst reicht eine offen gelassene Sitzung, um die Adresse und
     * über „Passwort vergessen" das ganze Konto zu übernehmen. Übernommen wird
     * die Adresse erst nach dem Bestätigungslink (siehe ProfileController).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            'current_password' => [
                Rule::requiredIf(fn () => $this->emailGeaendert() && $this->user()->password !== null),
                'nullable',
                'current_password',
            ],
        ];
    }

    /**
     * Microsoft-Konten bekommen ihre Adresse aus Entra – dort, nicht hier,
     * wird sie geändert.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->emailGeaendert() && $this->user()->nurUeberMicrosoft()) {
                    $validator->errors()->add('email', 'Deine Adresse kommt aus deinem Microsoft-Konto und lässt sich nur dort ändern.');
                }
            },
        ];
    }

    public function emailGeaendert(): bool
    {
        return mb_strtolower(trim((string) $this->input('email'))) !== mb_strtolower((string) $this->user()->email);
    }

    public function attributes(): array
    {
        return ['current_password' => 'aktuelles Passwort'];
    }
}
