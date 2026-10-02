<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::ordered()->withCount('products')->get();

        return view('admin.categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.categories.create', ['category' => new Category]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        $category = Category::create($request->validated());

        return redirect()->route('admin.categorias.index')
            ->with('success', "Se creó la categoría «{$category->name}».");
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return redirect()->route('admin.categorias.index')
            ->with('success', "Se guardaron los cambios de «{$category->name}».");
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Solo cuenta productos activos: los eliminados ya no se ven en ninguna parte.
        $count = $category->products()->count();

        if ($count > 0) {
            $what = $count === 1 ? '1 producto' : "{$count} productos";

            return redirect()->route('admin.categorias.index')
                ->with('error', "No puedes eliminar «{$category->name}» porque tiene {$what}. Muévelos primero a otra categoría.");
        }

        $category->delete();

        return redirect()->route('admin.categorias.index')
            ->with('success', "Se eliminó la categoría «{$category->name}».");
    }
}
