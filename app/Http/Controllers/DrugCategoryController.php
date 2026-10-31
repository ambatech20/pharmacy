<?php

namespace App\Http\Controllers;

use App\Models\DrugCategory;
use Illuminate\Http\Request;

class DrugCategoryController extends Controller
{
    public function index()
    {
        $categories = DrugCategory::withCount('drugs')->orderBy('name')->get();
        return view('categories.index', compact('categories'));
    }

    public function create()
    {
        return view('categories.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        DrugCategory::create($validated);

        return redirect()->route('categories.index')->with('success', 'Category added.');
    }

    public function edit(DrugCategory $category)
    {
        return view('categories.edit', compact('category'));
    }

    public function update(Request $request, DrugCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $category->update($validated);

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(DrugCategory $category)
    {
        if ($category->drugs()->exists()) {
            return back()->with('error', 'Cannot delete this category — drugs are still assigned to it.');
        }

        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }
}
