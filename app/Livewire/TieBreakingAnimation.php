<?php

namespace App\Livewire;

use Livewire\Component;
use App\Models\Procurement;
use App\Models\AoqEvaluation;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class TieBreakingAnimation extends Component
{
    public $procurement;
    public $tieInfo;
    public $isProcessing = false;
    public $currentStep = 'ready'; // ready, animating, complete
    public $winnerIndex = null;
    public $winner = null;
    public $animationProgress = 0;
    
    public function mount($procurement, $tieInfo)
    {
        $this->procurement = $procurement;
        $this->tieInfo = $tieInfo;
    }
    
    public function startTieBreaking()
    {
        \Log::info('🎬 Starting live tie-breaking animation', [
            'procurement_id' => $this->procurement->id,
            'method' => $this->tieInfo['method']
        ]);
        
        $this->isProcessing = true;
        $this->currentStep = 'animating';
        
        // Dispatch browser event to start animation
        $this->dispatch('start-animation', [
            'method' => $this->tieInfo['method'],
            'suppliersCount' => $this->tieInfo['count']
        ]);
    }
    
    public function completeAnimation()
    {
        \Log::info('🎲 Completing tie-breaking process');

        $seed = time() . $this->procurement->id . $this->procurement->procurement_id;
        mt_srand(crc32($seed));

        $suppliers = collect($this->tieInfo['suppliers']);

        if ($this->tieInfo['method'] === 'coin_toss') {
            $this->winnerIndex = mt_rand(0, 1);
        } else {
            $this->winnerIndex = mt_rand(0, $suppliers->count() - 1);
        }

        $this->winner = $suppliers[$this->winnerIndex];

        \Log::info('🏆 Winner determined', [
            'winner_index' => $this->winnerIndex,
            'winner_name' => $this->winner['supplier_name'],
            'winner_id' => $this->winner['rfq_response_id']
        ]);

        $this->storeTieBreakingRecord();
        $this->updateEvaluations();

        $this->currentStep = 'complete';
        $this->isProcessing = false;

        // Dispatch event to notify parent
        $this->dispatch('tie-breaking-complete', ['winner' => $this->winner, 'method' => $this->tieInfo['method']])
            ->to('view-aoq');
    }
    
    private function storeTieBreakingRecord()
    {
        try {
            DB::table('aoq_tie_breaking_records')->insert([
                'procurement_id' => $this->procurement->id,
                'aoq_number' => $this->procurement->procurement_id,
                'method' => $this->tieInfo['method'],
                'tied_amount' => $this->tieInfo['amount'],
                'tied_suppliers_count' => $this->tieInfo['count'],
                'tied_suppliers_data' => json_encode($this->tieInfo['suppliers']),
                'winner_rfq_response_id' => $this->winner['rfq_response_id'],
                'winner_supplier_name' => $this->winner['supplier_name'],
                'seed_used' => crc32(time() . $this->procurement->id . $this->procurement->procurement_id),
                'performed_at' => now(),
                'performed_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            \Log::info('💾 Tie-breaking record stored successfully');
        } catch (\Exception $e) {
            \Log::error('❌ Failed to store tie-breaking record', [
                'error' => $e->getMessage()
            ]);
        }
    }
    
    private function updateEvaluations()
    {
        $rfqResponses = $this->procurement->rfqResponses;
        $procurementItems = $this->procurement->procurementItems;
        
        foreach ($procurementItems as $item) {
            foreach ($rfqResponses as $rfqResponse) {
                $quote = $rfqResponse->quotes->where('procurement_item_id', $item->id)->first();
                
                if (!$quote) continue;
                
                $isWinner = $rfqResponse->id === $this->winner['rfq_response_id'];
                
                $remarks = null;
                if ($isWinner) {
                    $remarks = sprintf(
                        'Winning bid - determined by %s (tied at ₱%s with %d other supplier(s))',
                        $this->tieInfo['method'] === 'coin_toss' ? 'coin toss' : 'random draw',
                        number_format($this->tieInfo['amount'], 2),
                        $this->tieInfo['count'] - 1
                    );
                }
                
                AoqEvaluation::updateOrCreate(
                    [
                        'rfq_response_id' => $rfqResponse->id,
                        'procurement_id' => $this->procurement->id,
                        'requirement' => 'quote_' . $item->id,
                    ],
                    [
                        'status' => 'pass',
                        'lowest_bid' => $isWinner,
                        'remarks' => $remarks,
                    ]
                );
            }
        }
        
        \Log::info('✅ Evaluations updated with tie-breaking results');
    }
    
    public function render()
    {
        return view('livewire.tie-breaking-animation');
    }
}