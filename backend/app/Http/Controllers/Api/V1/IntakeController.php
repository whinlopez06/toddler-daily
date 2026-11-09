<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Medications\RecordIntake;
use App\Http\Controllers\Controller;
use App\Http\Requests\IntakeRequest;
use App\Http\Resources\RecordResource;
use App\Models\Medication;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    public function index(Request $r, string $child, string $medication)
    {
        $r->validate(['per_page' => ['sometimes','integer','between:1,100']]);
        $med = $this->medication($r, $child, $medication);
        return RecordResource::collection($med->intakes()->with('replacement')->orderByDesc('created_at')->orderByDesc('id')->paginate($r->integer('per_page', 20)));
    }
    public function show(Request $r, string $child, string $medication, string $intake): RecordResource
    {
        return new RecordResource($this->medication($r, $child, $medication)->intakes()->with('replacement')->whereKey($intake)->firstOrFail());
    }
    public function store(IntakeRequest $r, string $child, string $medication, RecordIntake $action)
    {
        return (new RecordResource($action->handle($r->user(), $this->medication($r, $child, $medication), $r->validated())))->response()->setStatusCode(201);
    }
    public function correct(IntakeRequest $r, string $child, string $medication, string $intake, RecordIntake $action)
    {
        return (new RecordResource($action->handle($r->user(), $this->medication($r, $child, $medication), $r->validated(), $intake)))->response()->setStatusCode(201);
    }
    private function medication(Request $r, string $child, string $id): Medication
    {
        $c = $r->user()->family->children()->whereKey($child)->firstOrFail();
        return Medication::where('child_id', $c->id)->whereKey($id)->firstOrFail();
    }
}
