<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IntakeRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->family !== null; }
    public function rules(): array
    {
        return [
            'administered_at' => ['required','date','before_or_equal:now','regex:/([zZ]|[+-]\\d{2}:\\d{2})$/'],
            'timezone' => ['required','timezone:all'],
            'actual_amount' => ['nullable','numeric','gt:0','max:999999999.999','decimal:0,3'],
            'dose_unit' => ['nullable','required_with:actual_amount',Rule::prohibitedIf(!$this->filled('actual_amount')),'string','max:32'],
            'notes' => ['nullable','string',$this->user()->demoAccount ? 'max:1000' : 'max:10000'], 'client_request_id' => ['required','uuid'],
            'state' => ['required', $this->route('intake') ? Rule::in(['recorded','voided']) : Rule::in(['recorded'])],
            'correction_reason' => [$this->route('intake') ? 'required' : 'prohibited','string','max:500'],
        ];
    }
}
