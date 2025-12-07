<?php

namespace App\Dto;

use App\Enums\TaskStatus;
use Carbon\Carbon;

class FilterTaskDto
{
    public function __construct(
        public ?TaskStatus $status = null,
        public ?Carbon $estimateAt = null,
        public ?Carbon $estimateUntil = null,
        public ?int $employeeId = null,
    ) {
    }
}