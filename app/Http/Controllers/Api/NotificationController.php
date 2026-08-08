<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Notifikasi in-app (lonceng di navbar) — dibaca dari tabel `notifications`
 * bawaan Laravel, sumber yang sama dengan yang dikirim lewat web push.
 */
class NotificationController extends Controller
{
    private const LIMIT = 20;

    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()
                ->latest()
                ->take(self::LIMIT)
                ->get()
                ->map(fn ($notification) => [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? null,
                    'message' => $notification->data['message'] ?? '',
                    'url' => $notification->data['url'] ?? '/',
                    'read' => $notification->read_at !== null,
                    'created_at' => $notification->created_at->toIso8601String(),
                ]),
            'unread_count' => $user->unreadNotifications()->count(),
        ]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->noContent();
    }
}
