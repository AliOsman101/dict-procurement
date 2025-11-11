@php
    $isLogin = request()->routeIs('filament.admin.auth.login');
@endphp

@if (! $isLogin)
    <div class="flex items-center gap-3">
        <img src="{{ asset('images/dict-logo-only.png') }}" alt="DICT Logo" class="h-10 w-10 rounded-full object-contain">

        <div class="flex flex-col leading-tight">
            <span class="text-base font-bold text-gray-800">DICT CAR</span>
            <span class="text-xs text-gray-500">Procurment System</span>
        </div>
    </div>
@endif
