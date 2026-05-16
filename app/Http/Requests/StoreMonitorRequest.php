<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonitorRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'check_interval' => $this->input(
                'check_interval',
                config('monitoring.defaults.check_interval')
            ),
            'threshold' => $this->input(
                'threshold',
                config('monitoring.defaults.threshold')
            ),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url:http,https',
                'max:'.config('monitoring.limits.url_max_length'),
                Rule::unique('monitors', 'url'),
            ],
            'check_interval' => [
                'sometimes',
                'integer',
                'min:'.config('monitoring.limits.check_interval_min'),
                'max:'.config('monitoring.limits.check_interval_max'),
            ],
            'threshold' => [
                'sometimes',
                'integer',
                'min:'.config('monitoring.limits.threshold_min'),
            ],
        ];
    }
}
