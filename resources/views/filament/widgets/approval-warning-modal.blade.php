<div 
    x-data="{ open: true }"
    x-show="open"
    x-cloak
    class="fixed inset-0 z-50 flex items-center justify-center p-4"
    style="display: none;"
>
    <!-- Backdrop -->
    <div class="fixed inset-0 bg-gray-950/50 dark:bg-gray-950/75"></div>
    
    <!-- Modal -->
    <div class="relative bg-white dark:bg-gray-900 rounded-xl shadow-xl max-w-md w-full overflow-hidden ring-1 ring-gray-950/5 dark:ring-white/10">
        
        <!-- Content -->
        <div class="p-6 space-y-6">
            <!-- Icon -->
            <div class="flex justify-center">
                <div class="flex items-center justify-center w-12 h-12 rounded-full bg-danger-50 dark:bg-danger-500/10">
                    <svg class="w-6 h-6 text-danger-600 dark:text-danger-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Title -->
            <div class="text-center">
                <h2 class="text-xl font-semibold text-gray-950 dark:text-white">
                    {{ $title }}
                </h2>
            </div>
            
            <!-- Message -->
            <div class="text-center">
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    {!! $message !!}
                </p>
            </div>
        </div>
        
        <!-- Footer Actions -->
        <div class="flex items-center justify-center gap-3 px-6 py-4">
            <a 
                href="{{ $url }}" 
                class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus-visible:ring-2 rounded-lg fi-color-custom fi-btn-color-danger fi-size-md fi-btn-size-md gap-1.5 px-3 py-2 text-sm inline-grid shadow-sm bg-custom-600 text-white hover:bg-custom-500 focus-visible:ring-custom-500/50 dark:bg-custom-500 dark:hover:bg-custom-400 dark:focus-visible:ring-custom-400/50 fi-ac-action fi-ac-btn-action"
                style="--c-400:var(--danger-400);--c-500:var(--danger-500);--c-600:var(--danger-600);"
            >
                <span class="fi-btn-label">
                    {{ $buttonLabel }}
                </span>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"></path>
                </svg>
            </a>
        </div>
    </div>
</div>