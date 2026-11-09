<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\WriteAudit;
use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class FamilyController extends Controller
{
    public function me(Request $r): UserResource { return new UserResource($r->user()); }
    public function updateMe(Request $r): UserResource
    {
        $data = $r->validate(['name' => ['sometimes','required','string','max:100'], 'email_reminders_enabled' => ['sometimes','boolean']]);
        abort_if($r->user()->demoAccount && ($data['email_reminders_enabled'] ?? false), 403, 'Email reminders are disabled for demo accounts.');
        $r->user()->update($data);
        return new UserResource($r->user());
    }
    public function show(Request $r) { return response()->json(['data' => $r->user()->family->only(['id','name','timezone','version'])]); }
    public function update(Request $r, WriteAudit $audit)
    {
        $data = $r->validate(['name' => ['required','string','max:100'], 'timezone' => ['required','timezone:all'], 'version' => ['required','integer','min:1']]);
        $family = DB::transaction(function () use ($r, $data, $audit) {
            $f = $r->user()->family()->lockForUpdate()->firstOrFail();
            abort_if($f->version !== $data['version'], 409, 'This family was changed. Reload before saving.');
            $before = $f->attributesToArray();
            $f->update(['name' => $data['name'], 'timezone' => $data['timezone'], 'version' => $f->version + 1]);
            $audit->handle($f, $r->user()->id, $f, 'updated', $before);
            return $f;
        });
        return response()->json(['data' => $family->only(['id','name','timezone','version'])]);
    }
    public function confirmPassword(Request $r)
    {
        $r->validate(['password' => ['required','string']]);
        abort_unless(Hash::check($r->input('password'), $r->user()->password), 422, 'The password is incorrect.');
        $r->session()->put('auth.password_confirmed_at', time());
        return response()->noContent();
    }
}
