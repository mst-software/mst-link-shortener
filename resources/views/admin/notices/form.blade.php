@extends('layouts.admin')

@section('title', $notice->exists ? 'Edit notice' : 'New notice')

@section('content')
  <div class="box box-primary">
    <form action="{{ $notice->exists ? route('admin.notices.update', $notice) : route('admin.notices.store') }}" method="post">
      @csrf
      @if ($notice->exists)
        @method('PUT')
      @endif
      <div class="box-body">
        <div class="form-group @error('title') has-error @enderror">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" class="form-control" maxlength="255" required value="{{ old('title', $notice->title) }}">
          @error('title')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="form-group @error('body') has-error @enderror">
          <label for="body">Body (plain text)</label>
          <textarea id="body" name="body" class="form-control" rows="10" required>{{ old('body', $notice->body) }}</textarea>
          @error('body')<span class="help-block">{{ $message }}</span>@enderror
        </div>
        <div class="checkbox">
          <label>
            <input type="hidden" name="is_published" value="0">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $notice->is_published))>
            Published
          </label>
        </div>
      </div>
      <div class="box-footer">
        <button type="submit" class="btn btn-primary">Save</button>
        <a href="{{ route('admin.notices.index') }}" class="btn btn-default">Cancel</a>
      </div>
    </form>
  </div>
@endsection
