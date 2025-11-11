<?php
namespace App\Filament\Resources\ProcurementResource\Pages;

use App\Filament\Resources\ProcurementResource;
use App\Models\ProcurementDocument;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Actions;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use App\Mail\PpmpUploadedMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Auth;
use App\Models\ActivityLog;



class ViewPpmp extends ViewRecord
{
    protected static string $resource = ProcurementResource::class;
    protected static string $view = 'filament.resources.procurement-resource.pages.view-ppmp';

    public function getTitle(): string
    {
        $ppmpRecord = $this->record->children()->where('module', 'ppmp')->first();
        return 'PPMP No. ' . ($ppmpRecord->procurement_id ?? $this->record->procurement_id ?? 'N/A');
    }

    public function infolist(Infolist $infolist): Infolist
    {
        $ppmpRecord = $this->record->children()->where('module', 'ppmp')->first() ?? $this->record;
        return $infolist
            ->record($ppmpRecord)
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
                                return $document ? $document->status : 'Not Submitted';
                            })
                            ->color(fn ($state): string => $state === 'Uploaded' ? 'info' : 'danger')
                            ->weight('bold')
                            ->size('lg'),
                        TextEntry::make('file')
                            ->label('Document')
                            ->state(function ($record) {
                                $document = $record->documents()->where('module', 'ppmp')->latest()->first();
                                return $document ? 'View PPMP' : 'No document available';
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
        $user = auth()->user(); $canEdit = $user && $user->can('update', $this->record);

        $ppmpRecord = $this->record->children()->where('module', 'ppmp')->first() ?? $this->record;
        $hasDocument = $ppmpRecord->documents()->where('module', 'ppmp')->exists();

        if ($hasDocument) {
            return [];
        }

        return [
            Actions\Action::make('uploadPpmp')
                ->label('Upload PPMP')
                ->button()
                ->color('warning')
                ->icon('heroicon-o-arrow-up-tray')
                ->visible(fn () => $canEdit) // ✅ add this line
                ->form([
                    \Filament\Forms\Components\FileUpload::make('file')
                        ->label('Upload PPMP File')
                        ->disk('public')
                        ->directory('uploads/ppmp')
                        ->preserveFilenames()
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        ])
                        ->required()
                        ->multiple(false)
                        ->storeFiles(true)
                        ->storeFileNamesIn('file_name'),
                ])
                ->action(function (array $data) use ($ppmpRecord) {
    $filePath = is_array($data['file']) ? ($data['file'][0] ?? null) : $data['file'];
    if (!is_string($filePath) || empty($filePath)) {
        throw new \Exception('Invalid file upload: File path must be a non-empty string.');
    }

    $fileName = $data['file_name'] ?? basename($filePath);

    // Save PPMP document
    \App\Models\ProcurementDocument::create([
        'procurement_id' => $ppmpRecord->id,
        'module' => 'ppmp',
        'file_path' => $filePath,
        'file_name' => $fileName,
        'status' => 'Uploaded',
    ]);

    $uploader = Auth::user();
    $procurement = $this->record;


     // ✅ Log the upload action in History Log
                     ActivityLog::create([
        'user_id' => $uploader->id,
        'role' => $uploader->roles->pluck('name')->implode(', ') ?? 'Unknown',
        'action' => 'Uploaded PPMP',
        'details' => $ppmpRecord->procurement_id ?? 'Unknown PPMP Number', 
        'ip_address' => request()->ip(),
    ]);

    // Collect recipients
    $recipients = [];

    // Employees linked to procurement
    foreach ($procurement->employees as $employee) {
        if ($employee->user && $employee->user->email) {
            $recipients[] = $employee->user->email;
        }
    }

    // Add uploader/creator email if not already included
    if ($uploader && $uploader->email && !in_array($uploader->email, $recipients)) {
        $recipients[] = $uploader->email;
    }

    // Send email to all recipients
    foreach ($recipients as $email) {
        Mail::to($email)->send(new PpmpUploadedMail($procurement, $uploader));
    }

    // Redirect back to PPMP view
    $this->redirect(route('filament.admin.resources.procurements.view-ppmp', $this->record));
})

                ->modalHeading('Upload PPMP')
                ->modalSubmitActionLabel('Upload'),
        ];
    }
}