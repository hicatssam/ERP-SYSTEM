@extends('layouts.app')

@section('title', 'تعديل قناة بيع')

@section('content')
    <div class="page-header">
        <div>
            <h1 class="page-heading">تعديل {{ $channel->name }}</h1>

            <p class="page-subheading">
                <a href="{{ route('settings.sales-channels.index') }}">قنوات البيع</a>
                &laquo; تعديل
            </p>
        </div>
    </div>

    <form
        action="{{ route('settings.sales-channels.update', $channel) }}"
        method="POST"
        enctype="multipart/form-data"
        style="max-width:1100px"
    >
        @csrf
        @method('PUT')

        @include('admin.settings.sales-channels._form', ['channel' => $channel])

        <div class="form-actions" style="display:flex;justify-content:flex-end;gap:.75rem;margin-top:1.25rem">
            <a class="btn btn-ghost" href="{{ route('settings.sales-channels.show', $channel) }}">
                إلغاء
            </a>

            <button class="btn btn-gold" type="submit">
                حفظ التعديلات
            </button>
        </div>
    </form>
@endsection