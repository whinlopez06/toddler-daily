<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChildRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->family !== null; }
    protected function prepareForValidation(): void
    {
        if ($this->has('first_name')) {
            $parts = [];
            foreach (['first_name', 'middle_name', 'last_name', 'suffix'] as $key) {
                $value = trim((string) $this->input($key, ''));
                $this->merge([$key => $value ?: null]);
                if ($value !== '') $parts[] = $value;
            }
            $this->merge(['display_name' => implode(' ', $parts)]);
        }
    }
    public function rules(): array
    {
        return [
            'first_name' => ['required_with:last_name','nullable','string','max:100'],
            'middle_name' => ['nullable','string','max:100'],
            'last_name' => ['required_with:first_name','nullable','string','max:100'],
            'suffix' => ['nullable','string','max:20'],
            'gender' => ['required_with:first_name','nullable','in:male,female'],
            'display_name' => ['required','string','max:100'],
            'birth_date' => ['required','date_format:Y-m-d','before_or_equal:'.now($this->user()->family->timezone)->toDateString()],
            'version' => [$this->isMethod('PATCH') ? 'required' : 'prohibited','integer','min:1'],
            'archived' => ['sometimes','boolean'],
        ];
    }
}
