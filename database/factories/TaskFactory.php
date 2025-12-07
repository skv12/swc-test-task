<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Task>
 */
class TaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->words(3, true),
            'description' => $this->faker->realText(),
            'employee_id' => User::factory(),
            'status' => TaskStatus::PLANNED,
            'estimate_until' => $this->faker->date(),
        ];
    }

    /**
     * @param array<string|\Illuminate\Http\UploadedFile> $attachments
     * @return $this
     */
    public function attachments(array $attachments): static
    {
        return $this->afterCreating(function (Task $task) use ($attachments) {
            foreach ($attachments as $attachment) {
                if ($attachment instanceof UploadedFile) {
                    $task->addMedia($attachment)->toMediaCollection('attachments');
                    continue;
                }
                if (Str::isUrl($attachment)) {
                    $task->addMediaFromUrl($attachment)->toMediaCollection('attachments');
                }
            }
        });
    }
}
