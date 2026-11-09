<?php

namespace App\Actions\Privacy;

use App\Models\DeletionRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class WriteDeletionLedger
{
    public function handle(DeletionRequest $request): void
    {
        $payload = Crypt::encryptString(json_encode([
            'family_id' => $request->family_id, 'owner_user_id' => $request->owner_user_id,
            'requested_at' => $request->requested_at->toIso8601String(), 'retain_until' => $request->retain_until->toIso8601String(),
        ], JSON_THROW_ON_ERROR));
        if (app()->environment(['local','testing'])) {
            Storage::disk('local')->put('deletion-ledger/'.$request->family_id.'.enc', $payload);
            return;
        }
        $url = config('tracker.supabase_url');
        $key = config('tracker.supabase_storage_key');
        if (!$url || !$key) throw new RuntimeException('external_ledger_unconfigured');
        $response = Http::withToken($key)->withHeaders(['apikey' => $key, 'x-upsert' => 'true'])
            ->withBody($payload, 'application/octet-stream')->timeout(3)
            ->post(rtrim($url, '/').'/storage/v1/object/deletion-ledger/'.$request->family_id.'.enc');
        if (!$response->successful()) throw new RuntimeException('external_ledger_unavailable');
    }
}
