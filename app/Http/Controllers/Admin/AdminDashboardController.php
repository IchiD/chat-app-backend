<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\User;
use App\Models\Conversation;
use App\Models\ChatRoom;
use App\Models\Group;
use App\Models\Message;
use App\Models\Friendship;
use App\Models\OperationLog;
use App\Models\Participant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AdminDashboardController extends Controller
{
  /**
   * 管理画面ダッシュボード
   */
  public function dashboard()
  {
    $admin = Auth::guard('admin')->user();

    // 統計情報
    $userCount = User::count();
    $adminCount = Admin::count();

    // チャットルーム数（新構造）
    $chatRoomCount = ChatRoom::count();

    // 本日送信されたメッセージ数
    $todayMessagesCount = Message::whereDate('sent_at', date('Y-m-d'))->count();

    // 本日のアクティブユーザー数（本日メッセージを送信したユーザー）
    $todayActiveUsersCount = Message::whereDate('sent_at', date('Y-m-d'))
      ->distinct('sender_id')
      ->count('sender_id');

    // システム状態チェック
    $systemStatus = $this->checkSystemStatus();

    $frontendLogs = \App\Models\OperationLog::where('category', 'frontend')
      ->orderBy('created_at', 'desc')
      ->take(50)
      ->get();
    $backendLogs = \App\Models\OperationLog::where('category', 'backend')
      ->orderBy('created_at', 'desc')
      ->take(50)
      ->get();

    return view('admin.dashboard', compact(
      'admin',
      'userCount',
      'adminCount',
      'chatRoomCount',
      'todayMessagesCount',
      'todayActiveUsersCount',
      'systemStatus',
      'frontendLogs',
      'backendLogs'
    ));
  }

  /**
   * ユーザー管理画面
   */
  public function users(Request $request)
  {
    $admin = Auth::guard('admin')->user();

    // クエリビルダーを初期化（削除されたユーザーも含む）
    $query = User::withTrashed();

    // 検索機能
    if ($search = $request->get('search')) {
      $query->where(function ($q) use ($search) {
        $q->where('name', 'LIKE', '%' . $search . '%')
          ->orWhere('email', 'LIKE', '%' . $search . '%')
          ->orWhere('friend_id', 'LIKE', '%' . $search . '%');
      });
    }

    // 認証状態フィルター
    if ($status = $request->get('status')) {
      if ($status === 'verified') {
        $query->where('is_verified', true);
      } elseif ($status === 'unverified') {
        $query->where('is_verified', false);
      } elseif ($status === 'deleted') {
        $query->whereNotNull('deleted_at');
      } elseif ($status === 'banned') {
        $query->where('is_banned', true);
      }
    }

    // 並び順
    $sort = $request->get('sort', 'created_at_desc');
    switch ($sort) {
      case 'created_at_asc':
        $query->orderBy('created_at', 'asc');
        break;
      case 'name_asc':
        $query->orderBy('name', 'asc');
        break;
      case 'name_desc':
        $query->orderBy('name', 'desc');
        break;
      case 'created_at_desc':
      default:
        $query->orderBy('created_at', 'desc');
        break;
    }

    // ページネーション実行（削除されたユーザーも含む）
    $users = $query->with('deletedByAdmin')->paginate(20);

    // 検索パラメータをページネーションに追加
    $users->appends($request->query());

    return view('admin.users.index', compact('admin', 'users'));
  }

  /**
   * ユーザー詳細表示
   */
  public function showUser($id)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->with(['deletedByAdmin'])
      ->findOrFail($id);

    // ユーザーの統計情報（新構造に対応）
    $stats = [
      'total_chat_rooms' => $user->getChatRooms()->count(),
      'total_messages' => $user->messages()->count(),
      'friends_count' => $user->friends()->count(),
      'last_login' => null, // TODO: ログイン履歴があれば追加
    ];

    // ユーザーが参加しているチャットルームを取得（最新5件、論理削除されたものも含む）
    $chatRooms = ChatRoom::withTrashed()
      ->where('type', '!=', 'support_chat')
      ->where(function ($query) use ($user) {
        // 直接参加者として登録されているチャットルーム
        $query->where('participant1_id', $user->id)
          ->orWhere('participant2_id', $user->id)
          // グループメンバーとして参加しているチャットルーム
          ->orWhereHas('group.activeMembers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
          });
      })
      ->with([
        'latestMessage.sender',
        'latestMessage.chatRoom.group.groupMembers',
        'group',
        'participant1',
        'participant2',
        'deletedByAdmin' // 削除を実行した管理者情報も取得
      ])
      ->orderBy('updated_at', 'desc')
      ->take(5)
      ->get();

    // メッセージを別途ロード（新旧構造混在に対応）
    $user->load(['messages' => function ($query) {
      $query->orderBy('sent_at', 'desc')->take(10);
    }]);

    return view('admin.users.show', compact('admin', 'user', 'stats', 'chatRooms'));
  }

  /**
   * ユーザー編集画面
   */
  public function editUser($id)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->findOrFail($id);

    return view('admin.users.edit', compact('admin', 'user'));
  }

  /**
   * ユーザー情報更新
   */
  public function updateUser(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->findOrFail($id);

    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
      'is_verified' => 'boolean',
      'friend_id' => 'required|string|size:6|unique:users,friend_id,' . $user->id,
      'plan' => 'required|in:free,standard,premium',
    ]);

    $user->update([
      'name' => $request->name,
      'email' => $request->email,
      'is_verified' => $request->boolean('is_verified'),
      'friend_id' => strtoupper($request->friend_id),
      'plan' => $request->plan,
    ]);
    \App\Services\OperationLogService::log('backend', 'update_user', 'admin:' . $admin->id . ' user:' . $user->id);

    return redirect()->route('admin.users.show', $user->id)
      ->with('success', 'ユーザー情報を更新しました。');
  }

  /**
   * ユーザー削除（論理削除）
   */
  public function deleteUser(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->findOrFail($id);

    if ($user->isDeleted()) {
      return redirect()->back()->with('error', 'このユーザーは既に削除されています。');
    }

    $request->validate([
      'reason' => 'nullable|string|max:500',
    ]);

    $user->deleteByAdmin($admin->id, $request->reason ?? '管理者による削除');
    \App\Services\OperationLogService::log('backend', 'delete_user', 'admin:' . $admin->id . ' user:' . $user->id);

    return redirect()->route('admin.users')
      ->with('success', "ユーザー（ID: {$user->id}、{$user->name}）を削除しました。");
  }

  /**
   * ユーザー削除の取り消し
   */
  public function restoreUser($id)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->findOrFail($id);

    if (!$user->isDeleted()) {
      return redirect()->back()->with('error', 'このユーザーは削除されていません。');
    }

    try {
      $user->restoreByAdmin();
      \App\Services\OperationLogService::log('backend', 'restore_user', 'admin:' . $admin->id . ' user:' . $user->id);

      return redirect()->route('admin.users.show', $user->id)
        ->with('success', "ユーザー（ID: {$user->id}、{$user->name}）の削除を取り消しました。");
    } catch (\Exception $e) {
      return redirect()->back()
        ->with('error', $e->getMessage());
    }
  }

  /**
   * 再登録許可フラグの切り替え
   */
  public function toggleReRegistration(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->findOrFail($id);

    $newValue = !$user->allow_re_registration;
    $user->update(['allow_re_registration' => $newValue]);

    \App\Services\OperationLogService::log('backend', 'toggle_re_registration', 'admin:' . $admin->id . ' user:' . $user->id . ' value:' . ($newValue ? 'true' : 'false'));

    $message = $newValue
      ? "ユーザー（ID: {$user->id}、{$user->name}）の再登録を許可しました。"
      : "ユーザー（ID: {$user->id}、{$user->name}）の再登録を禁止しました。";

    return redirect()->back()->with('success', $message);
  }

  /**
   * ユーザーの会話一覧を表示
   */
  public function userConversations($id)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->findOrFail($id);

    // ユーザーが参加しているチャットルーム（サポートチャット以外、論理削除されたものも含む）
    $chatRooms = ChatRoom::withTrashed()
      ->where('type', '!=', 'support_chat')
      ->where(function ($query) use ($user) {
        // 直接参加者として登録されているチャットルーム
        $query->where('participant1_id', $user->id)
          ->orWhere('participant2_id', $user->id)
          // グループメンバーとして参加しているチャットルーム
          ->orWhereHas('group.activeMembers', function ($q) use ($user) {
            $q->where('user_id', $user->id);
          });
      })
      ->with([
        'latestMessage.sender',
        'latestMessage.chatRoom.group.groupMembers',
        'group.activeMembers.user',
        'group.owner',
        'participant1',
        'participant2',
        'messages',
        'deletedByAdmin' // 削除を実行した管理者情報も取得
      ])
      ->orderBy('updated_at', 'desc')
      ->paginate(10);

    return view('admin.users.conversations', compact('admin', 'user', 'chatRooms'));
  }

  /**
   * 会話詳細を表示
   */
  public function userConversationDetail($userId, $conversationId)
  {
    $admin = Auth::guard('admin')->user();
    $user = User::withTrashed()->findOrFail($userId);
    $chatRoom = ChatRoom::withTrashed()->with([
      'group.activeMembers.user', // グループメンバーとユーザー情報も読み込み
      'group.owner', // グループオーナー情報も読み込み
      'participant1',
      'participant2',
      'messages.sender',
      'messages.adminDeletedBy',
      'deletedByAdmin' // 削除を実行した管理者情報も取得
    ])->findOrFail($conversationId);

    $messages = $chatRoom->messages()
      ->with(['sender', 'adminDeletedBy', 'chatRoom.group.groupMembers'])
      ->orderBy('sent_at', 'desc')
      ->paginate(20);

    return view('admin.users.conversation-detail', compact('admin', 'user', 'chatRoom', 'messages'));
  }

  /**
   * 会話削除（論理削除）
   */
  public function deleteConversation(Request $request, $userId, $conversationId)
  {
    $admin = Auth::guard('admin')->user();
    $chatRoom = ChatRoom::findOrFail($conversationId);

    $request->validate([
      'reason' => 'nullable|string|max:500',
    ]);

    // チャットルームを論理削除
    $chatRoom->deleteByAdmin($admin->id, $request->reason ?? '管理者による削除');
    \App\Services\OperationLogService::log('backend', 'delete_chat_room', 'admin:' . $admin->id . ' chat_room:' . $chatRoom->id);

    return redirect()->route('admin.users.conversations', $userId)
      ->with('success', 'チャットルームを削除しました。');
  }

  /**
   * 会話削除の取り消し
   */
  public function restoreConversation($userId, $conversationId)
  {
    $admin = Auth::guard('admin')->user();
    $chatRoom = ChatRoom::withTrashed()->findOrFail($conversationId);

    if (!$chatRoom->isDeleted()) {
      return redirect()->back()->with('error', 'このチャットルームは削除されていません。');
    }

    $chatRoom->restoreByAdmin();
    \App\Services\OperationLogService::log('backend', 'restore_chat_room', 'admin:' . $admin->id . ' chat_room:' . $chatRoom->id);

    return redirect()->route('admin.users.conversations', $userId)
      ->with('success', 'チャットルームの削除を取り消しました。');
  }

  /**
   * 全トークルーム一覧表示・検索
   */
  public function conversations(Request $request)
  {
    $admin = Auth::guard('admin')->user();

    $search = $request->get('search');

    $query = ChatRoom::withTrashed()
      ->where('type', '!=', 'support_chat')
      ->withCount('messages')
      ->with([
        'group.activeMembers.user', // グループメンバーとユーザー情報も読み込み
        'group.owner', // グループオーナー情報も読み込み
        'participant1',
        'participant2',
        'latestMessage.sender',
        'latestMessage.chatRoom.group.groupMembers',
        'deletedByAdmin' // 削除を実行した管理者情報も取得
      ]);

    if ($search) {
      $searchType = $request->get('search_type', 'all');

      $query->where(function ($subQuery) use ($search, $searchType) {
        switch ($searchType) {
          case 'room_token':
            $subQuery->where('room_token', 'LIKE', '%' . $search . '%');
            break;
          case 'id':
            // 数値のみの場合のみIDで検索
            if (is_numeric($search)) {
              $subQuery->where('id', (int)$search);
            } else {
              // 数値でない場合は結果なしにする
              $subQuery->whereRaw('1 = 0');
            }
            break;
          case 'messages':
            $subQuery->whereHas('messages', function ($messageQuery) use ($search) {
              $messageQuery->where('text_content', 'LIKE', '%' . $search . '%')
                ->whereNull('deleted_at')
                ->whereNull('admin_deleted_at');
            });
            break;
          case 'participants':
            $subQuery->whereHas('participant1', function ($userQuery) use ($search) {
              $userQuery->where('name', 'LIKE', '%' . $search . '%');
            })->orWhereHas('participant2', function ($userQuery) use ($search) {
              $userQuery->where('name', 'LIKE', '%' . $search . '%');
            })->orWhereHas('group', function ($groupQuery) use ($search) {
              $groupQuery->where('name', 'LIKE', '%' . $search . '%');
            });
            break;
          case 'all':
          default:
            // IDは数値の場合のみ検索対象とする
            if (is_numeric($search)) {
              $subQuery->where('id', (int)$search);
            } else {
              $subQuery->whereRaw('1 = 0'); // 数値でない場合はID検索をスキップ
            }
            $subQuery
              ->orWhere('room_token', 'LIKE', '%' . $search . '%')
              ->orWhereHas('messages', function ($messageQuery) use ($search) {
                $messageQuery->where('text_content', 'LIKE', '%' . $search . '%')
                  ->whereNull('deleted_at')
                  ->whereNull('admin_deleted_at');
              })
              ->orWhereHas('participant1', function ($userQuery) use ($search) {
                $userQuery->where('name', 'LIKE', '%' . $search . '%');
              })
              ->orWhereHas('participant2', function ($userQuery) use ($search) {
                $userQuery->where('name', 'LIKE', '%' . $search . '%');
              })
              ->orWhereHas('group', function ($groupQuery) use ($search) {
                $groupQuery->where('name', 'LIKE', '%' . $search . '%');
              });
            break;
        }
      });
    }

    $chatRooms = $query->orderBy('updated_at', 'desc')->paginate(20);
    $chatRooms->appends($request->query());

    return view('admin.conversations.index', compact('admin', 'chatRooms'));
  }

  /**
   * トークルーム詳細表示
   */
  public function conversationDetail($id)
  {
    $admin = Auth::guard('admin')->user();
    $chatRoom = ChatRoom::withTrashed()->with([
      'group.activeMembers.user', // グループメンバーとユーザー情報も読み込み
      'group.owner', // グループオーナー情報も読み込み
      'participant1',
      'participant2',
      'messages.sender',
      'messages.adminDeletedBy',
      'deletedByAdmin' // 削除を実行した管理者情報も取得
    ])->findOrFail($id);

    $messages = $chatRoom->messages()
      ->with(['sender', 'adminDeletedBy', 'chatRoom.group.groupMembers'])
      ->orderBy('sent_at', 'desc')
      ->paginate(20);

    return view('admin.conversations.detail', compact('admin', 'chatRoom', 'messages'));
  }

  /**
   * トークルーム削除
   */
  public function deleteConversationDirect(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();
    $chatRoom = ChatRoom::findOrFail($id);

    $request->validate([
      'reason' => 'nullable|string|max:500',
    ]);

    // チャットルームを論理削除
    $chatRoom->deleteByAdmin($admin->id, $request->reason ?? '管理者による削除');
    \App\Services\OperationLogService::log('backend', 'delete_chat_room_admin', 'admin:' . $admin->id . ' chat_room:' . $chatRoom->id);

    return redirect()->route('admin.conversations')
      ->with('success', 'チャットルームを削除しました。');
  }

  /**
   * トークルーム削除の取り消し
   */
  public function restoreConversationDirect($id)
  {
    $admin = Auth::guard('admin')->user();
    $chatRoom = ChatRoom::withTrashed()->findOrFail($id);

    if (!$chatRoom->isDeleted()) {
      return redirect()->back()->with('error', 'このチャットルームは削除されていません。');
    }

    $chatRoom->restoreByAdmin();
    \App\Services\OperationLogService::log('backend', 'restore_chat_room_admin', 'admin:' . $admin->id . ' chat_room:' . $chatRoom->id);

    return redirect()->route('admin.conversations')
      ->with('success', 'チャットルームの削除を取り消しました。');
  }

  /**
   * メッセージ更新
   */
  public function updateMessage(Request $request, $userId, $conversationId, $messageId)
  {
    $admin = Auth::guard('admin')->user();
    $message = Message::findOrFail($messageId);

    $request->validate([
      'text_content' => 'required|string|max:1000',
    ]);

    $message->update([
      'text_content' => $request->text_content,
      'edited_at' => now(),
    ]);
    \App\Services\OperationLogService::log('backend', 'update_message', 'admin:' . $admin->id . ' message:' . $message->id);

    return redirect()->back()->with('success', 'メッセージを更新しました。');
  }

  /**
   * メッセージ削除（管理者による削除）
   */
  public function deleteMessage(Request $request, $userId, $conversationId, $messageId)
  {
    $admin = Auth::guard('admin')->user();
    $message = Message::findOrFail($messageId);

    if ($message->isAdminDeleted()) {
      return redirect()->back()->with('error', 'このメッセージは既に削除されています。');
    }

    $request->validate([
      'reason' => 'nullable|string|max:500',
    ]);

    $message->deleteByAdmin($admin->id, $request->reason ?? '管理者による削除');
    \App\Services\OperationLogService::log('backend', 'delete_message', 'admin:' . $admin->id . ' message:' . $message->id);

    return redirect()->back()->with('success', 'メッセージを削除しました。');
  }

  /**
   * アドミン管理画面（スーパーアドミンのみ）
   */
  public function admins()
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isSuperAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    $admins = Admin::paginate(20);

    return view('admin.admins.index', compact('admin', 'admins'));
  }

  /**
   * アドミン作成（スーパーアドミンのみ）
   */
  public function createAdmin(Request $request)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isSuperAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    $request->validate([
      'name' => 'required|string|max:255',
      'email' => 'required|string|email|max:255|unique:admins',
      'password' => 'required|string|min:8',
      'role' => 'required|in:admin,super_admin',
    ]);

    Admin::create([
      'name' => $request->name,
      'email' => $request->email,
      'password' => Hash::make($request->password),
      'role' => $request->role,
    ]);
    \App\Services\OperationLogService::log('backend', 'create_admin', 'admin:' . $admin->id . ' email:' . $request->email);

    return redirect()->route('admin.admins')->with('success', 'アドミンが作成されました。');
  }

  /**
   * アドミン編集画面（スーパーアドミンのみ）
   */
  public function editAdmin($id)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isSuperAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    // 自分自身の編集を禁止
    if ($admin->id == $id) {
      return redirect()->route('admin.admins')->with('error', '自分自身は編集できません。');
    }

    $editAdmin = Admin::findOrFail($id);

    return view('admin.admins.edit', compact('admin', 'editAdmin'));
  }

  /**
   * アドミン更新（スーパーアドミンのみ）
   */
  public function updateAdmin(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isSuperAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    // 自分自身の編集を禁止
    if ($admin->id == $id) {
      return redirect()->route('admin.admins')->with('error', '自分自身は編集できません。');
    }

    $editAdmin = Admin::findOrFail($id);

    $validationRules = [
      'name' => 'required|string|max:255',
      'email' => 'required|string|email|max:255|unique:admins,email,' . $id,
      'role' => 'required|in:admin,super_admin',
    ];

    // パスワードが入力された場合のみバリデーション
    if ($request->filled('password')) {
      $validationRules['password'] = 'string|min:8';
    }

    $request->validate($validationRules);

    $updateData = [
      'name' => $request->name,
      'email' => $request->email,
      'role' => $request->role,
    ];

    // パスワードが入力された場合のみ更新
    if ($request->filled('password')) {
      $updateData['password'] = Hash::make($request->password);
    }

    $editAdmin->update($updateData);

    \App\Services\OperationLogService::log('backend', 'update_admin', 'admin:' . $admin->id . ' target:' . $editAdmin->id . ' email:' . $editAdmin->email);

    return redirect()->route('admin.admins')->with('success', 'アドミン情報が更新されました。');
  }

  /**
   * アドミン削除（スーパーアドミンのみ）
   */
  public function deleteAdmin(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isSuperAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    // 自分自身の削除を禁止
    if ($admin->id == $id) {
      return redirect()->route('admin.admins')->with('error', '自分自身は削除できません。');
    }

    $deleteAdmin = Admin::findOrFail($id);

    // 削除理由のバリデーション
    $request->validate([
      'reason' => 'required|string|max:500',
    ]);

    \App\Services\OperationLogService::log('backend', 'delete_admin', 'admin:' . $admin->id . ' target:' . $deleteAdmin->id . ' email:' . $deleteAdmin->email . ' reason:' . $request->reason);

    // 物理削除を実行
    $deleteAdmin->delete();

    return redirect()->route('admin.admins')->with('success', 'アドミンが削除されました。');
  }

  /**
   * システム状態をチェック
   */
  private function checkSystemStatus()
  {
    $status = [];

    // データベース接続とサイズチェック
    try {
      DB::connection()->getPdo();
      $dbSize = $this->getDatabaseSize();
      $status['database'] = [
        'name' => 'データベース',
        'status' => 'success',
        'message' => sprintf(
          '接続正常 | サイズ: %s | テーブル数: %d',
          $dbSize['formatted_size'],
          $dbSize['table_count']
        )
      ];
    } catch (\Exception $e) {
      $status['database'] = [
        'name' => 'データベース',
        'status' => 'error',
        'message' => '接続に失敗しました'
      ];
    }

    // Laravelストレージディレクトリ監視
    $storageInfo = $this->getStorageUsage();
    $status['app_storage'] = [
      'name' => 'アプリストレージ',
      'status' => $storageInfo['status'],
      'message' => sprintf(
        '総使用量: %s | ファイル数: %d',
        $storageInfo['total_size'],
        $storageInfo['file_count']
      )
    ];

    // ログファイル監視
    $logInfo = $this->getLogFileInfo();
    $status['logs'] = [
      'name' => 'ログファイル',
      'status' => $logInfo['status'],
      'message' => sprintf(
        '総サイズ: %s | エラー: %d件 | 最新: %s',
        $logInfo['total_size'],
        $logInfo['error_count'],
        $logInfo['last_modified']
      )
    ];

    // メモリ使用量チェック
    $memoryUsage = memory_get_usage(true);
    $memoryPeak = memory_get_peak_usage(true);
    $memoryLimit = $this->parseSize(ini_get('memory_limit'));
    if ($memoryLimit > 0) {
      $memoryPercent = ($memoryPeak / $memoryLimit) * 100;
      $status['memory'] = [
        'name' => 'メモリ使用量',
        'status' => $memoryPercent > 85 ? 'error' : ($memoryPercent > 70 ? 'warning' : 'success'),
        'message' => sprintf(
          '現在: %s | ピーク: %s (%.1f%%)',
          $this->formatBytes($memoryUsage),
          $this->formatBytes($memoryPeak),
          $memoryPercent
        )
      ];
    } else {
      $status['memory'] = [
        'name' => 'メモリ使用量',
        'status' => 'success',
        'message' => sprintf(
          '現在: %s | ピーク: %s',
          $this->formatBytes($memoryUsage),
          $this->formatBytes($memoryPeak)
        )
      ];
    }

    // Laravelキャッシュ監視
    $cacheInfo = $this->getCacheInfo();
    $status['cache'] = [
      'name' => 'キャッシュ',
      'status' => $cacheInfo['status'],
      'message' => $cacheInfo['message']
    ];

    // レスポンス時間とデータベースクエリチェック
    $performanceInfo = $this->getPerformanceInfo();
    $status['performance'] = [
      'name' => 'パフォーマンス',
      'status' => $performanceInfo['status'],
      'message' => $performanceInfo['message']
    ];

    return $status;
  }

  /**
   * バイト数を人間が読みやすい形式に変換
   */
  private function formatBytes($bytes, $precision = 2)
  {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
      $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
  }

  /**
   * メモリサイズ文字列を数値に変換
   */
  private function parseSize($size)
  {
    $unit = preg_replace('/[^bkmgtpezy]/i', '', $size);
    $size = preg_replace('/[^0-9\.]/', '', $size);

    if ($unit) {
      return round($size * pow(1024, stripos('bkmgtpezy', $unit[0])));
    } else {
      return round($size);
    }
  }

  /**
   * データベースサイズを取得
   */
  private function getDatabaseSize()
  {
    try {
      // テーブル数を取得
      $tables = DB::select("SHOW TABLES");
      $tableCount = count($tables);

      // データベースサイズを取得
      $dbName = config('database.connections.mysql.database');
      $sizeQuery = "SELECT 
                      ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size_mb
                    FROM information_schema.tables 
                    WHERE table_schema = ?";

      $result = DB::select($sizeQuery, [$dbName]);
      $sizeMb = $result[0]->db_size_mb ?? 0;

      return [
        'table_count' => $tableCount,
        'size_bytes' => $sizeMb * 1024 * 1024,
        'formatted_size' => $this->formatBytes($sizeMb * 1024 * 1024)
      ];
    } catch (\Exception $e) {
      return [
        'table_count' => 0,
        'size_bytes' => 0,
        'formatted_size' => '不明'
      ];
    }
  }

  /**
   * Laravelストレージの使用量を取得
   */
  private function getStorageUsage()
  {
    try {
      $storagePath = storage_path();
      $totalSize = $this->getDirectorySize($storagePath);
      $fileCount = $this->countFiles($storagePath);

      // 100MB以上で警告、500MB以上でエラー
      $status = 'success';
      if ($totalSize > 500 * 1024 * 1024) {
        $status = 'error';
      } elseif ($totalSize > 100 * 1024 * 1024) {
        $status = 'warning';
      }

      return [
        'total_size' => $this->formatBytes($totalSize),
        'file_count' => $fileCount,
        'status' => $status
      ];
    } catch (\Exception $e) {
      return [
        'total_size' => '取得失敗',
        'file_count' => 0,
        'status' => 'warning'
      ];
    }
  }

  /**
   * ログファイル情報を取得
   */
  private function getLogFileInfo()
  {
    try {
      $logsPath = storage_path('logs');
      $totalSize = $this->getDirectorySize($logsPath);
      $errorCount = $this->countRecentErrors(storage_path('logs/laravel.log'));

      // 最新のログファイルの更新日時
      $latestLog = '';
      if (file_exists(storage_path('logs/laravel.log'))) {
        $latestLog = date('m/d H:i', filemtime(storage_path('logs/laravel.log')));
      } else {
        $latestLog = 'なし';
      }

      // 10MB以上で警告、50MB以上でエラー、または過去24時間でエラー5件以上
      $status = 'success';
      if ($totalSize > 50 * 1024 * 1024 || $errorCount > 10) {
        $status = 'error';
      } elseif ($totalSize > 10 * 1024 * 1024 || $errorCount > 5) {
        $status = 'warning';
      }

      return [
        'total_size' => $this->formatBytes($totalSize),
        'error_count' => $errorCount,
        'last_modified' => $latestLog,
        'status' => $status
      ];
    } catch (\Exception $e) {
      return [
        'total_size' => '取得失敗',
        'error_count' => 0,
        'last_modified' => '不明',
        'status' => 'warning'
      ];
    }
  }

  /**
   * キャッシュ情報を取得
   */
  private function getCacheInfo()
  {
    try {
      // ファイルキャッシュのサイズ
      $cachePath = storage_path('framework/cache');
      $cacheSize = 0;
      $cacheFiles = 0;

      if (is_dir($cachePath)) {
        $cacheSize = $this->getDirectorySize($cachePath);
        $cacheFiles = $this->countFiles($cachePath);
      }

      // キャッシュテスト
      $testKey = 'system_status_test_' . time();
      try {
        cache()->put($testKey, 'test', 60);
        $testResult = cache()->get($testKey);
        cache()->forget($testKey);
        $cacheWorking = ($testResult === 'test');
      } catch (\Exception $e) {
        $cacheWorking = false;
      }

      $status = $cacheWorking ? 'success' : 'error';
      $message = sprintf(
        '%s | サイズ: %s | ファイル数: %d',
        $cacheWorking ? '動作正常' : 'キャッシュエラー',
        $this->formatBytes($cacheSize),
        $cacheFiles
      );

      return [
        'status' => $status,
        'message' => $message
      ];
    } catch (\Exception $e) {
      return [
        'status' => 'warning',
        'message' => 'キャッシュ情報取得に失敗'
      ];
    }
  }

  /**
   * パフォーマンス情報を取得
   */
  private function getPerformanceInfo()
  {
    try {
      // データベースレスポンス時間
      $startTime = microtime(true);
      DB::select('SELECT 1');
      $dbResponseTime = (microtime(true) - $startTime) * 1000;

      // アクティブなデータベース接続数（概算）
      $activeConnections = 1; // 現在の接続

      // PHPのOPcache状況
      $opcacheEnabled = function_exists('opcache_get_status');
      $opcacheInfo = '';
      if ($opcacheEnabled) {
        $opcacheStatus = opcache_get_status();
        $opcacheInfo = $opcacheStatus ? '有効' : '無効';
      } else {
        $opcacheInfo = '未サポート';
      }

      // ステータス判定
      $status = 'success';
      if ($dbResponseTime > 1000) {
        $status = 'error';
      } elseif ($dbResponseTime > 300) {
        $status = 'warning';
      }

      $message = sprintf(
        'DB応答: %.1fms | 接続数: %d | OPcache: %s',
        $dbResponseTime,
        $activeConnections,
        $opcacheInfo
      );

      return [
        'status' => $status,
        'message' => $message
      ];
    } catch (\Exception $e) {
      return [
        'status' => 'error',
        'message' => 'パフォーマンス測定に失敗'
      ];
    }
  }

  /**
   * ディレクトリサイズを再帰的に計算
   */
  private function getDirectorySize($directory)
  {
    $size = 0;
    if (is_dir($directory)) {
      foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
        if ($file->isFile()) {
          $size += $file->getSize();
        }
      }
    }
    return $size;
  }

  /**
   * ディレクトリ内のファイル数をカウント
   */
  private function countFiles($directory)
  {
    $count = 0;
    if (is_dir($directory)) {
      foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory)) as $file) {
        if ($file->isFile()) {
          $count++;
        }
      }
    }
    return $count;
  }

  /**
   * 過去24時間のエラー数をカウント
   */
  private function countRecentErrors($logFile)
  {
    try {
      if (!file_exists($logFile)) {
        return 0;
      }

      $handle = fopen($logFile, 'r');
      if (!$handle) return 0;

      $errorCount = 0;
      $yesterday = Carbon::now()->subDay();

      while (($line = fgets($handle)) !== false) {
        if (strpos($line, 'ERROR') !== false || strpos($line, 'error') !== false) {
          // ログの日時を抽出して24時間以内かチェック
          if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', $line, $matches)) {
            $logTime = Carbon::parse($matches[1]);
            if ($logTime->gt($yesterday)) {
              $errorCount++;
            }
          }
        }
      }

      fclose($handle);
      return $errorCount;
    } catch (\Exception $e) {
      return 0;
    }
  }

  /**
   * 友達関係一覧の表示
   */
  public function friendships(Request $request)
  {
    $admin = Auth::guard('admin')->user();

    $query = Friendship::with(['user', 'friend', 'deletedByAdmin'])
      ->withTrashed()
      ->orderBy('created_at', 'desc');

    // ステータスフィルタ
    if ($request->filled('status')) {
      if ($request->status === 'deleted') {
        $query->whereNotNull('deleted_at');
      } elseif ($request->status === 'active') {
        $query->whereNull('deleted_at');
      } else {
        $query->whereNull('deleted_at')
          ->where('status', $request->status);
      }
    }

    // ユーザー名検索
    if ($request->filled('search')) {
      $search = $request->search;
      $query->where(function ($q) use ($search) {
        $q->whereHas('user', function ($userQuery) use ($search) {
          $userQuery->where('name', 'LIKE', "%{$search}%")
            ->orWhere('email', 'LIKE', "%{$search}%")
            ->orWhere('friend_id', 'LIKE', "%{$search}%");
        })->orWhereHas('friend', function ($friendQuery) use ($search) {
          $friendQuery->where('name', 'LIKE', "%{$search}%")
            ->orWhere('email', 'LIKE', "%{$search}%")
            ->orWhere('friend_id', 'LIKE', "%{$search}%");
        });
      });
    }

    $friendships = $query->paginate(20);
    $friendships->appends($request->query());

    return view('admin.friendships.index', compact('admin', 'friendships'));
  }

  /**
   * 友達関係の詳細表示
   */
  public function showFriendship($id)
  {
    $admin = Auth::guard('admin')->user();
    $friendship = Friendship::withTrashed()
      ->with(['user', 'friend', 'deletedByAdmin'])
      ->findOrFail($id);

    return view('admin.friendships.show', compact('admin', 'friendship'));
  }

  /**
   * 友達関係の削除
   */
  public function deleteFriendship(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();
    $friendship = Friendship::findOrFail($id);

    if ($friendship->isDeleted()) {
      return redirect()->back()->with('error', 'この友達関係は既に削除されています。');
    }

    $validated = $request->validate([
      'reason' => 'nullable|string|max:500'
    ]);

    $reason = $validated['reason'] ?? '管理者による削除';
    $friendship->deleteByAdmin($admin->id, $reason);
    \App\Services\OperationLogService::log('backend', 'delete_friendship', 'admin:' . $admin->id . ' friendship:' . $friendship->id);

    return redirect()
      ->route('admin.friendships.show', $friendship->id)
      ->with('success', '友達関係を削除しました。');
  }

  /**
   * 友達関係の復活
   */
  public function restoreFriendship($id)
  {
    $admin = Auth::guard('admin')->user();
    $friendship = Friendship::withTrashed()->findOrFail($id);

    if (!$friendship->isDeleted()) {
      return redirect()
        ->route('admin.friendships.show', $friendship->id)
        ->with('error', 'この友達関係は削除されていません。');
    }

    $friendship->restoreByAdmin();
    \App\Services\OperationLogService::log('backend', 'restore_friendship', 'admin:' . $admin->id . ' friendship:' . $friendship->id);

    return redirect()
      ->route('admin.friendships.show', $friendship->id)
      ->with('success', '友達関係を復活しました。');
  }

  /**
   * グループ一覧表示
   */
  public function groups(Request $request)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    $query = Group::with(['owner' => function ($q) {
      $q->select('id', 'name', 'email');
    }])->withCount('activeMembers as members_count');

    if ($search = $request->get('search')) {
      $query->where('name', 'LIKE', "%{$search}%")
        ->orWhereHas('owner', function ($q) use ($search) {
          $q->where('name', 'LIKE', "%{$search}%")
            ->orWhere('email', 'LIKE', "%{$search}%");
        });
    }

    $groups = $query->orderBy('created_at', 'desc')->paginate(20);
    $groups->appends($request->query());

    \App\Services\OperationLogService::log('backend', 'view_groups', 'admin:' . $admin->id);

    return view('admin.groups.index', compact('admin', 'groups'));
  }

  /**
   * グループ詳細表示
   */
  public function showGroup($id)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    try {
      $group = Group::with(['owner', 'activeMembers.user'])->findOrFail($id);

      \App\Services\OperationLogService::log('backend', 'view_group_detail', 'admin:' . $admin->id . ' group:' . $id);

      return view('admin.groups.show', compact('admin', 'group'));
    } catch (\Exception $e) {
      return redirect()->route('admin.groups')
        ->with('error', 'グループが見つかりません。');
    }
  }

  /**
   * グループ編集画面
   */
  public function editGroup($id)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    try {
      $group = Group::with(['owner', 'activeMembers.user'])->findOrFail($id);

      return view('admin.groups.edit', compact('admin', 'group'));
    } catch (\Exception $e) {
      return redirect()->route('admin.groups')
        ->with('error', 'グループが見つかりません。');
    }
  }

  /**
   * グループ情報更新
   */
  public function updateGroup(Request $request, $id)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    try {
      $group = Group::findOrFail($id);

      $currentMemberCount = $group->getMembersCount();

      $request->validate([
        'name' => 'required|string|max:255',
        'description' => 'nullable|string|max:1000',
        'max_members' => "required|integer|min:{$currentMemberCount}|max:100",
        'chat_styles' => 'required|array',
        'chat_styles.*' => 'in:group,group_member',
      ]);

      $group->update([
        'name' => $request->name,
        'description' => $request->description,
        'max_members' => $request->max_members,
        'chat_styles' => $request->chat_styles,
      ]);

      \App\Services\OperationLogService::log('backend', 'update_group', 'admin:' . $admin->id . ' group:' . $group->id);

      return redirect()->route('admin.groups.show', $group->id)
        ->with('success', 'グループ情報を更新しました。');
    } catch (\Exception $e) {
      return redirect()->back()
        ->with('error', 'グループの更新に失敗しました。')
        ->withInput();
    }
  }

  /**
   * グループメンバー削除
   */
  public function removeMember(Request $request, $groupId, $memberId)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    try {
      $group = Group::findOrFail($groupId);
      $member = $group->groupMembers()->where('user_id', $memberId)->first();

      if (!$member) {
        return redirect()->back()
          ->with('error', 'メンバーが見つかりません。');
      }

      $request->validate([
        'reason' => 'nullable|string|max:500',
      ]);

      // オーナーの削除は禁止
      if ($member->role === 'owner') {
        return redirect()->back()
          ->with('error', 'グループオーナーは削除できません。');
      }

      // メンバーを退会処理
      $member->update([
        'left_at' => now(),
      ]);

      $reason = $request->reason ?? '管理者による削除';
      \App\Services\OperationLogService::log('backend', 'remove_group_member', 'admin:' . $admin->id . ' group:' . $group->id . ' user:' . $memberId . ' reason:' . $reason);

      return redirect()->route('admin.groups.show', $group->id)
        ->with('success', 'メンバーを削除しました。');
    } catch (\Exception $e) {
      return redirect()->back()
        ->with('error', 'メンバーの削除に失敗しました。');
    }
  }

  /**
   * グループメンバー追加
   */
  public function addMember(Request $request, $groupId)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    try {
      $group = Group::findOrFail($groupId);

      $request->validate([
        'user_id' => 'required|exists:users,id',
        'role' => 'required|in:member,admin',
      ]);

      $userId = $request->user_id;

      // 既にメンバーかチェック
      if ($group->hasMember($userId)) {
        return redirect()->back()
          ->with('error', 'このユーザーは既にグループのメンバーです。');
      }

      // メンバー数制限チェック
      if (!$group->canAddMember()) {
        return redirect()->back()
          ->with('error', 'グループのメンバー数が上限に達しています。');
      }

      // ユーザーが削除・バンされていないかチェック
      $user = User::where('id', $userId)
        ->where('is_banned', false)
        ->whereNull('deleted_at')
        ->first();

      if (!$user) {
        return redirect()->back()
          ->with('error', '指定されたユーザーは存在しないか、削除・バンされています。');
      }

      // メンバーを追加
      $group->groupMembers()->create([
        'user_id' => $userId,
        'role' => $request->role,
        'joined_at' => now(),
      ]);

      \App\Services\OperationLogService::log('backend', 'add_group_member', 'admin:' . $admin->id . ' group:' . $group->id . ' user:' . $userId . ' role:' . $request->role);

      return redirect()->route('admin.groups.show', $group->id)
        ->with('success', 'メンバーを追加しました。');
    } catch (\Exception $e) {
      return redirect()->back()
        ->with('error', 'メンバーの追加に失敗しました。')
        ->withInput();
    }
  }

  /**
   * グループメンバーの役割変更
   */
  public function updateMemberRole(Request $request, $groupId, $memberId)
  {
    $admin = Auth::guard('admin')->user();

    if (!$admin->isAdmin()) {
      abort(403, 'アクセス権限がありません。');
    }

    try {
      $group = Group::findOrFail($groupId);
      $member = $group->groupMembers()->where('user_id', $memberId)->firstOrFail();

      $request->validate([
        'role' => 'required|in:member,admin',
      ]);

      // オーナーの役割変更は禁止
      if ($member->role === 'owner') {
        return redirect()->back()
          ->with('error', 'グループオーナーの役割は変更できません。');
      }

      $oldRole = $member->role;
      $member->update(['role' => $request->role]);

      \App\Services\OperationLogService::log('backend', 'update_member_role', 'admin:' . $admin->id . ' group:' . $group->id . ' user:' . $memberId . ' old_role:' . $oldRole . ' new_role:' . $request->role);

      return redirect()->route('admin.groups.show', $group->id)
        ->with('success', 'メンバーの役割を更新しました。');
    } catch (\Exception $e) {
      return redirect()->back()
        ->with('error', '役割の更新に失敗しました。');
    }
  }

  /**
   * サポート会話一覧を表示
   */
  public function supportConversations(Request $request)
  {
    $admin = Auth::guard('admin')->user();

    // サポート会話を取得（新しいChatRoomモデルを使用）
    $query = ChatRoom::where('type', 'support_chat')
      ->with(['participant1', 'participant2', 'latestMessage.sender', 'latestMessage.chatRoom.group.groupMembers'])
      ->orderBy('updated_at', 'desc');

    // 検索機能
    if ($search = $request->get('search')) {
      $query->where(function ($subQuery) use ($search) {
        $subQuery->whereHas('participant1', function ($q) use ($search) {
          $q->where('name', 'LIKE', '%' . $search . '%')
            ->orWhere('email', 'LIKE', '%' . $search . '%');
        })->orWhereHas('participant2', function ($q) use ($search) {
          $q->where('name', 'LIKE', '%' . $search . '%')
            ->orWhere('email', 'LIKE', '%' . $search . '%');
        });
      });
    }

    $conversations = $query->paginate(20);
    $conversations->appends($request->query());

    // 各会話の未読メッセージ数を取得（AdminConversationReadモデル削除のため一時的に0を設定）
    $conversationsWithUnread = $conversations->through(function ($conversation) use ($admin) {
      $conversation->unread_count = 0; // TODO: 新しい未読管理システムに置き換え
      return $conversation;
    });

    return view('admin.support.index', compact('admin', 'conversations'));
  }

  /**
   * サポート会話詳細を表示
   */
  public function supportConversationDetail($conversationId)
  {
    $admin = Auth::guard('admin')->user();
    $conversation = ChatRoom::with(['participant1', 'participant2', 'messages.sender'])
      ->where('type', 'support_chat')
      ->findOrFail($conversationId);

    $messages = $conversation->messages()
      ->with(['sender', 'chatRoom.group.groupMembers'])
      ->orderBy('sent_at', 'asc')
      ->get();

    // 会話詳細を表示する際に自動的に既読にする（AdminConversationReadモデル削除のため無効化）
    // \App\Models\AdminConversationRead::updateLastRead($admin->id, $conversation->id);

    return view('admin.support.detail', compact('admin', 'conversation', 'messages'));
  }

  /**
   * サポート会話に返信
   */
  public function replyToSupport(Request $request, $conversationId)
  {
    $admin = Auth::guard('admin')->user();
    $conversation = ChatRoom::where('type', 'support_chat')->findOrFail($conversationId);

    $request->validate([
      'message' => 'required|string|max:1000',
    ]);

    // 管理者メッセージとして作成
    $message = Message::create([
      'chat_room_id' => $conversation->id,
      'sender_id' => null,  // ユーザーではないのでnull
      'admin_sender_id' => $admin->id,  // 管理者IDを設定
      'text_content' => $request->message,
      'sent_at' => now(),
    ]);
    \App\Services\OperationLogService::log('backend', 'reply_support', 'admin:' . $admin->id . ' conversation:' . $conversation->id);

    // 管理者が返信したので、この会話を既読にする（AdminConversationReadモデル削除のため無効化）
    // \App\Models\AdminConversationRead::updateLastRead($admin->id, $conversation->id);

    // 会話の更新日時を更新
    $conversation->touch();

    return redirect()->back()->with('success', '返信を送信しました。');
  }

  /**
   * 新着サポートメッセージ数を取得（AJAX用）
   */
  public function getUnreadSupportCount()
  {
    $admin = Auth::guard('admin')->user();

    // AdminConversationReadモデル削除のため一時的に0を返す
    $unreadCount = 0; // TODO: 新しい未読管理システムに置き換え

    return response()->json(['unread_count' => $unreadCount]);
  }

  /**
   * サポート会話を既読にする
   */
  public function markSupportAsRead($conversationId)
  {
    $admin = Auth::guard('admin')->user();
    $conversation = ChatRoom::where('type', 'support_chat')->findOrFail($conversationId);

    // AdminConversationReadモデル削除のため無効化
    // \App\Models\AdminConversationRead::updateLastRead($admin->id, $conversation->id);

    return response()->json(['success' => true]);
  }
}
