@extends('admin.layouts.app')

@section('title', 'グループ編集')

@section('content')
<div class="row mb-4">
  <div class="col-12">
    <nav aria-label="breadcrumb">
      <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="{{ route('admin.groups') }}">グループ</a></li>
        <li class="breadcrumb-item"><a href="{{ route('admin.groups.show', $group->id) }}">{{ $group->name }}</a></li>
        <li class="breadcrumb-item active">編集</li>
      </ol>
    </nav>
    <div class="d-flex justify-content-between align-items-center">
      <h1 class="h3 mb-0">
        <i class="fas fa-edit me-2"></i>グループ編集
      </h1>
      <a href="{{ route('admin.groups.show', $group->id) }}" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>詳細に戻る
      </a>
    </div>
  </div>
</div>

<div class="row">
  <div class="col-md-8 mx-auto">
    <div class="card">
      <div class="card-header">
        <h5 class="card-title mb-0">
          <i class="fas fa-info-circle me-2"></i>グループ情報編集
        </h5>
      </div>
      <div class="card-body">
        <form method="POST" action="{{ route('admin.groups.update', $group->id) }}">
          @csrf
          @method('PUT')

          <div class="mb-3">
            <label for="name" class="form-label">グループ名 <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error('name') is-invalid @enderror"
              id="name" name="name" value="{{ old('name', $group->name) }}" required maxlength="255">
            @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>

          <div class="mb-3">
            <label for="description" class="form-label">説明</label>
            <textarea class="form-control @error('description') is-invalid @enderror"
              id="description" name="description" rows="4" maxlength="1000">{{ old('description', $group->description) }}</textarea>
            @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">1000文字以内で入力してください</small>
          </div>

          <div class="mb-3">
            <label for="max_members" class="form-label">メンバー上限 <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error('max_members') is-invalid @enderror"
              id="max_members" name="max_members" value="{{ old('max_members', $group->max_members) }}"
              required min="2" max="100">
            @error('max_members')
            <div class="invalid-feedback">{{ $message }}</div>
            @enderror
            <small class="text-muted">現在のメンバー数: {{ $group->getMembersCount() }}名</small>
          </div>

          <div class="mb-3">
            <label class="form-label">チャットスタイル <span class="text-danger">*</span></label>
            <div class="border rounded p-3">
              <div class="form-check">
                <input class="form-check-input @error('chat_styles') is-invalid @enderror"
                  type="checkbox" value="group" id="chat_style_group" name="chat_styles[]"
                  {{ in_array('group', old('chat_styles', $group->chat_styles ?? [])) ? 'checked' : '' }}>
                <label class="form-check-label" for="chat_style_group">
                  <strong>グループチャット</strong>
                  <div class="text-muted small">全メンバーが参加する共通チャット</div>
                </label>
              </div>
              <div class="form-check mt-2">
                <input class="form-check-input @error('chat_styles') is-invalid @enderror"
                  type="checkbox" value="group_member" id="chat_style_member" name="chat_styles[]"
                  {{ in_array('group_member', old('chat_styles', $group->chat_styles ?? [])) ? 'checked' : '' }}>
                <label class="form-check-label" for="chat_style_member">
                  <strong>メンバー間チャット</strong>
                  <div class="text-muted small">メンバー同士の1対1チャット</div>
                </label>
              </div>
            </div>
            @error('chat_styles')
            <div class="invalid-feedback d-block">{{ $message }}</div>
            @enderror
          </div>

          <div class="d-flex justify-content-between">
            <a href="{{ route('admin.groups.show', $group->id) }}" class="btn btn-secondary">
              <i class="fas fa-times me-1"></i>キャンセル
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save me-1"></i>更新する
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<div class="row mt-4">
  <div class="col-md-8 mx-auto">
    <div class="card border-info">
      <div class="card-header bg-info text-white">
        <h6 class="mb-0">
          <i class="fas fa-info-circle me-2"></i>編集に関する注意事項
        </h6>
      </div>
      <div class="card-body">
        <ul class="mb-0">
          <li>メンバー上限は現在のメンバー数以下には設定できません</li>
          <li>チャットスタイルの変更は既存のチャットルームには影響しません</li>
          <li>グループ名の変更は全メンバーに反映されます</li>
          <li>オーナー権限の変更はここではできません</li>
        </ul>
      </div>
    </div>
  </div>
</div>
@endsection