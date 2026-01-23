<div class="space-y-4">
    @if($records->isEmpty())
        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>No tie-breaking records found.</p>
        </div>
    @else
        @foreach($records as $record)
            <div class="border rounded-lg p-4 bg-white dark:bg-gray-800 shadow-sm">
                {{-- Header --}}
                <div class="flex items-center justify-between mb-3 pb-3 border-b border-gray-200 dark:border-gray-700">
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                        @if($record->procurement_item_id)
                            Item Tie-Breaking
                        @else
                            Grand Total Tie-Breaking
                        @endif
                    </h4>
                    <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                        ✓ Resolved
                    </span>
                </div>

                {{-- Details Grid --}}
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">AOQ Number</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $record->aoq_number }}</p>
                    </div>
                    
                    @if($record->procurement_item_id)
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Item ID</p>
                            <p class="text-sm text-gray-900 dark:text-gray-100">#{{ $record->procurement_item_id }}</p>
                        </div>
                    @endif

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Tied Amount</p>
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">₱{{ number_format($record->tied_amount, 2) }}</p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Number of Tied Suppliers</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ $record->tied_suppliers_count }}</p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Method Used</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100">
                            @if($record->method === 'coin_toss')
                                🪙 Coin Toss
                            @else
                                🎲 Random Draw
                            @endif
                        </p>
                    </div>

                    <div>
                        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Performed At</p>
                        <p class="text-sm text-gray-900 dark:text-gray-100">{{ \Carbon\Carbon::parse($record->performed_at)->format('M d, Y h:i A') }}</p>
                    </div>
                </div>

                {{-- Tied Suppliers --}}
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-2">Tied Suppliers</p>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-3">
                        @php
                            $suppliers = json_decode($record->tied_suppliers_data, true);
                        @endphp
                        @if(is_array($suppliers))
                            <ul class="space-y-1">
                                @foreach($suppliers as $supplier)
                                    <li class="text-sm text-gray-700 dark:text-gray-300">
                                        • {{ $supplier['supplier_name'] ?? 'Unknown' }}
                                        @if(isset($supplier['total_quoted']))
                                            - ₱{{ number_format($supplier['total_quoted'], 2) }}
                                        @elseif(isset($supplier['unit_value']))
                                            - ₱{{ number_format($supplier['unit_value'], 2) }}
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500">No supplier data available</p>
                        @endif
                    </div>
                </div>

                {{-- Winner --}}
                <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4 border-l-4 border-green-500">
                    <p class="text-sm font-medium text-green-700 dark:text-green-400 mb-1">🏆 Winner Selected</p>
                    <p class="text-base font-bold text-green-900 dark:text-green-100">{{ $record->winner_supplier_name }}</p>
                    <p class="text-xs text-green-600 dark:text-green-400 mt-1">Seed: {{ $record->seed_used }}</p>
                </div>
            </div>
        @endforeach
    @endif
</div>