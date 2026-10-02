@extends('layouts.store')

@section('title', 'Acceso no permitido')

@section('body')
    <main class="gate hero">
        <div class="grain" aria-hidden="true"></div>
        @include('partials.skyline')
        <div class="gate-card">
            <p class="gate-kicker outline">Misión fallida</p>
            <h1>Acceso no permitido</h1>
            <p class="gate-text">Lo sentimos: este catálogo es solo para mayores de 18 años.</p>
            <p class="gate-legal">¿Te equivocaste al responder? <a href="{{ url('/') }}">Volver a la pregunta</a>.</p>
        </div>
    </main>
@endsection
