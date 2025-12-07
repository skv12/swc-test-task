<?php

namespace App\Dto;

use App\Enums\TaskStatus;
use App\ValueObjects\Attachment;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Optional;

readonly class UpdateTaskDto
{
    /**
     * @var \Illuminate\Support\Collection<int, Attachment>|null
     */
    public ?Collection $attachments;

    public function __construct(
        public string $title,
        public string $description,
        public TaskStatus $status,
        public int $employeeId,
        public ?Carbon $estimateUntil = null,
        ?Collection $attachments = null,
    ) {
        if ($attachments instanceof Collection) {
            $attachments = $attachments->filter(fn ($attachment) => $attachment instanceof Attachment);
        }
        $this->attachments = $attachments;
    }
}