<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * PART C — expense categories. Owner/Admin only (routes enforce it).
 * Add, rename, activate/deactivate. Deleting is not offered because past
 * expenses keep referring to their category (safe pattern = deactivate).
 */
class ExpenseCategoryController extends Controller
{
    public function index()
    {
        $categories = ExpenseCategory::withCount('expenses')->orderBy('name')->get();

        return view('expenses.categories', compact('categories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100|unique:expense_categories,name',
        ], ['name.unique' => 'You already have a category with that name.']);

        // New categories share the "Other Expense" account unless the owner
        // later maps them elsewhere in the chart of accounts.
        ExpenseCategory::create($data + ['account_code' => '6090', 'active' => true]);

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, ExpenseCategory $category)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('expense_categories', 'name')->ignore($category->id)],
            'active' => 'nullable|boolean',
        ]);

        $category->update([
            'name' => $data['name'],
            'active' => $request->boolean('active'),
        ]);

        return back()->with('success', 'Category updated.');
    }
}
