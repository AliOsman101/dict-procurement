<x-filament::button
    tag="a"
    color="info"
>
    Sign in
</x-filament::button>


@if (session('status'))
    <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mt-4" role="alert">
        <strong class="font-bold">Notice:</strong>
        <span class="block sm:inline">{{ session('status') }}</span>
    </div>
@endif

<x-filament:
