<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\SendNotificationRequest;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\Notification\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class NotificationController extends Controller
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * NOT-008: Get user notifications
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Notification::query()
            ->where('user_id', $request->user()->id)
            ->orderBy('created_at', 'desc');

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Filter by read/unread
        if ($request->has('is_read')) {
            $isRead = filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_read', $isRead);
        }

        // Search in title and message
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('message', 'ILIKE', "%{$search}%");
            });
        }

        $notifications = $query->paginate($request->get('per_page', 15));

        return NotificationResource::collection($notifications);
    }

    /**
     * NOT-007: Send notification (admin only)
     */
    public function send(SendNotificationRequest $request): JsonResponse
    {
        $notifications = $this->notificationService->send(
            userIds: $request->recipient_ids,
            title: $request->title,
            message: $request->message,
            type: $request->type,
            channels: $request->channels ?? ['in_app'],
            metadata: $request->metadata ?? []
        );

        return response()->json([
            'message' => 'Notifications envoyées avec succès',
            'count' => $notifications->count(),
        ], 201);
    }

    /**
     * NOT-009: Mark notification as read
     */
    public function markAsRead(string $id, Request $request): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marquée comme lue',
            'notification' => new NotificationResource($notification->fresh()),
        ]);
    }

    /**
     * NOT-010: Mark all as read
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead($request->user()->id);

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues',
            'count' => $count,
        ]);
    }

    /**
     * Get unread count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = $this->notificationService->getUnreadCount($request->user()->id);

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * Delete notification
     */
    public function destroy(string $id, Request $request): JsonResponse
    {
        $notification = Notification::where('id', $id)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $notification->delete();

        return response()->json([
            'message' => 'Notification supprimée',
        ]);
    }
}