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

    /**
     * Регистрация
     *
     * Выполнена простая регистрация, сразу выдает токен авторизации `access_token`, используйте в заголовке `Authorization: Bearer <token>`
     * @unauthenticated
     */
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

    /**
     * Авторизация
     *
     * Выдает новый токен авторизации для пользователя `access_token`, используйте в заголовке `Authorization: Bearer <token>`
     * @unauthenticated
     */
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
     * Выход
     *
     * Удаляет токен Laravel Sanctum
     * @throws \Illuminate\Auth\AuthenticationException
     */
    public function logout(Request $request): Response
    {
        $this->authService->logout($request->user());

        return response()->noContent();
    }

    /**
     * Обо мне
     *
     * @param \Illuminate\Http\Request $request
     * @return \App\Http\Resources\UserResource
     */
    public function me(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
