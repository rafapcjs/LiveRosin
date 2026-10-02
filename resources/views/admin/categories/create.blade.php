@extends('layouts.admin')

@section('title', 'Nueva categoría')

@section('content')
    <div class="page-head">
        <h1>Nueva categoría</h1>
        <a href="{{ route('admin.categorias.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    <form method="POST" action="{{ route('admin.categorias.store') }}" novalidate>
        @csrf
        @include('admin.categories._form')
    </form>
@endsection
