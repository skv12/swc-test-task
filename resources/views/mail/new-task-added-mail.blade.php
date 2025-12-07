<x-mail::message>
    # {{ __('New Task') }}

    {{ __("You have been given a task #:id", ['id' => $task->id]) }} - {{ $task->title }}

    {{ __("Description: :description", ['description' => $task->description]) }}
    @if($task->estimate_until !== null)
    {{ __("Estimate Until: :until", ['until' => $task->estimate_until->toString()]) }}
    @endif
</x-mail::message>
