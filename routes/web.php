<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;

Route::get('/', function () {
  return view('welcome');
});

// IP確認用（デバッグ）
Route::get('/check-ip', function (Illuminate\Http\Request $request) {
  return response()->json([
    'ip' => $request->ip(),
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'unknown',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'http_x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown',
    'user_agent' => $request->userAgent(),
  ]);
});



// Admin Routes
Route::prefix('admin')->name('admin.')->group(function () {
  // Admin Authentication Routes
  Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
  Route::post('login', [AdminAuthController::class, 'login']);
  Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

  // Admin Protected Routes
  Route::middleware(['admin'])->group(function () {
    Route::get('dashboard', [AdminDashboardController::class, 'dashboard'])->name('dashboard');
    Route::get('users', [AdminDashboardController::class, 'users'])->name('users');

    // User Management Routes
    Route::get('users/{id}', [AdminDashboardController::class, 'showUser'])->name('users.show');
    Route::get('users/{id}/edit', [AdminDashboardController::class, 'editUser'])->name('users.edit');
    Route::put('users/{id}', [AdminDashboardController::class, 'updateUser'])->name('users.update');
    Route::delete('users/{id}', [AdminDashboardController::class, 'deleteUser'])->name('users.delete');
    Route::post('users/{id}/restore', [AdminDashboardController::class, 'restoreUser'])->name('users.restore');
    Route::post('users/{id}/toggle-re-registration', [AdminDashboardController::class, 'toggleReRegistration'])->name('users.toggle-re-registration');

    // User Conversations Management
    Route::get('users/{id}/conversations', [AdminDashboardController::class, 'userConversations'])->name('users.conversations');
    Route::get('users/{userId}/conversations/{conversationId}', [AdminDashboardController::class, 'userConversationDetail'])->name('users.conversations.detail');
    Route::delete('users/{userId}/conversations/{conversationId}', [AdminDashboardController::class, 'deleteConversation'])->name('users.conversations.delete');
    Route::post('users/{userId}/conversations/{conversationId}/restore', [AdminDashboardController::class, 'restoreConversation'])->name('users.conversations.restore');

    // Conversation Management Routes (全体のトークルーム管理)
    Route::get('conversations', [AdminDashboardController::class, 'conversations'])->name('conversations');
    Route::get('conversations/{id}', [AdminDashboardController::class, 'conversationDetail'])->name('conversations.detail');
    Route::delete('conversations/{id}', [AdminDashboardController::class, 'deleteConversationDirect'])->name('conversations.delete');
    Route::post('conversations/{id}/restore', [AdminDashboardController::class, 'restoreConversationDirect'])->name('conversations.restore');

    // Message Management
    Route::put('users/{userId}/conversations/{conversationId}/messages/{messageId}', [AdminDashboardController::class, 'updateMessage'])->name('users.messages.update');
    Route::delete('users/{userId}/conversations/{conversationId}/messages/{messageId}', [AdminDashboardController::class, 'deleteMessage'])->name('users.messages.delete');

    // Support Management Routes
    Route::get('support', [AdminDashboardController::class, 'supportConversations'])->name('support');
    Route::get('support/{conversationId}', [AdminDashboardController::class, 'supportConversationDetail'])->name('support.detail');
    Route::post('support/{conversationId}/reply', [AdminDashboardController::class, 'replyToSupport'])->name('support.reply');

    // AJAX Routes for support
    Route::get('api/support/unread-count', [AdminDashboardController::class, 'getUnreadSupportCount'])->name('support.unread-count');
    Route::post('api/support/{conversationId}/mark-read', [AdminDashboardController::class, 'markSupportAsRead'])->name('support.mark-read');

    // Friendship Management Routes
    Route::get('friendships', [AdminDashboardController::class, 'friendships'])->name('friendships');
    Route::get('friendships/{id}', [AdminDashboardController::class, 'showFriendship'])->name('friendships.show');
    Route::delete('friendships/{id}', [AdminDashboardController::class, 'deleteFriendship'])->name('friendships.delete');
    Route::post('friendships/{id}/restore', [AdminDashboardController::class, 'restoreFriendship'])->name('friendships.restore');

    // Group Management Routes
    Route::get('groups', [AdminDashboardController::class, 'groups'])->name('groups');
    Route::get('groups/{id}', [AdminDashboardController::class, 'showGroup'])->name('groups.show');
    Route::get('groups/{id}/edit', [AdminDashboardController::class, 'editGroup'])->name('groups.edit');
    Route::put('groups/{id}', [AdminDashboardController::class, 'updateGroup'])->name('groups.update');
    Route::post('groups/{groupId}/members', [AdminDashboardController::class, 'addMember'])->name('groups.members.add');
    Route::delete('groups/{groupId}/members/{memberId}', [AdminDashboardController::class, 'removeMember'])->name('groups.members.remove');
    Route::put('groups/{groupId}/members/{memberId}/role', [AdminDashboardController::class, 'updateMemberRole'])->name('groups.members.role');
    Route::patch('groups/{groupId}/members/{memberId}/rejoin', [AdminDashboardController::class, 'toggleMemberRejoin'])->name('groups.members.rejoin');
    Route::post('groups/{groupId}/members/{memberId}/restore', [AdminDashboardController::class, 'restoreMember'])->name('groups.members.restore');

    // Super Admin Only Routes
    Route::get('admins', [AdminDashboardController::class, 'admins'])->name('admins');
    Route::post('admins', [AdminDashboardController::class, 'createAdmin'])->name('admins.create')->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
    Route::get('admins/{id}/edit', [AdminDashboardController::class, 'editAdmin'])->name('admins.edit');
    Route::put('admins/{id}', [AdminDashboardController::class, 'updateAdmin'])->name('admins.update');
    Route::delete('admins/{id}', [AdminDashboardController::class, 'deleteAdmin'])->name('admins.delete');
  });
});
