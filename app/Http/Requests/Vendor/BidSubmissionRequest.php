<?php

namespace App\Http\Requests\Vendor;

use App\Enums\TenderStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BidSubmissionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'boq_prices' => ['nullable', 'array', 'min:1'],
            'boq_prices.*.boq_item_id' => ['required_with:boq_prices', 'uuid', 'exists:boq_items,id'],
            'boq_prices.*.unit_price' => ['required_with:boq_prices', 'numeric', 'gt:0'],
            'boq_prices.*.total_price' => ['required_with:boq_prices', 'numeric', 'gte:0'],
            'technical_notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $tender = $this->route('tender');

            if ($tender && $tender->status !== TenderStatus::Published) {
                $validator->errors()->add('tender', __('Tender must be published to accept bids.'));
            }

            if ($tender && $tender->submission_deadline && $tender->submission_deadline->isPast()) {
                $validator->errors()->add('tender', __('Submission deadline has passed.'));
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'boq_prices.required' => __('BOQ pricing is required.'),
            'boq_prices.*.boq_item_id.exists' => __('Invalid BOQ item selected.'),
            'boq_prices.*.unit_price.gt' => __('Unit price must be greater than zero.'),
            'boq_prices.*.total_price.gte' => __('Total price must be zero or greater.'),
        ];
    }
}
