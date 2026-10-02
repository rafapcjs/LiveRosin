@extends('layouts.admin')

@section('title', 'Categorías')

@section('content')
    <div class="page-head">
        <h1>Categorías</h1>
        <a href="{{ route('admin.categorias.create') }}" class="btn btn-primary">+ Nueva categoría</a>
    </div>

    <p class="muted">Las categorías aparecen como botones de filtro en el catálogo, en orden alfabético.</p>

    @if ($categories->isEmpty())
        <div class="card empty">Todavía no tienes categorías. Crea la primera con el botón «Nueva categoría».</div>
    @else
        <table class="rtable">
            <thead>
            <tr>
                <th>Nombre</th>
                <th class="num">Productos</th>
                <th><span class="visually-hidden">Acciones</span></th>
            </tr>
            </thead>
            <tbody>
            @foreach ($categories as $category)
                <tr>
                    <td class="cell-main"><strong>{{ $category->name }}</strong></td>
                    <td data-label="Productos" class="num">
                        <a href="{{ route('admin.productos.index', ['categoria' => $category->id]) }}">{{ num($category->products_count) }}</a>
                    </td>
                    <td class="cell-actions">
                        <div class="actions">
                            <a href="{{ route('admin.categorias.edit', $category) }}" class="btn btn-sm">Editar</a>
                            <form method="POST" action="{{ route('admin.categorias.destroy', $category) }}"
                                  data-confirm="¿Seguro que quieres eliminar la categoría «{{ $category->name }}»?"
                                  data-confirm-ok="Sí, eliminar">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger">Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
@endsection
