@extends('layouts.store')

@section('title', 'Solo mayores de edad')

@section('body')
    <main class="gate hero">
        <div class="grain" aria-hidden="true"></div>
        @include('partials.skyline')
        <div class="gate-card">
            <p class="gate-kicker outline">Zona solo para adultos</p>
            <p class="gate-big outline-lg" aria-hidden="true">+18</p>
            <h1>¿Eres mayor de 18 años?</h1>
            <p class="gate-text">
                Para entrar a {{ \App\Models\Setting::current()->store_name }} tienes que ser mayor de edad.
                Aquí se venden bebidas alcohólicas y vapeadores.
            </p>
            <form method="POST" action="{{ route('age.confirm') }}" class="gate-actions">
                @csrf
                <input type="hidden" name="volver" value="{{ $back }}">
                <button type="submit" name="respuesta" value="si" class="btn btn-go">Sí, soy mayor de 18</button>
                <button type="submit" name="respuesta" value="no" class="btn btn-ghost-light">No</button>
            </form>
            <p class="gate-legal">
                El exceso de alcohol es perjudicial para la salud. Ley 30 de 1986.
                Prohíbese el expendio de bebidas embriagantes a menores de edad. Ley 124 de 1994.
            </p>
        </div>
    </main>
@endsection
