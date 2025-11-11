<x-filament-panels::page>
    <x-filament::section>
        <h2 class="text-xl font-bold">Employees for Procurement: {{ $this->record->procurement_id }}</h2>
        <p>Title: {{ $this->record->title ?? 'No Title' }}</p>
    </x-filament::section>
    {{ $this->table }}
</x-filament-panels::page>