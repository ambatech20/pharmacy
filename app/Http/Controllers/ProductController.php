<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
    private function categoryHints()
    {
        return [
            'Cosmetics' => 'Makeup, beauty products, and cosmetic accessories.',
            'Hair Care' => 'Shampoo, conditioner, hair oil, hair cream, and treatments.',
            'Skin Care' => 'Face wash, moisturizer, sunscreen, lotion, and skin creams.',
            'Oral Care' => 'Toothbrushes, toothpaste, mouthwash, and dental floss.',
            'Personal Care' => 'Deodorant, body spray, body wash, and personal hygiene items.',
            'Baby Care' => 'Baby shampoo, baby lotion, baby soap, diapers, and baby items.',
            'Hygiene Products' => 'Soap, sanitary products, hand sanitizer, and tissues.',
            'Medical Supplies' => 'Gloves, masks, bandages, gauze, and non-drug supplies.',
            'Other Products' => 'A retail product that does not fit another category.',
        ];
    }
    public function index(Request $request)
    {
        $query = Product::with('category')
            ->withSum([
                'purchaseBatches as current_stock' => fn ($batches) => $batches
                    ->where(fn ($q) => $q
                        ->whereNull('expiry_date')
                        ->orWhere('expiry_date', '>=', now()->toDateString())),
            ], 'quantity_remaining')
            ->orderBy('name');
        if ($request->filled('q')) {
            $term = $request->string('q');
            $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")->orWhere('sku', 'like', "%{$term}%"));
        }
        if ($request->filled('category_id')) $query->where('category_id', $request->category_id);
        if ($request->input('status') === 'inactive') $query->where('active', false);
        elseif ($request->input('status') === 'active') $query->where('active', true);

        return view('products.index', [
            'products' => $query->paginate(15)->withQueryString(),
            'categories' => ProductCategory::where('active', true)->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        $categories = ProductCategory::where('active', true)->orderBy('name')->get();
        if ($categories->isEmpty()) return redirect()->route('product-categories.create')->with('error', 'Add a product category first.');
        return view('products.create', ['categories' => $categories, 'categoryHints' => $this->categoryHints()]);
    }

    public function store(Request $request)
    {
        $validated = $this->validated($request);

        $validated['minimum_stock'] = $validated['minimum_stock_level'] ?? 0;
        $validated['sku'] = filled($validated['sku'] ?? null) ? trim($validated['sku']) : null;
        unset($validated['minimum_stock_level']);

        Product::create($validated + ['active' => true]);

        return redirect()->route('products.index')->with('success', 'Product added. Record a purchase to add stock.');
    }

    public function edit(Product $product)
    {
        $categories = ProductCategory::where('active', true)->orderBy('name')->get();
        return view('products.edit', ['product' => $product, 'categories' => $categories, 'categoryHints' => $this->categoryHints()]);
    }

    public function update(Request $request, Product $product)
    {
        $validated = $this->validated($request, $product);

        $validated['minimum_stock'] = $validated['minimum_stock_level'] ?? 0;
        $validated['sku'] = filled($validated['sku'] ?? null) ? trim($validated['sku']) : null;
        unset($validated['minimum_stock_level']);

        $product->update($validated + ['active' => $request->boolean('active')]);

        return redirect()->route('products.index')->with('success', 'Product updated.');
    }

    /**
     * Products use the same three-rung sentence as drugs, with a wider choice
     * of base units because a product may be a bottle, a roll or a pair.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Product $product = null): array
    {
        $tops = ['pack', 'box', 'bottle', 'tin', 'vial', 'tube', 'piece'];
        $bases = ['piece', 'bottle', 'roll', 'sachet', 'ampoule', 'bag', 'pair', 'unit', 'tablet', 'capsule'];

        if ($product?->unit_top) {
            $tops[] = strtolower((string) $product->unit_top);
        }
        if ($product?->unit_base) {
            $bases[] = strtolower((string) $product->unit_base);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100|unique:products,sku' . ($product ? ',' . $product->id : ''),
            'category_id' => 'required|exists:product_categories,id',
            'brand' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'purchase_price' => 'required|numeric|min:0',
            'selling_price' => 'required|numeric|min:0',
            'minimum_stock_level' => 'required|numeric|min:0',
            'active' => 'nullable|boolean',

            // Packing. Optional so older callers that only post a price and a
            // minimum still work; the model then keeps a single rung.
            'unit_top' => ['nullable', Rule::in(array_values(array_unique($tops)))],
            'inner' => ['nullable', Rule::in(['none', 'strip', 'sachet', 'ampoule', 'bottle', 'vial', 'tin', 'tube', 'loose'])],
            'top_holds' => [
                'nullable', 'integer', 'min:1', 'max:10000',
                Rule::requiredIf(fn () => $this->holdsContents($request)),
            ],
            'middle_holds' => [
                'nullable', 'integer', 'min:1', 'max:10000',
                Rule::requiredIf(fn () => $this->holdsContents($request)
                    && $request->input('inner') === 'strip'),
            ],
            'unit_base' => ['nullable', Rule::in(array_values(array_unique($bases)))],

            // Legacy geometry, still accepted.
            'unit_of_measure' => 'nullable|string|max:50',
            'units_per_strip' => 'nullable|integer|min:1|max:10000',
            'strips_per_pack' => 'nullable|integer|min:1|max:10000',
        ], [
            'top_holds.required' => 'Tell us how many fit in one pack or box.',
            'middle_holds.required' => 'Tell us how many pieces one strip holds.',
        ]);

        unset($validated['inner']);

        if ($request->filled('unit_top')) {
            $this->assertPackingCanExist($request);

            return array_merge($validated, $this->packingAttributes($request));
        }

        $validated['unit_of_measure'] = $validated['unit_of_measure'] ?? 'piece';
        $validated['units_per_strip'] = $validated['units_per_strip'] ?? 1;
        $validated['strips_per_pack'] = $validated['strips_per_pack'] ?? 1;

        return $validated;
    }

    private function holdsContents(Request $request): bool
    {
        $top = strtolower((string) $request->input('unit_top'));

        // A piece is one piece. Every other product container follows the
        // record's own "contains" answer, the same as a drug.
        return $top !== 'piece' && $request->input('inner', 'none') !== 'none';
    }

    /**
     * Refuse a packing sentence that cannot mean anything.
     *
     * "A piece is one piece" is a complete sentence, so it is exempt. A box,
     * bottle, tin or tube containing itself is not; that is the shape the old
     * bug saved, and it must be corrected rather than silently collapsed.
     */
    private function assertPackingCanExist(Request $request): void
    {
        $top = strtolower((string) $request->input('unit_top'));

        if ($top === 'piece') {
            return;
        }

        $inner = strtolower((string) $request->input('inner', 'none'));

        if ($inner === 'none') {
            return;
        }

        $base = strtolower((string) $request->input('unit_base', 'piece'));
        $contents = in_array($inner, ['strip', 'loose'], true) ? $base : $inner;

        if ($inner === 'strip' && $base === 'strip') {
            throw ValidationException::withMessages([
                'unit_base' => 'A strip must contain pieces, not more strips.',
            ]);
        }

        if ($inner === $top || $contents === $top) {
            throw ValidationException::withMessages([
                'inner' => 'Choose something different inside the ' . $top
                    . '. A container cannot contain itself; choose "Nothing - sold as one unit" if you sell it whole.',
            ]);
        }
    }

    /**
     * Turn the form's sentence into the rungs the model stores.
     *
     * @return array<string, mixed>
     */
    private function packingAttributes(Request $request): array
    {
        $top = strtolower((string) $request->input('unit_top'));
        $inner = (string) $request->input('inner', 'none');
        $held = max(1, (int) $request->input('top_holds'));
        $perStrip = max(1, (int) $request->input('middle_holds'));
        $base = strtolower((string) $request->input('unit_base', 'piece'));

        $contentsBase = in_array($inner, ['strip', 'loose'], true) ? $base : $inner;

        if ($top === 'piece' || $inner === 'none' || $inner === $top || $contentsBase === $top) {
            return [
                'unit_top' => $top,
                'unit_middle' => null,
                'unit_base' => $top,
                'top_holds' => null,
                'middle_holds' => null,
                'sell_top' => true,
                'sell_middle' => false,
                'sell_base' => true,
            ];
        }

        if ($inner === 'strip') {
            return [
                'unit_top' => $top,
                'unit_middle' => 'strip',
                'unit_base' => $base,
                'top_holds' => $held,
                'middle_holds' => $perStrip,
                'sell_top' => true,
                'sell_middle' => $request->boolean('sell_middle'),
                'sell_base' => $request->boolean('sell_base'),
            ];
        }

        return [
            'unit_top' => $top,
            'unit_middle' => null,
            'unit_base' => $inner === 'loose' ? $base : $inner,
            'top_holds' => $held,
            'middle_holds' => null,
            'sell_top' => true,
            'sell_middle' => false,
            'sell_base' => $request->boolean('sell_base'),
        ];
    }

    public function destroy(Product $product)
    {
        if ($product->purchaseBatches()->exists()) return back()->with('error', 'Cannot delete a product with stock history. Deactivate it instead.');
        $product->delete();
        return redirect()->route('products.index')->with('success', 'Product deleted.');
    }
}
