@extends('layouts.app')
@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-[#1E2530] mb-1">Add Product</h1>
    <p class="text-sm text-[#64748b] mb-6">Add a non-drug item sold by the pharmacy.</p>
    <form method="POST" action="{{ route('products.store') }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
        @csrf
        @php($product = null)
        @include('products.create-fields')
        <div class="flex gap-3 pt-2"><button class="bg-[var(--brand-primary)] text-white font-medium text-sm py-2.5 px-5 rounded-lg">Save Product</button><a href="{{ route('products.index') }}" class="text-[#64748b] py-2.5 px-4">Cancel</a></div>
    </form>
</div>
@endsection
