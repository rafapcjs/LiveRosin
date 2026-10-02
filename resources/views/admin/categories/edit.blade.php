@extends('layouts.admin')

@section('title', 'Editar categoría')

@section('content')
    <div class="page-head">
        <h1>Editar categoría</h1>
        <a href="{{ route('admin.categorias.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    <form method="POST" action="{{ route('admin.categorias.update', $category) }}" novalidate>
        @csrf
        @method('PUT')
        @include('admin.categories._form')
    </form>
@endsection
