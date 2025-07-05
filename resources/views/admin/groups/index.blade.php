@extends('admin.layouts.app')

@section('title', 'グループ管理')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <div class="d-flex justify-content-between align-items-center">
      <div>
        <h1 class="h3 mb-0">
          <i class="fas fa-users-gear me-2"></i>グループ管理
        </h1>
        <p class="text-muted">作成されたグループの一覧</p>
      </div>
    </div>
  </div>
</div>

<div class="row mb-4">
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <form method="GET" action="{{ route('admin.groups') }}" class="row g-3">
          <div class="col-md-8">
            <label for="search" class="form-label">検索</label>
            <input type="text" class="form-control" id="search" name="search" value="{{ request('search') }}" placeholder="グループ名またはオーナー名">
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <button type="submit" class="btn btn-primary me-2">
              <i class="fas fa-search me-1"></i>検索
            </button>
            <a href="{{ route('admin.groups') }}" class="btn btn-secondary">
              <i class="fas fa-times me-1"></i>クリア
            </a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-12">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">グループ一覧 ({{ $groups->total() }}件)</h5>
      </div>
      <div class="card-body">
        @if($groups->count() > 0)
        <div class="table-responsive">
          <table class="table table-hover">
            <thead class="table-light">
              <tr>
                <th>ID</th>
                <th>グループ名</th>
                <th>オーナー</th>
                <th>メンバー数</th>
                <th>作成日</th>
                <th>操作</th>
              </tr>
            </thead>
            <tbody>
              @foreach($groups as $group)
              <tr class="{{ $group->isDeleted() ? 'table-danger' : '' }}">
                <td class="id-badge">#{{ $group->id }}</td>
                <td>
                  {{ $group->name }}
                  @if($group->isDeleted())
                  <span class="badge bg-danger ms-1">削除済み</span>
                  @endif
                </td>
                <td>
                  @if($group->owner)
                  {{ $group->owner->name }}
                  @if($group->owner->deleted_at)
                  <span class="badge bg-danger ms-1">削除済み</span>
                  @endif
                  @else
                  <span class="text-muted">-</span>
                  @endif
                </td>
                <td>{{ $group->members_count }}</td>
                <td>{{ $group->created_at->format('Y/m/d') }}</td>
                <td>
                  <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle"
                      type="button"
                      data-bs-toggle="dropdown">
                      <i class="fas fa-ellipsis-v"></i>
                    </button>
                    <ul class="dropdown-menu">
                      <li>
                        <a class="dropdown-item" href="{{ route('admin.groups.show', $group->id) }}">
                          <i class="fas fa-eye me-2"></i>詳細を見る
                        </a>
                      </li>
                      @if(!$group->isDeleted())
                      <li>
                        <a class="dropdown-item" href="{{ route('admin.groups.edit', $group->id) }}">
                          <i class="fas fa-edit me-2"></i>編集
                        </a>
                      </li>
                      @endif
                    </ul>
                  </div>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="d-flex justify-content-between align-items-center mt-3">
          <div class="text-muted">
            {{ $groups->firstItem() }}〜{{ $groups->lastItem() }}件目 / 全{{ $groups->total() }}件
          </div>
          <div>
            {{ $groups->links() }}
          </div>
        </div>
        @else
        <div class="text-center py-5">
          <i class="fas fa-users fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">グループが見つかりません</h5>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection