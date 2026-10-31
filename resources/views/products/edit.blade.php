@extends('layouts.app')
@section('content')
<div class="max-w-lg mx-auto"><h1 class="text-2xl font-bold mb-6">Edit Product</h1><form method="POST" action="{{ route('products.update', $product) }}" autocomplete="off" data-form-type="other" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">@csrf @method('PUT')
@include('products.create-fields')
<div class="flex items-center gap-2"><input type="checkbox" name="active" value="1" @checked(old('active', $product->active))><span class="text-sm">Active and available for sale</span></div>
<div class="flex gap-3"><button class="bg-[var(--brand-primary)] text-white font-medium text-sm py-2.5 px-5 rounded-lg">Save Changes</button><a href="{{ route('products.index') }}" class="text-[#64748b] py-2.5 px-4">Cancel</a></div></form></div>
@endsection
