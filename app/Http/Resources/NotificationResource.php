<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'user_id'       => $this->user_id,
            'type'          => $this->type,
            'channel'       => $this->channel->value,
            'message'       => $this->message,
            'status'        => $this->status->value,
            'status_label'  => $this->status->label(),
            'attempts'      => $this->attempts,
            'error_message' => $this->error_message,
            'processed_at'  => $this->processed_at?->format('Y-m-d H:i:s'),
            'created_at'    => $this->created_at->format('Y-m-d H:i:s'),
        ];
    }
}
