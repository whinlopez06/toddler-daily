<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\{SaveChildRecord, WriteAudit};
use App\Http\Controllers\Controller;
use App\Http\Requests\RecordRequest;
use App\Http\Resources\RecordResource;
use App\Models\Child;
use App\Support\RecordTypes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RecordController extends Controller
{
    public function index(Request $r, string $child, string $recordType)
    {
        $c = $this->child($r, $child);
        [$class, $date] = RecordTypes::get($recordType);
        $data = $r->validate(['per_page' => ['sometimes','integer','between:1,100'], 'from' => ['sometimes','date_format:Y-m-d'], 'to' => ['sometimes','date_format:Y-m-d'], 'status' => ['sometimes','string'], 'kind' => ['sometimes','string'], 'search' => ['sometimes','string','max:100']]);
        $query = $class::where('child_id', $c->id);
        if (isset($data['from'])) $query->whereDate($date, '>=', $data['from']);
        if (isset($data['to'])) $query->whereDate($date, '<=', $data['to']);
        if ($recordType === 'appointments' && isset($data['status'])) $query->where('status', $data['status']);
        if ($recordType === 'timeline-entries' && isset($data['kind'])) $query->where('kind', $data['kind']);
        if (isset($data['search'])) {
            $field = match($recordType) { 'medications' => 'name', 'vaccination-records' => 'vaccine_name', 'growth-measurements' => null, default => 'title' };
            if ($field) $query->where($field, 'ilike', '%'.addcslashes($data['search'], '%_\\').'%');
        }
        return RecordResource::collection($query->orderByDesc($date)->orderByDesc('id')->paginate($r->integer('per_page', 20)));
    }
    public function show(Request $r, string $child, string $recordType, string $recordId): RecordResource
    {
        $c = $this->child($r, $child);
        [$class] = RecordTypes::get($recordType);
        return new RecordResource($class::where('child_id', $c->id)->whereKey($recordId)->firstOrFail());
    }
    public function store(RecordRequest $r, string $child, string $recordType, SaveChildRecord $action)
    {
        $record = $action->handle($r->user(), $this->child($r, $child), $recordType, $r->validated());
        return (new RecordResource($record))->response()->setStatusCode(201);
    }
    public function update(RecordRequest $r, string $child, string $recordType, string $recordId, SaveChildRecord $action): RecordResource
    {
        $c = $this->child($r, $child);
        [$class] = RecordTypes::get($recordType);
        $record = $class::where('child_id', $c->id)->whereKey($recordId)->firstOrFail();
        return new RecordResource($action->handle($r->user(), $c, $recordType, $r->validated(), $record));
    }
    public function destroy(RecordRequest $r, string $child, string $recordType, string $recordId)
    {
        $c = $this->child($r, $child);
        [$class] = RecordTypes::get($recordType);
        DB::transaction(function () use ($r, $class, $c, $recordId, $recordType) {
            $c = Child::whereKey($c->id)->lockForUpdate()->firstOrFail();
            abort_if($c->archived_at !== null, 409, 'Restore this child before changing records.');
            $record = $class::where('child_id', $c->id)->whereKey($recordId)->lockForUpdate()->firstOrFail();
            abort_if($record->version !== $r->integer('version'), 409, 'This record was changed. Reload before removing it.');
            $before = $record->attributesToArray();
            if ($recordType === 'medications') {
                abort_if($record->intakes()->exists(), 409, 'This medication has intake history. Archive it to preserve those entries.');
            }
            if ($recordType === 'appointments') {
                $deliveries = $record->deliveries()->lockForUpdate()->pluck('id');
                DB::table('notifications')->whereIn('reminder_delivery_id', $deliveries)->delete();
                $record->deliveries()->delete();
            }
            $record->delete();
            app(WriteAudit::class)->handle($r->user()->family, $r->user()->id, $record, 'removed', $before);
        });
        return response()->noContent();
    }
    private function child(Request $r, string $id): Child
    {
        $c = $r->user()->family->children()->whereKey($id)->firstOrFail();
        Gate::authorize('view', $c);
        return $c;
    }
}
