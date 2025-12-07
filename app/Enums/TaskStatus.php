<?php

namespace App\Enums;

enum TaskStatus: string
{
    case PLANNED = 'planned';
    case IN_PROGRESS = 'in_progress';
    case DONE = 'done';
}
