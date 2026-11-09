<?php

namespace App\Http\Controllers;

use App\Models\DemoAccount;
use App\Models\Family;
use App\Models\TimelineEntry;
use App\Models\User;
use App\Support\DemoEnvironment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class DemoController extends Controller
{
    public function options(DemoEnvironment $environment): JsonResponse
    {
        return response()->json(['data' => ['demo_mode' => false, 'available' => $environment->ready()]]);
    }

    public function store(Request $request, DemoEnvironment $environment): JsonResponse
    {
        $environment->requireReady();
        abort_if($request->user(), 409, 'Sign out before starting a new demo.');
        if (is_string($request->input('email'))) {
            $request->merge(['email' => mb_strtolower(trim($request->input('email')))]);
        }
        $data = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'max:128', Password::min(12), 'confirmed'],
            'privacy_accepted' => ['accepted'],
        ]);
        $user = DB::transaction(function () use ($data) {
            DB::select('select pg_advisory_xact_lock(74103001)');
            if (User::where('email', $data['email'])->exists()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'This email address already has an account.']);
            }
            abort_if(DemoAccount::count() >= config('demo.total_accounts') ||
                DemoAccount::where('started_at', '>=', now()->startOfDay())->count() >= config('demo.daily_accounts'),
                429, 'Demo registration is at capacity. Please try another day.');
            $user = User::create(['name' => 'Demo visitor', 'email' => $data['email'], 'password' => $data['password'],
                'privacy_notice_version' => 'demo-2026-11-03', 'privacy_accepted_at' => now(), 'email_reminders_enabled' => false]);
            $demo = DemoAccount::create(['user_id' => $user->id, 'started_at' => now(), 'expires_at' => now()->addDay()]);
            $family = Family::create(['owner_user_id' => $user->id, 'name' => 'My demo family', 'timezone' => 'Asia/Manila']);
            $child = $family->children()->create(['display_name' => 'Sample child', 'birth_date' => now()->subYears(2)->toDateString()]);
            TimelineEntry::create(['child_id' => $child->id, 'created_by' => $user->id, 'kind' => 'memory',
                'title' => 'A sample afternoon in the park', 'entry_date' => now()->toDateString(),
                'description' => 'Fictional sample record. Try editing this memory or adding your own sample entry.']);
            $demo->recordEvent('registered');

            return $user;
        });
        Auth::guard('web')->login($user->fresh(), false);
        $request->session()->regenerate();

        return response()->json(['message' => 'Your private 24-hour demo is ready.'], 201);
    }
}
