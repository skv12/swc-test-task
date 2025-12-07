<?php

namespace App\Dto;

use App\Enums\TaskStatus;
use App\ValueObjects\Attachment;
use Carbon\Carbon;
use Illuminate\Support\Collection;

readonly class CreateTaskDto
{
    /**
     * @var \Illuminate\Support\Collection<int, Attachment>|null
     */
    public ?Collection $attachments;

    public function __construct(
        public string $title,
        public string $description,
        public int $employeeId,
        public TaskStatus $status = TaskStatus::PLANNED,
        public ?Carbon $estimateUntil = null,
        ?Collection $attachments = null,
    ) {
        if ($attachments instanceof Collection) {
            $this->attachments = $attachments->filter(fn ($attachment) => $attachment instanceof Attachment);
        }
    }
}