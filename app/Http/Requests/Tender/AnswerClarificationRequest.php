<?php

namespace App\Http\Requests\Tender;

use Illuminate\Foundation\Http\FormRequest;

class AnswerClarificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.answer_clarifications');
    }

    public function rules(): array
    {
        return [
            'answer' => ['required', 'string', 'max:10000'],
        ];
    }
}
