<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:10000'],
            'image' => ['nullable', 'image', 'max:10240'], // 10MB max
            'conversation_id' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'prompt.required' => 'Please enter a message.',
            'prompt.max' => 'Your message is too long. Please keep it under 10,000 characters.',
            'image.image' => 'The uploaded file must be an image.',
            'image.max' => 'The image size must not exceed 10MB.',
            'conversation_id.numeric' => 'Invalid conversation ID format.',
        ];
    }
}
