<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CategoryRequest;
use App\Models\Category;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Category::class);

        $categories = Category::query()
            ->withCount('posts')
            ->orderBy('order')
            ->get();

        return Inertia::render('admin/categorias/Index', [
            'categories' => $categories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'color' => $category->color,
                'icon' => $category->icon,
                'status' => $category->status,
                'order' => $category->order,
                'postsCount' => $category->posts_count,
            ]),
            'can' => [
                'create' => Gate::allows('create', Category::class),
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Category::class);

        return Inertia::render('admin/categorias/Form', ['category' => null]);
    }

    public function store(CategoryRequest $request, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('create', Category::class);

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category = Category::query()->create($data);

        $activity->log('categoria.creada', $category);

        return to_route('admin.categorias.index')->with('success', "Categoría «{$category->name}» creada.");
    }

    public function edit(Category $category): Response
    {
        Gate::authorize('update', $category);

        return Inertia::render('admin/categorias/Form', [
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'color' => $category->color,
                'icon' => $category->icon,
                'status' => $category->status,
                'order' => $category->order,
            ],
        ]);
    }

    public function update(CategoryRequest $request, Category $category, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('update', $category);

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $category->update($data);

        $activity->log('categoria.actualizada', $category);

        return to_route('admin.categorias.index')->with('success', "Categoría «{$category->name}» actualizada.");
    }

    public function destroy(Category $category, ActivityLogger $activity): RedirectResponse
    {
        Gate::authorize('delete', $category);

        if ($category->posts()->exists()) {
            return back()->with('error', 'No puedes eliminar una categoría con publicaciones asociadas.');
        }

        $name = $category->name;
        $category->delete();

        $activity->log('categoria.eliminada', $category, ['name' => $name]);

        return to_route('admin.categorias.index')->with('success', "Categoría «{$name}» eliminada.");
    }
}
