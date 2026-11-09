<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\ProcessReminders;
use App\Http\Controllers\Controller;
use App\Models\InAppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $r)
    {
        $r->validate(['per_page' => ['sometimes','integer','between:1,100']]);
        return response()->json(InAppNotification::where('user_id', $r->user()->id)->orderByDesc('created_at')->orderByDesc('id')->paginate($r->integer('per_page', 20)));
    }
    public function update(Request $r, string $notification)
    {
        $r->validate(['read' => ['required','accepted']]);
        $n = InAppNotification::where('user_id', $r->user()->id)->whereKey($notification)->firstOrFail();
        $n->update(['read_at' => $n->read_at ?? now()]);
        return response()->json(['data' => $n]);
    }
    public function refresh(Request $r, ProcessReminders $action)
    {
        return response()->json(['data' => $action->handle($r->user()->id, microtime(true) + 5)]);
    }
}
