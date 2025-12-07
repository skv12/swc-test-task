<?php

namespace App\Http\Controllers;

use App\Dto\CreateTaskDto;
use App\Dto\FilterTaskDto;
use App\Dto\UpdateTaskDto;
use App\Enums\TaskStatus;
use App\Http\Requests\FilterTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Requests\UpdateTaskRequest;
use App\Http\Resources\TaskCollection;
use App\Http\Resources\TaskResource;
use App\Services\TaskService;
use App\ValueObjects\Attachment;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class TaskController extends Controller
{
    public function __construct(
        protected TaskService $taskService
    ) {
    }

    public function index(FilterTaskRequest $request): TaskCollection
    {
        $validated = $request->validated();

        $tasks = $this->taskService->getTasks(
            new FilterTaskDto(
                status: isset($validated['status']) ? TaskStatus::tryFrom($validated['status']) : null,
                estimateAt: isset($validated['estimate_at']) ? Carbon::parse($validated['estimate_at']) : null,
                estimateUntil: isset($validated['estimate_until']) ? Carbon::parse($validated['estimate_until']) : null,
                employeeId: $validated['employee_id'] ?? null,
            )
        );

        return new TaskCollection($tasks);
    }

    public function store(StoreTaskRequest $request): TaskResource
    {
        $validated = $request->validated();

        /**
         * @var Collection<int, \App\ValueObjects\Attachment> $attachments
         */
        $attachments = new Collection();

        if (isset($validated['attachments'])) {
            foreach ($validated['attachments'] as $value) {
                $attachment = new Attachment(
                    file: $value['file'] ?? null,
                    url: $value['url'] ?? null,
                    order: $value['order'] ?? null,
                );

                $attachments->push($attachment);
            }
        }

        $task = $this->taskService->create(
            new CreateTaskDto(
                title: $validated['title'],
                description: $validated['description'],
                employeeId: $validated['employee_id'],
                status: isset($validated['status']) ? TaskStatus::tryFrom($validated['status']) : TaskStatus::PLANNED,
                estimateUntil: $validated['estimate_until'] ?? null,
                attachments: $attachments->isNotEmpty() ? $attachments : null,
            )
        );

        return TaskResource::make($task);
    }

    public function show(int $id): TaskResource
    {
        $task = $this->taskService->findByIdWithRelations($id);

        return TaskResource::make($task);
    }

    public function update(UpdateTaskRequest $request, int $id): TaskResource
    {
        $validated = $request->validated();

        $attachments = $validated['attachments'];
        if ($validated['attachments'] !== null) {
            $attachments = new Collection();
            /** @var array{
             *     file: ?string,
             *     url: ?string,
             *     uuid: ?string,
             *     order: ?int
             *  } $value
             */
            foreach ($validated['attachments'] as $value) {
                $attachment = new Attachment(
                    file: $value['file'] ?? null,
                    url: $value['url'] ?? null,
                    uuid: $value['uuid'] ?? null,
                    order: $value['order'] ?? 1,
                );

                $attachments->push($attachment);
            }
        }

        $dto = new UpdateTaskDto(
            title: $validated['title'],
            description: $validated['description'],
            status: TaskStatus::tryFrom($validated['status']),
            employeeId: $validated['employee_id'],
            estimateUntil: $validated['estimate_until'],
            attachments: $attachments,
        );

        $task = $this->taskService->update($id, $dto);

        return TaskResource::make($task);
    }

    public function destroy(int $task): Response
    {
        $this->taskService->delete($task);

        return response()->noContent();
    }
}
