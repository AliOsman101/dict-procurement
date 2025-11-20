@php
    $record = $getState();
    $rfqResponses = $record->rfqResponses ?? collect();
@endphp

<div class="space-y-6">
    @if($rfqResponses->isEmpty())
        <div class="text-center py-12 text-gray-500 dark:text-gray-400">
            Supplier responses not yet documented
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full w-full table-auto divide-y divide-gray-200 dark:divide-gray-700 border border-gray-200 dark:border-gray-700 rounded-lg">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider w-1/5">
                            Supplier
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider w-1/5">
                            Contact
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider w-1/5">
                            Submitted By
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider w-1/5">
                            Designation
                        </th>
                        <th class="px-6 py-4 text-left text-xs font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wider w-1/5">
                            Submitted Date
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach($rfqResponses as $response)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors">
                            <!-- Supplier -->
                            <td class="px-6 py-4 text-sm font-medium text-gray-900 dark:text-gray-100 align-top">
                                {{ $response->supplier?->business_name ?? $response->business_name ?? 'Unknown' }}
                            </td>

                            <!-- Contact -->
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 align-top">
                                <div class="font-medium">{{ $response->contact_no ?? 'N/A' }}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{{ $response->email_address ?? 'N/A' }}</div>
                            </td>

                            <!-- Submitted By -->
                            <td class="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 align-top">
                                {{ $response->submitted_by ?? 'N/A' }}
                            </td>

                            <!-- Designation -->
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 align-top">
                                {{ $response->designation ?? 'N/A' }}
                            </td>

                            <!-- Submitted Date -->
                            <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-300 align-top whitespace-nowrap">
                                {{ $response->submitted_date ? \Carbon\Carbon::parse($response->submitted_date)->format('M d, Y') : 'N/A' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6 text-sm text-gray-600 dark:text-gray-400 flex justify-between items-center">
            <span><strong>Total Responses:</strong> {{ $rfqResponses->count() }}</span>
        </div>
    @endif
</div>