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

    /**
     * Список задач
     *
     * Получает все неудаленные задачи с соответствующими фильтрами.
     *
     * Запрос с пагинацией
     * @param \App\Http\Requests\FilterTaskRequest $request
     * @return \App\Http\Resources\TaskCollection
     */
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

    /**
     * Создание задачи
     *
     * Статус по умолчанию будет указано `planned`.
     *
     * Вложения `attachments` могут загружаться как и файл и ссылка на файл
     * @param \App\Http\Requests\StoreTaskRequest $request
     * @return \App\Http\Resources\TaskResource
     */
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
                    order: $value['order'] ?? 1,
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
                estimateUntil: isset($validated['estimate_until']) ? Carbon::parse($validated['estimate_until']): null,
                attachments: $attachments->isNotEmpty() ? $attachments : null,
            )
        );

        return TaskResource::make($task);
    }

    /**
     * Получить задачу по ID
     *
     * Также подгружаются вложения
     * @param int $id
     * @return \App\Http\Resources\TaskResource
     */
    public function show(int $id): TaskResource
    {
        $task = $this->taskService->findByIdWithRelations($id);

        return TaskResource::make($task);
    }

    /**
     * Обновить полностью задачу
     *
     * По REST API PUT метод должен обновить полностью или создать новый ресурс.
     *
     * При пустом `attachments` удалит все вложения.
     *
     * При указанном `attachments.*.uuid` оставляет вложение, `file` или `url` добавляют файл.
     *
     * Удаляет те вложения, которые не были указаны, и добавляет новые.
     * @param \App\Http\Requests\UpdateTaskRequest $request
     * @param int $id
     * @return \App\Http\Resources\TaskResource
     */
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

    /**
     * Удаление задачи
     *
     * Вложения не удаляет, так как модель имеет трейт `SoftDeletes`
     * @param int $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(int $id): Response
    {
        $this->taskService->delete($id);

        return response()->noContent();
    }
}
