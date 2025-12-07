<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    /**
     * Testing api/register route
     */
    public function test_register(): void
    {
        $name = $this->faker->name();
        $email = $this->faker->freeEmail();
        $password = $this->faker->password(8);

        $response = $this->postJson('api/register', [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
            ],
            'access_token',
            'token_type',
        ]);
        $response->assertJson([
            'data' => [
                'name' => $name,
                'email' => $email,
            ]
        ]);
        $this->assertDatabaseHas('users', [
            'name' => $name,
            'email' => $email,
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => $response->baseRequest->header('User-Agent'),
        ]);
    }

    public function test_login(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
            ],
            'access_token',
            'token_type',
        ]);
        $response->assertJson([
            'data' => [
                'name' => $user->name,
                'email' => $user->email,
            ]
        ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'name' => $response->baseRequest->header('User-Agent'),
        ]);
    }

    public function test_login_invalid_email(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->postJson('api/login', [
            'email' => 'invalid_' . $user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_login_invalid_password(): void
    {
        $user = User::factory()->create([
            'password' => 'password',
        ]);

        $response = $this->postJson('api/login', [
            'email' => $user->email,
            'password' => 'password2',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    }

    public function test_logout(): void
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        $response = $this->postJson('api/logout');
        $response->assertStatus(204);
    }

    public function test_logout_invalid_token(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('api/logout');
        $response->assertStatus(401);
        $response->assertJson([
            'message' => 'Invalid token.',
        ]);
    }

    public function test_me(): void
    {
        Sanctum::actingAs(
            User::factory()->create(),
            ['*']
        );

        $response = $this->getJson('api/me');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email'
            ]
        ]);
    }
}
