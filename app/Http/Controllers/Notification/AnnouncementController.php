<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Requests\Notification\StoreAnnouncementRequest;
use App\Http\Requests\Notification\UpdateAnnouncementRequest;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    /**
     * NOT-012: Get announcements
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::query()
            ->with('creator:id,name,email');

        // Only show active announcements for non-admin users
        if (!$request->user()->hasRole('ADMIN')) {
            $query->active()
                ->forUser($request->user())
                ->notDismissedBy($request->user()->id);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by draft status
        if ($request->has('is_draft')) {
            $isPublished = filter_var($request->is_draft, FILTER_VALIDATE_BOOLEAN);
            $query->where('is_draft', $isPublished);
        }

        // Search in title and content
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ILIKE', "%{$search}%")
                    ->orWhere('content', 'ILIKE', "%{$search}%");
            });
        }

        $query->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc');

        $announcements = $query->paginate($request->get('per_page', 10));

        return AnnouncementResource::collection($announcements);
    }

    /**
     * NOT-011: Create announcement (admin only)
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $announcement = Announcement::create([
            'creator_id' => $request->user()->id,
            'title' => $request->title,
            'content' => $request->content,
            'priority' => $request->priority ?? 'medium',
            'target_audience' => $request->target_audience,
            'publish_at' => $request->publish_at,
            'expire_at' => $request->expire_at,
            'is_draft' => $request->is_draft ?? true,
        ]);

        return response()->json([
            'message' => 'Annonce créée avec succès',
            'announcement' => new AnnouncementResource($announcement->load('creator')),
        ], 201);
    }

    /**
     * Get single announcement
     */
    public function show(string $id): JsonResponse
    {
        $announcement = Announcement::with('creator')->findOrFail($id);

        return response()->json([
            'announcement' => new AnnouncementResource($announcement),
        ]);
    }

    /**
     * Update announcement (admin only)
     */
    public function update(UpdateAnnouncementRequest $request, string $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);

        $announcement->update($request->validated());

        return response()->json([
            'message' => 'Annonce mise à jour',
            'announcement' => new AnnouncementResource($announcement->fresh('creator')),
        ]);
    }

    /**
     * Delete announcement (admin only)
     */
    public function destroy(string $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->delete();

        return response()->json([
            'message' => 'Annonce supprimée',
        ]);
    }

    /**
     * Dismiss announcement for current user
     */
    public function dismiss(string $id, Request $request): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->dismissFor($request->user()->id);

        return response()->json([
            'message' => 'Annonce masquée',
        ]);
    }

    /**
     * Publish announcement (admin only)
     */
    public function publish(string $id): JsonResponse
    {
        $announcement = Announcement::findOrFail($id);
        $announcement->publish();

        return response()->json([
            'message' => 'Annonce publiée',
            'announcement' => new AnnouncementResource($announcement->fresh()),
        ]);
    }
}