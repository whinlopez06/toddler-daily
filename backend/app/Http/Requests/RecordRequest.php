<?php

namespace App\Http\Requests;

use App\Support\RecordTypes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->family !== null; }
    public function rules(): array
    {
        RecordTypes::get($this->route('recordType'));
        $version = ['version' => [$this->isMethod('POST') ? 'prohibited' : 'required','integer','min:1']];
        if ($this->isMethod('DELETE')) return $version;
        $date = ['required','date_format:Y-m-d','before_or_equal:'.now($this->user()->family->timezone)->toDateString()];
        $note = ['nullable','string',$this->user()->demoAccount ? 'max:1000' : 'max:10000'];
        return $version + match ($this->route('recordType')) {
            'timeline-entries' => [
                'kind' => ['required',Rule::in(['memory','milestone','health_note','life_event'])], 'title' => ['required','string','max:160'],
                'category' => ['nullable','string','max:50'], 'description' => $note, 'entry_date' => $date,
            ],
            'growth-measurements' => [
                'measured_on' => $date,
                'weight_value' => ['nullable','required_without:height_value','numeric','gt:0','max:99999.999','decimal:0,3'],
                'weight_unit' => ['nullable','required_with:weight_value',Rule::prohibitedIf(!$this->filled('weight_value')),Rule::in(['kg','lb'])],
                'height_value' => ['nullable','required_without:weight_value','numeric','gt:0','max:999999.99','decimal:0,2'],
                'height_unit' => ['nullable','required_with:height_value',Rule::prohibitedIf(!$this->filled('height_value')),Rule::in(['cm','in'])], 'notes' => $note,
            ],
            'medications' => [
                'name' => ['required','string','max:150'], 'instructions' => $note,
                'instructed_amount' => ['nullable','numeric','gt:0','max:999999999.999','decimal:0,3'],
                'dose_unit' => ['nullable','required_with:instructed_amount',Rule::prohibitedIf(!$this->filled('instructed_amount')),'string','max:32'],
                'start_date' => ['nullable','date_format:Y-m-d'],
                'end_date' => ['nullable','date_format:Y-m-d',Rule::when($this->filled('start_date'), ['after_or_equal:start_date'])],
                'archived' => ['sometimes','boolean'],
            ],
            'vaccination-records' => [
                'vaccine_name' => ['required','string','max:150'], 'administered_on' => $date,
                'dose_label' => ['nullable','string','max:50'], 'provider' => ['nullable','string','max:160'], 'notes' => $note,
            ],
            'appointments' => [
                'title' => ['required','string','max:160'], 'kind' => ['required',Rule::in(['general','vaccination'])],
                'starts_at' => ['required','date','regex:/([zZ]|[+-]\\d{2}:\\d{2})$/'],
                'timezone' => ['required','timezone:all'], 'provider' => ['nullable','string','max:160'],
                'clinic' => ['nullable','string','max:160'], 'reason' => $note, 'visit_summary' => $note,
                'status' => ['required',Rule::in(['scheduled','completed','cancelled'])],
                'reminders_enabled' => ['required','boolean'],
            ],
        };
    }
    public function after(): array
    {
        return [function ($validator) {
            if ($validator->errors()->isNotEmpty()) return;
            if ($this->route('recordType') === 'appointments' && $this->input('status') === 'completed' && \Carbon\CarbonImmutable::parse($this->input('starts_at'))->isFuture()) {
                $validator->errors()->add('status', 'A future appointment cannot be marked completed.');
            }
            if ($this->route('recordType') === 'timeline-entries' && $this->input('kind') === 'milestone' && $this->filled('category') && !in_array($this->input('category'), ['physical','language','cognitive','social_emotional','personal'])) {
                $validator->errors()->add('category', 'Choose physical, language, cognitive, social_emotional, or personal.');
            }
        }];
    }
}
