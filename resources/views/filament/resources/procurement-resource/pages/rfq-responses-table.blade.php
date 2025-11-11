@php
    // Get the actual RFQ record to attach responses to
    $rfq = \App\Models\Procurement::where('parent_id', $getState()->parent_id)
        ->where('module', 'request_for_quotation')
        ->first();
@endphp

@if($rfq)
    @livewire(\App\Filament\Resources\ProcurementResource\RelationManagers\RfqResponsesRelationManager::class, [
        'ownerRecord' => $rfq,
        'pageClass' => \App\Filament\Resources\ProcurementResource\Pages\ViewAoq::class,
    ])
@else
    <div class="text-gray-500 p-4">
        No RFQ found for this AOQ.
    </div>
@endif