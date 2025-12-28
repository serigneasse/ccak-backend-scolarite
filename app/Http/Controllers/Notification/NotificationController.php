<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Notification; // Importation du modèle indispensable

class NotificationController extends Controller
{
    /**
     * Liste des notifications de l'utilisateur connecté (NOT-008)
     */
    public function index()
    {
        return Notification::where('user_id', Auth::id())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Création d'une nouvelle notification (NOT-007)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required', // UUID du destinataire
            'title'   => 'required|string|max:255',
            'message' => 'required|string',
            'type'    => 'required|string|in:info,warning,success,error',
        ]);

        $notification = Notification::create([
            'user_id' => $validated['user_id'],
            'title'   => $validated['title'],
            'message' => $validated['message'],
            'type'    => $validated['type'],
        ]);

        return response()->json($notification, 201);
    }

    /**
     * Marquer une notification comme lue (NOT-009)
     */
    public function markAsRead($id)
    {
        // On sécurise pour que l'utilisateur ne puisse marquer que SES notifications
        $notification = Notification::where('user_id', Auth::id())->findOrFail($id);
        
        $notification->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue'
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues (NOT-010)
     */
    public function markAllAsRead()
    {
        Notification::where('user_id', Auth::id())
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues'
        ]);
    }
}