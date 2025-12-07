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
            $task->update(array_filter([
                'title' => $dto->title,
                'description' => $dto->description,
                'status' => $dto->status,
                'employee_id' => $dto->employeeId,
            ]));

            if ($dto->attachments === null) {
                $task->clearMediaCollection('attachments');
            } else {
                $existingMedia = $task->getMedia('attachments');

                $keepIds = $dto->attachments->filter(fn(Attachment $attachment) => $attachment->id !== null);

                $keepMedia = $existingMedia->filter(
                    fn(Media $media) => $keepIds->contains(fn(Attachment $attachment) => $attachment->id === $media->id)
                )->each(fn(Media $media) => $media->update([
                    'order_column' => $dto->attachments->firstWhere(
                            fn(Attachment $attachment) => $attachment->id === $media->id
                        )->order ?? null,
                ]));

                $newMedia = $dto->attachments->filter(fn(Attachment $attachment) => $attachment->id === null)->map(
                    fn(Attachment $attachment) => $this->addAttachment($task, $attachment)
                );

                $newMedia->each(fn(?Media $media) => $media !== null ? $keepMedia->push($media) : null);

                $existingMedia->whereNotIn('id', $keepMedia->pluck('id'))->each(fn(Media $media) => $media->delete());
            }
        });

        $task->loadMedia('attachments');

        return $task;
    }

    public function findById(int $id): Task
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
