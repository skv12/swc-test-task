<?php

namespace App\Services;

use App\Dto\CreateTaskDto;
use App\Dto\FilterTaskDto;
use App\Dto\UpdateTaskDto;
use App\Enums\TaskStatus;
use App\Models\Task;
use App\ValueObjects\Attachment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class TaskService
{
    public function __construct()
    {
    }

    /**
     * @param \App\Dto\FilterTaskDto $dto
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Task>
     */
    public function getTasks(FilterTaskDto $dto): LengthAwarePaginator
    {
        return Task::query()
            ->when($dto->status !== null, fn(Builder $query) => $query->where('status', $dto->status))
            ->when($dto->employeeId !== null, fn(Builder $query) => $query->where('employee_id', $dto->employeeId))
            ->when(
                $dto->estimateAt !== null,
                fn(Builder $query) => $query->whereNotNull('estimate_until')
                    ->where('estimate_until', '>=', $dto->estimateAt)
            )
            ->when(
                $dto->estimateUntil !== null,
                fn(Builder $query) => $query->whereNotNull('estimate_until')
                    ->where('estimate_until', '<=', $dto->estimateUntil)
            )
            ->with('employee')
            ->paginate();
    }

    public function create(CreateTaskDto $dto): Task
    {
        $task = DB::transaction(function () use ($dto) {
            $task = Task::create([
                'title' => $dto->title,
                'description' => $dto->description,
                'status' => $dto->status ?? TaskStatus::PLANNED,
                'employee_id' => $dto->employeeId,
            ]);

            $dto->attachments?->each(fn(Attachment $attachment) => $this->addAttachment($task, $attachment));

            return $task;
        });

        $task->load('employee');
        $task->getMediaCollection('attachments');

        return $task;
    }

    public function update(int $id, UpdateTaskDto $dto): Task
    {
        $task = $this->findById($id);

        DB::transaction(function () use (&$task, $dto) {
            $task->update([
                'title' => $dto->title,
                'description' => $dto->description,
                'status' => $dto->status,
                'employee_id' => $dto->employeeId,
                'estimate_until' => $dto->estimateUntil,
            ]);

            if ($dto->attachments === null || $dto->attachments->isEmpty()) {
                $task->clearMediaCollection('attachments');
            } else {
                $existingMedia = $task->getMedia('attachments');

                /**
                 * @var \Illuminate\Support\Collection<int, Media> $keepIds
                 */
                $newMedia = $dto->attachments->sortBy('order')->map(function (Attachment $attachment) use ($task, $existingMedia) {
                    if ($attachment->uuid) {
                        return $existingMedia->firstWhere('uuid', $attachment->uuid);
                    }
                    return $this->addAttachment($task, $attachment);
                })->filter();

                $task->updateMedia($newMedia->toArray(), 'attachments');
            }
        });

        $task->load('employee');
        $task->getMediaCollection('attachments');

        return $task;
    }

    public function findById(int $id): Task
    {
        return Task::query()->findOrFail($id);
    }

    public function findByIdWithRelations(int $id): Task
    {
        $task = Task::query()->with('employee')->findOrFail($id);
        $task->getMediaCollection('attachments');

        return $task;
    }

    public function delete(int $id): void
    {
        $task = $this->findById($id);

        $task->delete();
    }

    private function addAttachment(Task $task, Attachment $attachment): ?Media
    {
        if ($attachment->file !== null) {
            return $task->addMedia($attachment->file)->setOrder($attachment->order)->toMediaCollection('attachments');
        }
        if ($attachment->url !== null) {
            return $task->addMediaFromUrl($attachment->url)->setOrder($attachment->order)->toMediaCollection(
                'attachments'
            );
        }
        return null;
    }
}
