<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

// PRD §4.8: login memakai username, bukan email bawaan Filament.
class Login extends BaseLogin
{
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getUsernameFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ]);
    }

    protected function getUsernameFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    protected function throwFailureValidationException(): never
    {
        // DESIGN.md §7.1: pesan gagal yang jelas dan menyebut penyebab.
        throw ValidationException::withMessages([
            'data.username' => 'Username atau password salah.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        // Keputusan 5/2.6: akun nonaktif ditolak lewat jalur kredensial yang
        // sama, sehingga pesannya identik dengan gagal login biasa dan tidak
        // membocorkan keberadaan akun.
        return [
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => true,
        ];
    }
}
