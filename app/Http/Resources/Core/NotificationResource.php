<?php

namespace App\Http\Resources\Core;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
            'isRead' => !is_null($this->read_at),
            'readAt' => $this->read_at,
            'createdAt' => $this->created_at,
            'timeAgo' => $this->created_at->diffForHumans(),
        ];
    }
}
