<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Task
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'employee' => UserResource::make($this->whenLoaded('employee')),
            'estimate_until' => $this->estimate_until,
            /**
             * @var array<string, string>
             */
            'attachments' => $this->when(
                isset($this->mediaCollections['attachments']),
                $this->getMedia('attachments')->pluck('original_url', 'uuid')
            ),
        ];
    }
}
