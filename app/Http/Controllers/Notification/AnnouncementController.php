<?php

namespace App\Http\Controllers\Notification;

use App\Models\Announcement;
use App\Events\AnnouncementPublished;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;

class AnnouncementController extends Controller
{
    // Lister les annonces (NOT-012)
    public function index()
    {
        return Announcement::with('creator')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    // Créer une annonce (NOT-011)
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'in:low,medium,high',
            'expires_at' => 'nullable|date',
        ]);

        $announcement = Announcement::create([
            'id' => (string) Str::uuid(), // Utilisation de l'UUID
            'creator_id' => Auth::id(),
            'title' => $validated['title'],
            'content' => $validated['content'],
            'priority' => $validated['priority'] ?? 'medium',
            'expires_at' => $validated['expires_at'],
        ]);

        // Déclenche l'événement temps réel
        event(new AnnouncementPublished($announcement));

        return response()->json($announcement, 201);
    }
}