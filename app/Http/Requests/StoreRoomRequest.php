<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'admin_password' => ['required', 'string', 'min:4', 'max:40'],
            'seconds_per_question' => ['required', 'integer', 'between:5,120'],
            'questions' => ['required', 'array', 'min:1', 'max:50'],
            'questions.*.question_text' => ['required', 'string', 'max:255'],
            'questions.*.options' => ['required', 'array', 'size:4'],
            'questions.*.options.*' => ['required', 'string', 'max:120'],
            'questions.*.correct_option' => ['required', 'integer', 'between:0,3'],
        ];
    }
}
