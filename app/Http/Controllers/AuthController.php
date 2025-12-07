<?php

namespace App\Http\Controllers;

use App\Dto\LoginDto;
use App\Dto\RegisterDto;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {
    }

    public function register(RegisterRequest $request): UserResource
    {
        $validated = $request->validated();

        $user = $this->authService->register(
            new RegisterDto(
                name: $validated['name'],
                email: $validated['email'],
                password: $validated['password'],
            )
        );

        return UserResource::make($user)->additional([
            'access_token' => $this->authService->createToken($request->userAgent(), $user),
            'token_type' => 'Bearer',
        ]);
    }

    public function login(LoginRequest $request): UserResource
    {
        $validated = $request->validated();

        $user = $this->authService->login(
            new LoginDto(
                email: $validated['email'],
                password: $validated['password']
            )
        );

        return UserResource::make($user)->additional([
            'access_token' => $this->authService->createToken($request->userAgent(), $user),
            'token_type' => 'Bearer',
        ]);
    }

    /**
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
