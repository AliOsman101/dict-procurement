<div class="p-6 space-y-6">
    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 border-2 border-blue-300 dark:border-blue-700 rounded-xl p-6 shadow-sm">
        <div class="flex items-center gap-3 mb-3">
            <div class="bg-blue-500 dark:bg-blue-600 rounded-full p-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3" />
                </svg>
            </div>
            <h3 class="text-xl font-bold text-blue-900 dark:text-blue-100">
                Tie-Breaking Results
            </h3>
        </div>
        <p class="text-sm text-blue-800 dark:text-blue-200 leading-relaxed">
            Multiple suppliers submitted identical lowest bids. A fair and transparent tie-breaking method was executed to determine the winning supplier.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-2xl">
                    @if($record->method === 'coin_toss')
                        🪙
                    @else
                        🎯
                    @endif
                </span>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Method Used</h4>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white">
                @if($record->method === 'coin_toss')
                    Coin Toss
                @else
                    Random Draw
                @endif
            </p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-2xl">💰</span>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tied Amount</h4>
            </div>
            <p class="text-lg font-bold text-green-600 dark:text-green-400">
                ₱{{ number_format($record->tied_amount, 2) }}
            </p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-2xl">👥</span>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Tied Suppliers</h4>
            </div>
            <p class="text-lg font-bold text-gray-900 dark:text-white">
                {{ $record->tied_suppliers_count }} Suppliers
            </p>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-lg border border-gray-200 dark:border-gray-700 p-4 shadow-sm">
            <div class="flex items-center gap-2 mb-2">
                <span class="text-2xl">📅</span>
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300">Performed At</h4>
            </div>
            <p class="text-sm font-semibold text-gray-900 dark:text-white">
                {{ \Carbon\Carbon::parse($record->performed_at)->format('M d, Y') }}
            </p>
            <p class="text-xs text-gray-600 dark:text-gray-400">
                {{ \Carbon\Carbon::parse($record->performed_at)->format('h:i A') }}
            </p>
        </div>
    </div>

    <div class="space-y-3">
        <h4 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
            <span class="text-xl">📊</span>
            Participating Suppliers
        </h4>
        <div class="space-y-3">
            @foreach($suppliers as $index => $supplier)
                <div class="relative overflow-hidden rounded-xl border-2 transition-all duration-300 hover:shadow-md
                    {{ $supplier['rfq_response_id'] == $record->winner_rfq_response_id 
                        ? 'border-green-500 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30' 
                        : 'border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800' }}">
                    
                    @if($supplier['rfq_response_id'] == $record->winner_rfq_response_id)
                        <div class="absolute top-0 right-0 bg-green-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg">
                            WINNER
                        </div>
                    @endif
                    
                    <div class="p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex items-start gap-3 flex-1">
                                <div class="mt-1">
                                    @if($supplier['rfq_response_id'] == $record->winner_rfq_response_id)
                                        <div class="bg-green-100 dark:bg-green-800 rounded-full p-2">
                                            <span class="text-2xl">🏆</span>
                                        </div>
                                    @else
                                        <div class="bg-gray-100 dark:bg-gray-700 rounded-full p-2">
                                            <span class="text-2xl">📋</span>
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1">
                                    <p class="font-bold text-lg text-gray-900 dark:text-white mb-1">
                                        {{ $supplier['supplier_name'] }}
                                    </p>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        Response ID: <span class="font-mono font-semibold">{{ $supplier['rfq_response_id'] }}</span>
                                    </p>
                                    @if($supplier['rfq_response_id'] == $record->winner_rfq_response_id)
                                        <div class="mt-2 inline-flex items-center gap-1 bg-green-100 dark:bg-green-800 text-green-800 dark:text-green-100 text-xs font-semibold px-2 py-1 rounded-full">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                            </svg>
                                            Selected Winner
                                        </div>
                                    @endif
                                </div>
                            </div>
                            <div class="text-right">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-1">Quoted Amount</p>
                                <p class="text-xl font-bold text-gray-900 dark:text-white">
                                    ₱{{ number_format($supplier['total_quoted'], 2) }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-gradient-to-r from-gray-50 to-slate-50 dark:from-gray-800 dark:to-slate-800 rounded-xl p-5 border border-gray-200 dark:border-gray-700">
        <h4 class="text-sm font-bold text-gray-900 dark:text-white mb-3 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            Audit Information
        </h4>
        <div class="space-y-2">
            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm text-gray-600 dark:text-gray-400">Random Seed</span>
                <span class="text-sm font-mono font-semibold text-gray-900 dark:text-white">{{ $record->seed_used }}</span>
            </div>
            <div class="flex items-center justify-between py-2 border-b border-gray-200 dark:border-gray-700">
                <span class="text-sm text-gray-600 dark:text-gray-400">AOQ Number</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">{{ $record->aoq_number }}</span>
            </div>
            <div class="flex items-center justify-between py-2">
                <span class="text-sm text-gray-600 dark:text-gray-400">Performed By</span>
                <span class="text-sm font-semibold text-gray-900 dark:text-white">
                    {{ \App\Models\User::find($record->performed_by)?->name ?? 'System' }}
                </span>
            </div>
        </div>
        <div class="mt-4 p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <p class="text-xs text-blue-800 dark:text-blue-200 leading-relaxed">
                <strong>Verification:</strong> This tie-breaking process used a cryptographically-seeded pseudo-random number generator (PRNG) to ensure fairness, transparency, and reproducibility. The random seed is stored for audit purposes.
            </p>
        </div>
    </div>
</div>