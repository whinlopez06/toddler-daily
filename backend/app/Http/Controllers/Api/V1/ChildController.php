<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WriteAudit;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChildRequest;
use App\Http\Resources\ChildResource;
use App\Models\Child;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ChildController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['archived' => ['sometimes','in:true,false'], 'per_page' => ['sometimes','integer','between:1,100']]);
        $query = $r->user()->family->children();
        if (!$r->boolean('archived')) $query->whereNull('archived_at');
        return ChildResource::collection($query->orderBy('display_name')->orderBy('id')->paginate($r->integer('per_page', 20)));
    }
    public function show(Request $r, string $child): ChildResource { return new ChildResource($this->resolve($r, $child)); }
    public function store(ChildRequest $r, WriteAudit $audit)
    {
        $child = DB::transaction(function () use ($r, $audit) {
            $demo = \App\Support\DemoQuota::lock($r->user());
            abort_if($demo && $r->user()->family->children()->count() >= config('demo.children'), 409, 'This demo can contain up to three child profiles, including archived profiles.');
            $c = $r->user()->family->children()->create($r->safe()->only(['display_name','birth_date','first_name','middle_name','last_name','suffix','gender']));
            $audit->handle($r->user()->family, $r->user()->id, $c, 'created');
            return $c;
        });
        return (new ChildResource($child))->response()->setStatusCode(201);
    }
    public function update(ChildRequest $r, string $child, WriteAudit $audit): ChildResource
    {
        $c = $this->resolve($r, $child);
        return new ChildResource(DB::transaction(function () use ($r, $c, $audit) {
            $c = Child::whereKey($c->id)->lockForUpdate()->firstOrFail();
            abort_if($c->version !== $r->integer('version'), 409, 'This child was changed. Reload before saving.');
            $before = $c->attributesToArray();
            $data = $r->safe()->only(['display_name','birth_date','first_name','middle_name','last_name','suffix','gender']);
            if ($r->has('archived')) $data['archived_at'] = $r->boolean('archived') ? now() : null;
            $c->update([...$data, 'version' => $c->version + 1]);
            if ($c->archived_at) {
                DB::table('reminder_deliveries')->whereIn('appointment_id', DB::table('appointments')->where('child_id', $c->id)->select('id'))->whereIn('status', ['pending','processing'])->update(['status' => 'cancelled', 'updated_at' => now()]);
            }
            $audit->handle($r->user()->family, $r->user()->id, $c, 'updated', $before);
            return $c;
        }));
    }
    public function destroy(Request $r, string $child, WriteAudit $audit)
    {
        $r->validate(['version' => ['required', 'integer', 'min:1']]);
        DB::transaction(function () use ($r, $child, $audit) {
            $profile = $r->user()->family->children()->whereKey($child)->lockForUpdate()->firstOrFail();
            Gate::authorize('update', $profile);
            abort_if($profile->version !== $r->integer('version'), 409, 'This profile changed. Reload before deleting it.');
            foreach (['timeline_entries', 'growth_measurements', 'vaccination_records'] as $table) {
                $rows = DB::table($table)->where('child_id', $profile->id);
                abort_if((clone $rows)->whereNull('deleted_at')->exists(), 409, 'Delete the child records first, or archive the profile to keep its history.');
                $rows->whereNotNull('deleted_at')->delete();
            }
            foreach (['appointments', 'medications'] as $table) {
                abort_if(DB::table($table)->where('child_id', $profile->id)->exists(), 409, 'Delete the child records first, or archive the profile to keep its history.');
            }
            $audit->handle($r->user()->family, $r->user()->id, $profile, 'removed', $profile->attributesToArray());
            $profile->delete();
        });
        return response()->noContent();
    }

    private function resolve(Request $r, string $id): Child
    {
        $child = $r->user()->family->children()->whereKey($id)->firstOrFail();
        Gate::authorize('view', $child);
        return $child;
    }
}
