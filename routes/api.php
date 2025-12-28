<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserRoleController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\AnnouncementController;
Route::middleware('auth:api')->group(function () {
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
            'message' => 'Operation successful',
            'meta' => null,
        ]);
    });
    Route::get('/protected-resource', function () {
        return response()->json([
            'success' => true,
            'data' => ['message' => 'This is a protected resource accessible only to authenticated Keycloak users.'],
            'message' => 'Operation successful',
            'meta' => null,
        ]);
    });

    Route::apiResource('faculties', \App\Http\Controllers\Academic\FacultyController::class);
    Route::apiResource('departments', \App\Http\Controllers\Academic\DepartmentController::class);
    Route::apiResource('academic-programs', \App\Http\Controllers\Academic\AcademicProgramController::class);
    Route::apiResource('course-units', \App\Http\Controllers\Academic\CourseUnitController::class);
    Route::apiResource('courses', \App\Http\Controllers\Academic\CourseController::class);

    Route::get('roles', [RoleController::class, 'index']);
    Route::post('roles', [RoleController::class, 'store']);
    Route::put('roles/{role}', [RoleController::class, 'update']);
    Route::put('users/{user}/roles', [UserRoleController::class, 'update']);
   // Notifications
    Route::get('notifications', [NotificationController::class, 'index']); // NOT-008
    Route::post('notifications', [NotificationController::class, 'store']); // NOT-007
    Route::put('notifications/{id}/read', [NotificationController::class, 'markAsRead']); // NOT-009
    Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']); // NOT-010
    Route::get('announcements', [AnnouncementController::class, 'index']);
    Route::post('announcements', [AnnouncementController::class, 'store']);
});