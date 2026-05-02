<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'string', 'max:255'],
            'type'    => ['required', 'string', 'max:100'],
            'message' => ['required', 'string', 'max:1000'],
            'channel' => ['sometimes', 'string', 'in:database,email,sms,push'],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'The user ID is required.',
            'user_id.max'      => 'The user ID may not exceed 255 characters.',
            'type.required'    => 'The notification type is required.',
            'type.max'         => 'The notification type may not exceed 100 characters.',
            'message.required' => 'The notification message is required.',
            'message.max'      => 'The message may not exceed 1000 characters.',
            'channel.in'       => 'The channel must be one of: database, email, sms, push.',
        ];
    }
}
