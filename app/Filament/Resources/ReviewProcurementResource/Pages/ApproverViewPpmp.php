<?php

namespace App\Filament\Resources\ReviewProcurementResource\Pages;

use App\Filament\Resources\ReviewProcurementResource;
use App\Models\ProcurementDocument;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Facades\Storage;
use App\Models\Procurement;

class ApproverViewPpmp extends ViewRecord
{
    protected static string $resource = ReviewProcurementResource::class;
    protected static string $view = 'filament.resources.review-procurement-resource.pages.approver-view-ppmp';

    public function mount($record): void
    {
        $child = Procurement::where('parent_id', $record)
                            ->where('module', 'ppmp')
                            ->firstOrFail();
        $this->record = $child;
        $this->record->refresh();
    }

    public function getTitle(): string
    {
        return 'PPMP No. ' . ($this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->record($this->record)
            ->schema([
                Section::make('PPMP Document')
                    ->schema([
                        TextEntry::make('file_name')
                            ->label('File Name')
                            ->getStateUsing(function ($record) {
                                $document = $record->documents()->where('module', 'ppmp')->latest()->first();
                                return $document ? $document->file_name : 'No file uploaded';
                            }),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->getStateUsing(function ($record) {
                                $document = $record->documents()->where('module', 'ppmp')->latest()->first();
                                return $document ? 'Uploaded' : 'Not Submitted';
                            })
                            ->color(fn ($state): string => $state === 'Uploaded' ? 'info' : 'danger')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('file')
                            ->label('Document')
                            ->state(function ($record) {
                                $document = $record->documents()->where('module', 'ppmp')->latest()->first();
                                return $document ? 'View PPMP (click to open)' : 'No document available';
                            })
                            ->url(function ($record) {
                                $document = $record->documents()->where('module', 'ppmp')->latest()->first();
                                if ($document) {
                                    $filePath = $document->file_path;
                                    if (is_array($filePath)) {
                                        $filePath = $filePath[0] ?? null;
                                    }
                                    if (is_string($filePath) && !empty($filePath) && Storage::disk('public')->exists($filePath)) {
                                        return Storage::url($filePath);
                                    }
                                }
                                return null;
                            })
                            ->openUrlInNewTab(),
                    ])
                    ->columns(3),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}