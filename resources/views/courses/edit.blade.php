@extends('layouts.app')

@section('title', 'Editar curso')

@section('content')
<div class="card mera-form-card">
    <div class="card-header mera-form-header">
        <div class="mera-header-icon"><i class="fa fa-book" aria-hidden="true"></i></div>
        <div>
            <h1 class="h5">Editar curso</h1>
            <span>
                {{ $course->name }}
                @if ($course->content_url)
                · <a href="{{ $course->content_url }}" target="_blank" rel="noopener">Ver curso <i class="fa fa-external-link-alt" aria-hidden="true"></i></a>
                @endif
            </span>
        </div>
    </div>

    <div class="card-block">
        @include('courses._form', [
            'action' => route('courses.update', $course),
            'method' => 'PUT',
            'submitLabel' => 'Guardar cambios',
        ])
    </div>
</div>
@endsection
