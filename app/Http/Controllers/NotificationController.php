<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Get paginated notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $notifications = $this->notificationService->getNotifications(
            $request->user(),
            'user',
            min($perPage, 100),
        );

        $items = collect($notifications->items())->map(fn ($n) => [
            'id' => $n->id,
            'type' => $n->type,
            'data' => $n->data,
            'read_at' => $n->read_at,
            'created_at' => $n->created_at,
            'read' => $n->read_at !== null,
        ]);

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /**
     * Get the unread notification count for the authenticated user.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user(), 'user');

        return response()->json(['count' => $count]);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $updated = $this->notificationService->markAsRead($id, 'user', $request->user());

        if (! $updated) {
            return response()->json(['message' => 'Notification not found'], 404);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Mark all unread notifications as read for the authenticated user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user(), 'user');

        return response()->json(['success' => true, 'count' => $count]);
    }
}
