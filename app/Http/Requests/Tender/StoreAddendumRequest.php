<?php

namespace App\Http\Requests\Tender;

use App\Rules\MinHoursAfter;
use App\Rules\PdfFile;
use Illuminate\Foundation\Http\FormRequest;

class StoreAddendumRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenders.issue_addenda');
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:500'],
            'content_en' => ['required', 'string', 'max:10000'],
            'content_ar' => ['nullable', 'string', 'max:10000'],
            'extends_deadline' => ['required', 'boolean'],
            'new_deadline' => ['nullable', 'required_if:extends_deadline,true', 'date', 'after:now'],
            // BUG-26: when an addendum extends the deadline, the opening
            // date must also move so it stays strictly after the new
            // deadline (with a minimum buffer enforced by MinHoursAfter,
            // configurable via tender.min_hours_between_deadline_and_opening).
            // Without this, addenda would create un-openable tenders
            // (submission_deadline >= opening_date).
            'new_opening_date' => [
                'nullable',
                'required_if:extends_deadline,true',
                'date',
                'after:new_deadline',
                new MinHoursAfter('new_deadline'),
            ],
            'file' => ['nullable', new PdfFile],
        ];
    }
}
