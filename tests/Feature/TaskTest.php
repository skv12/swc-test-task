<?php

namespace Tests\Feature;

use App\Enums\TaskStatus;
use App\Mail\NewTaskAddedMail;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TaskTest extends TestCase
{
    use WithFaker, RefreshDatabase;

    protected User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        Sanctum::actingAs(
            $this->user,
            ['*']
        );
    }

    public function test_index(): void
    {
        $this->seedingTasks();

        $response = $this->getJson('/api/tasks');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'description',
                    'status',
                    'employee' => [
                        'id',
                        'name',
                        'email',
                    ],
                    'estimate_until'
                ]
            ],
            'meta'
        ]);
        $response->assertJson([
            'meta' => [
                'total' => 24,
            ]
        ]);
        $response->assertJsonMissingPath('data.*.attachments');
    }

    public function test_index_with_status_filter(): void
    {
        $this->seedingTasks();

        $response = $this->getJson('/api/tasks?status=planned');
        $response->assertStatus(200);

        $response->assertJson(
            fn(AssertableJson $json) => $json->has('data')
                ->has('meta')
                ->where('data.0.status', TaskStatus::PLANNED)
                ->where('data.1.status', TaskStatus::PLANNED)
                ->etc()
        );
    }

    public function test_index_with_employee_id_filter(): void
    {
        $this->seedingTasks();

        $response = $this->getJson("/api/tasks?employee_id=" . $this->user->id);
        $response->assertStatus(200);

        $response->assertJson(
            fn(AssertableJson $json) => $json->has('data')
                ->has('meta')
                ->where('data.0.employee.id', $this->user->id)
                ->where('data.1.employee.id', $this->user->id)
                ->etc()
        );
    }

    public function test_index_with_estimate_until_filter(): void
    {
        $until = Carbon::now()->addDays(5);

        $this->seedingTasks();
        $response = $this->getJson("/api/tasks?estimate_until=" . $until->toIso8601ZuluString());
        $response->assertStatus(200);

        $response->assertJson(
            fn(AssertableJson $json) => $json->has('data')
                ->has('meta')
                ->where(
                    'data',
                    fn(Collection $data) => $data->filter(fn(array $item) => Carbon::parse($item['estimate_until'])->lte($until)
                            && Carbon::parse($item['estimate_until'])->addDays(5)->gt($until)
                        )->count() === $data->count()
                )->etc()
        );
    }

    public function test_index_with_estimate_at_filter(): void
    {
        $at = Carbon::now()->addDays(2)->startOfDay();

        $this->seedingTasks();
        $response = $this->getJson("/api/tasks?estimate_at=" . $at->toIso8601ZuluString());
        $response->assertStatus(200);

        $response->assertJson(
            fn(AssertableJson $json) => $json->has('data')
                ->has('meta')
                ->where(
                    'data',
                    fn(Collection $data) => $data->filter(fn(array $item) => Carbon::parse($item['estimate_until'])->gte($at))->count() === $data->count()
                )->etc()
        );
    }

    public function test_index_with_all_filter(): void
    {
        $this->seedingTasks();

        $at = Carbon::now()->addDays(2)->startOfDay();
        $until = Carbon::now()->addDays(5)->startOfDay();

        $queries = http_build_query([
            'estimate_at' => $at->toIso8601ZuluString(),
            'estimate_until' => $until->toIso8601ZuluString(),
            'status' => TaskStatus::PLANNED->value,
            'employee_id' => $this->user->id,
        ]);
        $response = $this->getJson('/api/tasks?' . $queries);
        $response->assertStatus(200);

        $response->assertJson(
            fn(AssertableJson $json) => $json->has('data')
                ->has('meta')
                ->where(
                    'data',
                    fn(Collection $data) => $data->filter(
                            fn(array $item) => Carbon::parse($item['estimate_until'])->gte($at)
                                && Carbon::parse($item['estimate_until'])->lte($until)
                                && $item['employee']['id'] === $this->user->id
                                && $item['status'] === TaskStatus::PLANNED->value
                        )->count() === $data->count()
                )->etc()
        );
    }

    public function test_store(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/tasks', [
            'title' => 'Test Title',
            'description' => 'Test Description',
            'employee_id' => $this->user->id,
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'title' => 'Test Title',
                'description' => 'Test Description',
                'employee' => [
                    'id' => $this->user->id,
                ],
                'status' => TaskStatus::PLANNED->value,
                'estimate_until' => null,
            ]
        ]);

        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Title',
        ]);

        Mail::assertQueued(NewTaskAddedMail::class, function (NewTaskAddedMail $mail) use ($response) {
            return $mail->hasTo($response->json('data.employee.email'));
        });
    }

    public function test_store_with_media(): void
    {
        Storage::fake();
        Mail::fake();

        $response = $this->post('/api/tasks', [
            'title' => 'Test Title',
            'description' => 'Test Description',
            'employee_id' => $this->user->id,
            'attachments' => [
                [
                    'file' => UploadedFile::fake()->image('attachment.jpg'),
                ],
                [
                    'url' => 'https://placehold.co/400x400',
                ]
            ]
        ]);

        $response->assertStatus(201);
        $response->assertJson([
            'data' => [
                'title' => 'Test Title',
                'description' => 'Test Description',
                'employee' => [
                    'id' => $this->user->id,
                ],
                'status' => TaskStatus::PLANNED->value,
                'estimate_until' => null,
            ]
        ]);
        $response->assertJsonCount(2, 'data.attachments');

        $this->assertDatabaseHas('tasks', [
            'title' => 'Test Title',
        ]);

        $this->assertDatabaseCount('media', 2);

        Mail::assertQueued(NewTaskAddedMail::class, function (NewTaskAddedMail $mail) use ($response) {
            return $mail->hasTo($response->json('data.employee.email'));
        });
    }

    public function test_show(): void
    {
        Storage::fake();

        $task = Task::factory()->attachments([
            UploadedFile::fake()->image('attachment.jpg'),
            'https://placehold.co/400x400',
            'https://placehold.co/300x300',
            UploadedFile::fake()->image('attachment2.jpg'),
        ])->create();

        $response = $this->getJson("/api/tasks/$task->id");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                'id',
                'title',
                'description',
                'employee' => [
                    'id',
                    'name',
                    'email',
                ],
                'status',
                'estimate_until',
                'attachments'
            ]
        ]);
        $response->assertJsonCount(4, 'data.attachments');
    }

    public function test_update(): void
    {
        Storage::fake();

        $estimate = now()->addDay();
        $task = Task::factory()->attachments([
            UploadedFile::fake()->image('attachment.jpg'),
            'https://placehold.co/400x400',
            'https://placehold.co/300x300',
            UploadedFile::fake()->image('attachment2.jpg'),
        ])->for(User::factory(), 'employee')->create([
            'status' => TaskStatus::PLANNED->value,
            'estimate_until' => $estimate
        ]);

        $keepMedia = $task->getMedia('attachments')->shuffle()->first();

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'employee_id' => $task->employee_id,
            'status' => TaskStatus::PLANNED->value,
            'estimate_until' => $estimate
        ]);

        $response = $this->put("/api/tasks/$task->id", [
            'title' => 'Test Title Updated',
            'description' => 'Test Description Updated',
            'employee_id' => $this->user->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'estimate_until' => null,
            'attachments' => [
                [
                    'file' => UploadedFile::fake()->image('updated.jpg'),
                    'order' => 3
                ],
                [
                    'url' => 'https://placehold.co/200x200',
                    'order' => 2
                ],
                [
                    'uuid' => $keepMedia->uuid,
                    'order' => 1
                ]
            ]
        ]);

        $response->assertStatus(200);
        $response->assertJson(fn(AssertableJson $json) => $json->has(
            'data',
            fn(AssertableJson $json) => $json->where('title', 'Test Title Updated')
                ->where('description', 'Test Description Updated')
                ->where('employee.id', $this->user->id)
                ->where('status', TaskStatus::IN_PROGRESS->value)
                ->where('estimate_until', null)
                ->count('attachments', 3)
                ->where('attachments', fn (Collection $attachments) => $attachments
                        ->map(function (string $attachment, string $key) {
                            return [
                                'uuid' => $key,
                                'url' => $attachment,
                            ];
                        })->values()
                    ->filter(function (array $attachment, int $key) use ($keepMedia) {
                        if ($key === 0) {
                            return $attachment['uuid'] === $keepMedia->uuid;
                        }
                        if ($key === 1) {
                            return Str::isUrl($attachment['url']) && Str::contains($attachment['url'], '200x200');
                        }
                        if ($key === 2) {
                            return Str::isUrl($attachment['url']) && Str::contains($attachment['url'], 'updated');
                        }
                        return false;
                    })->count() === 3)
                ->etc()
        ));

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Test Title Updated',
            'description' => 'Test Description Updated',
            'employee_id' => $this->user->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'estimate_until' => null,
        ]);

        $this->assertDatabaseCount('media', 3);
    }

    public function test_update_remove_all_media(): void
    {
        Storage::fake();

        $estimate = now()->addDay();
        $task = Task::factory()->attachments([
            UploadedFile::fake()->image('attachment.jpg'),
            'https://placehold.co/400x400',
            'https://placehold.co/300x300',
            UploadedFile::fake()->image('attachment2.jpg'),
        ])->for(User::factory(), 'employee')->create([
            'status' => TaskStatus::PLANNED->value,
            'estimate_until' => $estimate
        ]);

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => $task->title,
            'description' => $task->description,
            'employee_id' => $task->employee_id,
            'status' => TaskStatus::PLANNED->value,
            'estimate_until' => $estimate
        ]);

        $response = $this->put("/api/tasks/$task->id", [
            'title' => 'Test Title Updated',
            'description' => 'Test Description Updated',
            'employee_id' => $this->user->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'estimate_until' => null,
            'attachments' => []
        ]);

        $response->assertStatus(200);
        $response->assertJson(fn(AssertableJson $json) => $json->has(
            'data',
            fn(AssertableJson $json) => $json->where('title', 'Test Title Updated')
                ->where('description', 'Test Description Updated')
                ->where('employee.id', $this->user->id)
                ->where('status', TaskStatus::IN_PROGRESS->value)
                ->where('estimate_until', null)
                ->count('attachments', 0)
                ->etc()
        ));

        $this->assertDatabaseHas('tasks', [
            'id' => $task->id,
            'title' => 'Test Title Updated',
            'description' => 'Test Description Updated',
            'employee_id' => $this->user->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'estimate_until' => null,
        ]);
    }

    public function test_delete(): void
    {
        Storage::fake();

        $task = Task::factory()->attachments([
            UploadedFile::fake()->image('attachment.jpg'),
            'https://placehold.co/400x400',
            'https://placehold.co/300x300',
            UploadedFile::fake()->image('attachment2.jpg'),
        ])->create();

        $response = $this->deleteJson("/api/tasks/$task->id");
        $response->assertStatus(204);

        $this->assertDatabaseMissing($task, [
            'deleted_at' => null,
        ]);
    }

    private function seedingTasks(): void
    {
        Task::factory(12)->state(
            new Sequence(
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays()],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => null],
                ['status' => TaskStatus::IN_PROGRESS, 'estimate_until' => Carbon::now()->addDays()],
                ['status' => TaskStatus::IN_PROGRESS, 'estimate_until' => null],
                ['status' => TaskStatus::DONE, 'estimate_until' => Carbon::now()->addDays()],
                ['status' => TaskStatus::DONE, 'estimate_until' => null],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(2)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(3)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(4)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(5)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(6)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(7)],
            )
        )->for($this->user, 'employee')->create();

        Task::factory(12)->state(
            new Sequence(
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays()],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => null],
                ['status' => TaskStatus::IN_PROGRESS, 'estimate_until' => Carbon::now()->addDays()],
                ['status' => TaskStatus::IN_PROGRESS, 'estimate_until' => null],
                ['status' => TaskStatus::DONE, 'estimate_until' => Carbon::now()->addDays()],
                ['status' => TaskStatus::DONE, 'estimate_until' => null],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(2)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(3)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(4)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(5)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(6)],
                ['status' => TaskStatus::PLANNED, 'estimate_until' => Carbon::now()->addDays(7)],
            )
        )->for(User::factory(), 'employee')->create();
    }
}
