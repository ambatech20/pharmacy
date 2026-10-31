<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\Request;

class ProductCategoryController extends Controller
{
    public function index()
    {
        return view('product-categories.index', ['categories' => ProductCategory::withCount('products')->orderBy('name')->get()]);
    }

    public function create() { return view('product-categories.create'); }

    public function store(Request $request)
    {
        ProductCategory::create($request->validate([
            'name' => 'required|string|max:255|unique:product_categories,name',
            'description' => 'nullable|string',
        ]) + ['active' => true]);
        return redirect()->route('product-categories.index')->with('success', 'Product category added.');
    }

    public function edit(ProductCategory $productCategory)
    {
        return view('product-categories.edit', ['category' => $productCategory]);
    }

    public function update(Request $request, ProductCategory $productCategory)
    {
        $productCategory->update($request->validate([
            'name' => 'required|string|max:255|unique:product_categories,name,' . $productCategory->id,
            'description' => 'nullable|string',
            'active' => 'nullable|boolean',
        ]) + ['active' => $request->boolean('active')]);
        return redirect()->route('product-categories.index')->with('success', 'Product category updated.');
    }

    public function destroy(ProductCategory $productCategory)
    {
        if ($productCategory->products()->exists()) return back()->with('error', 'Cannot delete a category assigned to products. Deactivate it instead.');
        $productCategory->delete();
        return redirect()->route('product-categories.index')->with('success', 'Product category deleted.');
    }
}
