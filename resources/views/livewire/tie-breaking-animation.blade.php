<div>
    @if(($currentStep ?? 'ready') === 'ready')
        <!-- Ready State -->
        <div class="text-center space-y-6 py-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full shadow-lg">
                <span class="text-4xl">
                    @if($tieInfo['method'] === 'coin_toss')
                        🪙
                    @else
                        🎯
                    @endif
                </span>
            </div>
            
            <div>
                <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-2">
                    Tie Detected!
                </h3>
                <p class="text-gray-600 dark:text-gray-400">
                    {{ $tieInfo['count'] }} suppliers are tied at 
                    <span class="font-bold text-green-600 dark:text-green-400">₱{{ number_format($tieInfo['amount'], 2) }}</span>
                </p>
            </div>
            
            <!-- Tied Suppliers List -->
            <div class="bg-gray-50 dark:bg-gray-800 rounded-xl p-6 max-w-2xl mx-auto">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 flex items-center gap-2">
                    <span class="text-lg">👥</span>
                    Tied Suppliers
                </h4>
                <div class="space-y-2">
                    @foreach($tieInfo['suppliers'] as $supplier)
                        <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                                    <span class="text-lg">🏢</span>
                                </div>
                                <div>
                                    <p class="font-semibold text-gray-900 dark:text-white">
                                        {{ $supplier['supplier_name'] }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        ID: {{ $supplier['rfq_response_id'] }}
                                    </p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">
                                ₱{{ number_format($supplier['total_quoted'], 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <!-- Method Description -->
            <div class="bg-blue-50 dark:bg-blue-900/20 border-2 border-blue-200 dark:border-blue-700 rounded-xl p-4 max-w-2xl mx-auto">
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    <strong>Method:</strong> 
                    @if($tieInfo['method'] === 'coin_toss')
                        A virtual <strong>coin toss</strong> will be performed. Heads for the first supplier, Tails for the second.
                    @else
                        A <strong>random draw</strong> will be performed using a cryptographically-seeded random number generator.
                    @endif
                </p>
            </div>
            
            <!-- Start Button -->
            <button 
                wire:click="startTieBreaking"
                class="inline-flex items-center gap-2 px-8 py-4 bg-gradient-to-r from-orange-500 to-red-500 hover:from-orange-600 hover:to-red-600 text-white font-bold rounded-xl shadow-lg hover:shadow-xl transform hover:scale-105 transition-all duration-200"
                wire:loading.attr="disabled"
            >
                <span class="text-2xl">🎲</span>
                <span>Start Tie-Breaking</span>
            </button>
        </div>
    @endif
    
    @if(($currentStep ?? 'ready') === 'animating')
        <!-- Animation State -->
        <div class="text-center space-y-8 py-12" id="animation-container">
            @if($tieInfo['method'] === 'coin_toss')
                <!-- Coin Toss Animation -->
                <div class="flex justify-center">
                    <div class="coin-flip-container">
                        <div class="coin" id="coin">
                            <div class="coin-side coin-heads">
                                <div class="text-6xl">👤</div>
                                <div class="text-sm font-bold mt-2">HEADS</div>
                            </div>
                            <div class="coin-side coin-tails">
                                <div class="text-6xl">🏢</div>
                                <div class="text-sm font-bold mt-2">TAILS</div>
                            </div>
                        </div>
                    </div>
                </div>
                <p class="text-xl font-semibold text-gray-700 dark:text-gray-300 animate-pulse">
                    Flipping coin...
                </p>
            @else
                <!-- Random Draw Animation -->
                <div class="flex justify-center gap-4" id="drum-container">
                    @foreach($tieInfo['suppliers'] as $index => $supplier)
                        <div class="drum-card" data-index="{{ $index }}">
                            <div class="drum-card-inner">
                                <div class="text-4xl mb-2">🎟️</div>
                                <div class="text-xs font-bold">{{ $index + 1 }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="relative">
                    <div class="text-6xl animate-bounce mb-4">🎰</div>
                    <p class="text-xl font-semibold text-gray-700 dark:text-gray-300 animate-pulse">
                        Drawing winner...
                    </p>
                </div>
            @endif
            
            <!-- Progress Bar -->
            <div class="max-w-md mx-auto">
                <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div class="h-full bg-gradient-to-r from-blue-500 to-purple-500 animate-progress"></div>
                </div>
            </div>
        </div>
    @endif
    
    @if(($currentStep ?? 'ready') === 'complete' && $winner)
        <!-- Winner State -->
        <div class="text-center space-y-6 py-8">
            <!-- Confetti Animation -->
            <div class="confetti-container" id="confetti"></div>
            
            <!-- Trophy Animation -->
            <div class="animate-bounce-in">
                <div class="inline-flex items-center justify-center w-24 h-24 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full shadow-2xl mb-4">
                    <span class="text-6xl animate-pulse">🏆</span>
                </div>
            </div>
            
            <div class="space-y-2 animate-fade-in">
                <h3 class="text-3xl font-bold text-gray-900 dark:text-white">
                    Winner Determined!
                </h3>
                <p class="text-gray-600 dark:text-gray-400">
                    By {{ $tieInfo['method'] === 'coin_toss' ? 'Coin Toss' : 'Random Draw' }}
                </p>
            </div>
            
            <!-- Winner Card -->
            <div class="max-w-2xl mx-auto animate-scale-in">
                <div class="relative bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/30 dark:to-emerald-900/30 border-4 border-green-500 rounded-2xl p-8 shadow-2xl">
                    <div class="absolute -top-4 -right-4 bg-green-500 text-white text-sm font-bold px-4 py-2 rounded-full shadow-lg animate-pulse">
                        WINNER
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 bg-green-100 dark:bg-green-800 rounded-full flex items-center justify-center">
                                <span class="text-3xl">🏢</span>
                            </div>
                            <div class="text-left">
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">
                                    {{ $winner['supplier_name'] }}
                                </p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    Response ID: {{ $winner['rfq_response_id'] }}
                                </p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Winning Bid</p>
                            <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                                ₱{{ number_format($winner['total_quoted'], 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- All Participants -->
            <div class="max-w-2xl mx-auto">
                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">All Participants</h4>
                <div class="space-y-2">
                    @foreach($tieInfo['suppliers'] as $supplier)
                        <div class="flex items-center justify-between p-3 rounded-lg border
                            {{ $supplier['rfq_response_id'] === $winner['rfq_response_id'] 
                                ? 'bg-green-50 dark:bg-green-900/20 border-green-500' 
                                : 'bg-gray-50 dark:bg-gray-800 border-gray-200 dark:border-gray-700' }}">
                            <div class="flex items-center gap-3">
                                <span class="text-xl">
                                    {{ $supplier['rfq_response_id'] === $winner['rfq_response_id'] ? '🏆' : '📋' }}
                                </span>
                                <span class="font-semibold text-gray-900 dark:text-white">
                                    {{ $supplier['supplier_name'] }}
                                </span>
                            </div>
                            <span class="text-sm font-bold text-gray-900 dark:text-white">
                                ₱{{ number_format($supplier['total_quoted'], 2) }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
            
            <!-- Close Button -->
            <button 
                onclick="window.location.reload()"
                class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-lg transition-all duration-200"
            >
                <span>✓</span>
                <span>Complete & Refresh</span>
            </button>
        </div>
    @endif

    <style>
        /* Coin Flip Animation */
        .coin-flip-container {
            perspective: 1000px;
            width: 200px;
            height: 200px;
        }
        
        .coin {
            width: 100%;
            height: 100%;
            position: relative;
            transform-style: preserve-3d;
            animation: flip 2s ease-in-out infinite;
        }
        
        .coin-side {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: linear-gradient(145deg, #ffd700, #ffed4e);
            border: 6px solid #daa520;
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .coin-tails {
            transform: rotateY(180deg);
        }
        
        @keyframes flip {
            0%, 100% { transform: rotateY(0deg) rotateX(0deg); }
            25% { transform: rotateY(90deg) rotateX(180deg); }
            50% { transform: rotateY(180deg) rotateX(360deg); }
            75% { transform: rotateY(270deg) rotateX(540deg); }
        }
        
        /* Drum/Card Animation */
        .drum-card {
            width: 80px;
            height: 100px;
            perspective: 1000px;
        }
        
        .drum-card-inner {
            width: 100%;
            height: 100%;
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            animation: shuffle 0.5s ease-in-out infinite;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        }
        
        @keyframes shuffle {
            0%, 100% { transform: translateY(0px) rotateY(0deg); }
            50% { transform: translateY(-20px) rotateY(180deg); }
        }
        
        /* Progress Bar */
        @keyframes progress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        
        .animate-progress {
            animation: progress 3s ease-in-out;
        }
        
        /* Scale In Animation */
        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.5);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .animate-scale-in {
            animation: scaleIn 0.5s ease-out;
        }
        
        /* Bounce In Animation */
        @keyframes bounceIn {
            0% {
                opacity: 0;
                transform: scale(0.3) translateY(-100px);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
            70% {
                transform: scale(0.9);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .animate-bounce-in {
            animation: bounceIn 0.8s ease-out;
        }
        
        /* Fade In Animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.6s ease-out 0.3s both;
        }
        
        /* Confetti */
        .confetti-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 9999;
        }
    </style>

    <script>
        document.addEventListener('livewire:initialized', () => {
            // Listen for animation start
            Livewire.on('start-animation', (data) => {
                console.log('🎬 Animation started', data);
                
                // Complete animation after 3 seconds
                setTimeout(() => {
                    @this.completeAnimation();
                }, 3000);
            });
            
            // Listen for winner reveal
            Livewire.on('show-winner', (data) => {
                console.log('🏆 Winner revealed', data);
                
                // Trigger confetti
                createConfetti();
            });
        });
        
        function createConfetti() {
            const container = document.getElementById('confetti');
            if (!container) return;
            
            const colors = ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff', '#00ffff'];
            
            for (let i = 0; i < 100; i++) {
                setTimeout(() => {
                    const confetti = document.createElement('div');
                    confetti.style.position = 'absolute';
                    confetti.style.width = '10px';
                    confetti.style.height = '10px';
                    confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                    confetti.style.left = Math.random() * 100 + '%';
                    confetti.style.top = '-10px';
                    confetti.style.opacity = '1';
                    confetti.style.transform = 'rotate(' + (Math.random() * 360) + 'deg)';
                    confetti.style.transition = 'all 3s ease-out';
                    
                    container.appendChild(confetti);
                    
                    setTimeout(() => {
                        confetti.style.top = '100%';
                        confetti.style.opacity = '0';
                        confetti.style.transform = 'rotate(' + (Math.random() * 720) + 'deg)';
                    }, 100);
                    
                    setTimeout(() => {
                        confetti.remove();
                    }, 3100);
                }, i * 30);
            }
        }
    </script>
</div>