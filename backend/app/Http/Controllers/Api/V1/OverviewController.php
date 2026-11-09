<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\{ChildResource, RecordResource};
use App\Models\{Appointment, Medication, TimelineEntry};
use Illuminate\Http\Request;

class OverviewController extends Controller
{
    private function childIds(Request $r)
    {
        $r->validate(['child_id' => ['sometimes','ulid'], 'per_page' => ['sometimes','integer','between:1,100']]);
        $children = $r->user()->family->children()->whereNull('archived_at');
        if ($r->filled('child_id')) {
            $child = $r->user()->family->children()->whereKey($r->input('child_id'))->firstOrFail();
            $children->whereKey($child->id);
        }
        return $children->pluck('id');
    }
    public function dashboard(Request $r)
    {
        $ids = $this->childIds($r);
        $localNow = now($r->user()->family->timezone)->toImmutable();
        $today = $localNow->toDateString();
        $medications = Medication::with('child')->whereIn('child_id', $ids)
            ->whereNull('archived_at')->where('start_date', '<=', $today)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $today))
            ->orderBy('start_date')->orderBy('id')->get();
        $appointments = Appointment::with('child')->whereIn('child_id', $ids)->where('status', 'scheduled')->where('starts_at', '>=', now())->orderBy('starts_at')->limit(5)->get();
        $recent = TimelineEntry::with('child')->whereIn('child_id', $ids)
            ->whereBetween('entry_date', [$localNow->startOfYear()->toDateString(), $localNow->endOfYear()->toDateString()])
            ->orderByDesc('entry_date')->orderByDesc('id')->limit(5)->get();
        return response()->json(['data' => [
            'children' => ChildResource::collection($r->user()->family->children()->whereIn('id', $ids)->orderBy('display_name')->get())->resolve(),
            'appointments' => RecordResource::collection($appointments)->resolve(),
            'medications' => RecordResource::collection($medications)->resolve(),
            'recent_entries' => RecordResource::collection($recent)->resolve(),
            'timezone' => $r->user()->family->timezone,
            'reminder_notice' => 'Reminders are best effort and may be delayed when free hosting is asleep or paused.',
        ]]);
    }
    public function timeline(Request $r)
    {
        $ids = $this->childIds($r);
        $data = $r->validate(['kind' => ['sometimes','in:memory,milestone,health_note,life_event'], 'from' => ['sometimes','date_format:Y-m-d'], 'to' => ['sometimes','date_format:Y-m-d']]);
        $query = TimelineEntry::with('child')->whereIn('child_id', $ids);
        if (isset($data['kind'])) $query->where('kind', $data['kind']);
        if (isset($data['from'])) $query->whereDate('entry_date', '>=', $data['from']);
        if (isset($data['to'])) $query->whereDate('entry_date', '<=', $data['to']);
        return RecordResource::collection($query->orderByDesc('entry_date')->orderByDesc('id')->paginate($r->integer('per_page', 20)));
    }
    public function appointments(Request $r)
    {
        $ids = $this->childIds($r);
        $r->validate(['status' => ['sometimes','in:scheduled,completed,cancelled']]);
        $query = Appointment::with('child')->whereIn('child_id', $ids);
        if ($r->filled('status')) $query->where('status', $r->input('status'));
        return RecordResource::collection($query->orderBy('starts_at')->orderBy('id')->paginate($r->integer('per_page', 20)));
    }
}
