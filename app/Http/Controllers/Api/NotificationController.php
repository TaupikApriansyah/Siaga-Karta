<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['per_page' => 'nullable|integer|min:5|max:50']);
        $userId = $request->attributes->get('api_user')->id;
        $perPage = (int)($data['per_page'] ?? 20);
        $page = AppNotification::query()
            ->where('user_id', $userId)
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'data' => $page->items(),
            'current_page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
            'unread' => AppNotification::where('user_id', $userId)->whereNull('read_at')->count(),
        ]);
    }

    public function read(Request $request, AppNotification $notification)
    {
        $userId = $request->attributes->get('api_user')->id;
        abort_unless((int)$notification->user_id === (int)$userId, 404);
        if (!$notification->read_at) $notification->forceFill(['read_at' => now()])->save();
        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    public function readAll(Request $request)
    {
        $userId = $request->attributes->get('api_user')->id;
        AppNotification::where('user_id', $userId)->whereNull('read_at')->update(['read_at' => now(), 'updated_at' => now()]);
        return response()->json(['message' => 'Semua notifikasi sudah dibaca.']);
    }
}
