<?php

namespace App\Observers;

use App\Mail\NewTaskAddedMail;
use App\Models\Task;
use Illuminate\Support\Facades\Mail;

class TaskObserver
{
    /**
     * Handle the Task "created" event.
     */
    public function created(Task $task): void
    {
        $task->loadMissing('employee');
        Mail::to($task->employee)->locale(config('app.locale'))->send(new NewTaskAddedMail($task));
    }
}
