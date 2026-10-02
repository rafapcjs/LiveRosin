@extends('layouts.admin')

@section('title', 'Nuevo producto')

@section('content')
    <div class="page-head">
        <h1>Nuevo producto</h1>
        <a href="{{ route('admin.productos.index') }}" class="btn btn-ghost">« Volver</a>
    </div>

    <form method="POST" action="{{ route('admin.productos.store') }}" enctype="multipart/form-data" novalidate data-product-calc>
        @csrf
        @include('admin.products._form')
    </form>
@endsection
