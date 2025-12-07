<?php

namespace App\Services;

use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\TransientToken;

class AuthService
{

    public function register(RegisterDto $dto): User
    {
        $user = User::query()->create([
            'name' => $dto->name,
            'email' => $dto->email,
            'password' => $dto->password,
        ]);

        event(new Registered($user));

        return $user;
    }

    public function createToken(User $user): string
    {
        return $user->createToken('token')->plainTextToken;
    }

    /**
     * @param \App\Dto\LoginDto $dto
     * @return \App\Models\User
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function login(LoginDto $dto): User
    {
        try {
            $user = User::query()->where('email', $dto->email)->firstOrFail();
        } catch (ModelNotFoundException) {
            $this->throwInvalidCredentials();
        }

        if (!$user || !Hash::check($dto->password, $user->password)) {
            $this->throwInvalidCredentials();
        }

        return $user;
    }

    /**
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function logout(): void
    {
        $user = auth()->user();

        $currentToken = $user->currentAccessToken();

        if (!$currentToken instanceof Model) {
            throw new AuthenticationException('Invalid token.');
        }

        $currentToken->delete();
        event(new Logout('sanctum', $user));
    }

    private function throwInvalidCredentials(): void
    {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
            'password' => ['The provided credentials are incorrect.'],
        ]);
    }
}
