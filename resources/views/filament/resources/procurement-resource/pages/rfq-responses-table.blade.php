@php
    $record = $getState();
    $rfqResponses = $record->rfqResponses;
    $pageComponent = $this;
@endphp

<div class="space-y-4">
    @if($rfqResponses->isEmpty())
        <div class="text-gray-500 text-center py-8">
            Supplier responses not yet documented
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supplier</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Supplier Documents</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">RFQ Document</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($rfqResponses as $rfqResponse)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $rfqResponse->supplier?->business_name ?? $rfqResponse->business_name ?? 'Unknown Supplier' }}
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                @php
                                    $documents = is_string($rfqResponse->documents)
                                        ? json_decode($rfqResponse->documents, true) ?? []
                                        : ($rfqResponse->documents ?? []);
                                @endphp

                                @if(empty($documents))
                                    <span class="text-gray-400">No files uploaded</span>
                                @else
                                    <div class="space-y-1">
                                        @foreach($documents as $req => $path)
                                            @if(is_string($path) && !empty($path))
                                                @php
                                                    $disk = Storage::disk('public');
                                                    $exists = $disk->exists($path);
                                                    $url = $exists ? $disk->url($path) : '#';
                                                    $filename = basename($path);
                                                @endphp
                                                <div>
                                                    <a href="{{ $url }}" target="_blank"
                                                       class="{{ $exists ? 'text-primary-600 hover:underline' : 'text-gray-400 cursor-not-allowed' }}">
                                                        {{ $filename }}
                                                    </a>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                @endif
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-300">
                                @if($rfqResponse->rfq_document)
                                    @php
                                        $disk = Storage::disk('public');
                                        $path = $rfqResponse->rfq_document;
                                        $exists = $disk->exists($path);
                                        $url = $exists ? $disk->url($path) : '#';
                                        $filename = basename($path);
                                    @endphp
                                    <a href="{{ $url }}" target="_blank"
                                       class="{{ $exists ? 'text-primary-600 hover:underline font-semibold' : 'text-red-600' }}">
                                        {{ $exists ? $filename : 'File not found' }}
                                    </a>
                                @else
                                    <span class="text-gray-400">Not uploaded</span>
                                @endif
                            </td>

                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('procurements.rfq-response.pdf', $rfqResponse->id) }}"
                                       target="_blank"
                                       class="text-primary-600 hover:text-primary-800 font-medium">
                                        View PDF
                                    </a>
                                    @if(!$pageComponent->isSupplierEvaluated($rfqResponse->id))
                                        <span class="text-gray-300">|</span>
                                        
                                        <button
                                            type="button"
                                            wire:click="mountAction('editResponseModal', @js(['responseId' => $rfqResponse->id]))"
                                            class="text-primary-600 hover:text-primary-800 dark:text-primary-400 dark:hover:text-primary-300 text-sm font-medium inline-flex items-center gap-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                            </svg>
                                            Edit
                                        </button>
                                        
                                        <span class="text-gray-300">|</span>
                                        
                                        <button
                                            type="button"
                                            wire:click="mountAction('deleteResponseModal', @js(['responseId' => $rfqResponse->id]))"
                                            class="text-danger-600 hover:text-danger-800 dark:text-danger-400 dark:hover:text-danger-300 text-sm font-medium inline-flex items-center gap-1"
                                        >
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Delete
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>