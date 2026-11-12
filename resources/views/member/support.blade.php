@extends('layouts.member')

@section('title', 'Support')

@section('content')
  @php($member = auth()->user())
  @php($fields = [
    ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => $member->fullname ?: $member->username],
    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $member->email],
    ['name' => 'title', 'label' => 'Title', 'type' => 'text', 'value' => ''],
  ])
  <div class="box box-primary">
    <div class="box-body">
      <form action="{{ route('member.form.support.store') }}" method="post" class="form-horizontal">
        @csrf
        @foreach ($fields as $field)
          <div class="form-group @error($field['name']) has-error @enderror">
            <div class="col-xs-12">
              <label for="{{ $field['name'] }}">{{ $field['label'] }}</label>
              <input type="{{ $field['type'] }}" id="{{ $field['name'] }}" name="{{ $field['name'] }}" class="form-control" placeholder="{{ $field['label'] }}" maxlength="255" required value="{{ old($field['name'], $field['value']) }}">
              @error($field['name'])<span class="help-block">{{ $message }}</span>@enderror
            </div>
          </div>
        @endforeach
        <div class="form-group @error('message') has-error @enderror">
          <div class="col-xs-12">
            <label for="message">Message</label>
            <textarea id="message" name="message" class="form-control" placeholder="Message" rows="4" maxlength="5000" required>{{ old('message') }}</textarea>
            @error('message')<span class="help-block">{{ $message }}</span>@enderror
          </div>
        </div>
        <input type="submit" value="Submit" class="btn btn-primary">
      </form>
    </div>
  </div>
@endsection
