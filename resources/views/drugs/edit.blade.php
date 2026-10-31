@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-6">Edit Drug</h1>

    <form method="POST" action="{{ route('drugs.update', $drug->id) }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @method('PUT')

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Name</label>
            <input type="text" name="name" value="{{ old('name', $drug->name) }}"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
        </div>

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Generic Name</label>
            <input type="text" name="generic_name" value="{{ old('generic_name', $drug->generic_name) }}"
                   autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                   data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                   class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Strength</label>
                <input type="text" name="strength" value="{{ old('strength', $drug->strength) }}"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
            </div>
            <div>
                <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Form</label>
                <input type="text" name="form" value="{{ old('form', $drug->form) }}"
                       autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false"
                       data-lpignore="true" data-1p-ignore="true" data-bwignore="true" data-form-type="other"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30">
            </div>
        </div>

        @include('drugs.partials.identity', ['drug' => $drug])

        <div>
            <label class="block text-sm font-medium text-[#1E2530] mb-1.5">Category</label>
            <select name="category_id" class="w-full border border-gray-200 rounded-lg px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[var(--brand-primary)]/30" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected(old('category_id', $drug->category_id) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>

        @include('drugs.partials.packing', ['packing' => $drug])

        <p class="text-xs text-[#64748b]">Changing this does not convert stock already on the shelf — it only changes how that stock is counted and described.</p>

        <div class="flex gap-3 pt-2">
            <button type="submit" class="bg-[var(--brand-primary)] hover:bg-[var(--brand-primary-dark)] text-white font-medium text-sm py-2.5 px-5 rounded-lg transition-colors">
                Save Changes
            </button>
            <a href="{{ route('drugs.index') }}" class="text-[#64748b] hover:text-[#1E2530] text-sm py-2.5 px-4">Cancel</a>
        </div>
    </form>
</div>
@endsection
