<?php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\GoogleController;
use App\Http\Controllers\ProcurementPdfController;

Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('filament.admin.pages.dashboard');
    }
    return redirect()->route('filament.admin.auth.login');
});

Route::get('social/google', [GoogleController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('social/google/callback', [GoogleController::class, 'handleGoogleCallback']);

Route::middleware(['auth'])->group(function () {
    Route::get('/procurements/{procurement}/ppmp', [ProcurementPdfController::class, 'viewPpmp'])
        ->name('procurements.ppmp');
    Route::get('/procurements/{procurement}/pr-pdf', [ProcurementPdfController::class, 'generatePrPdf'])
        ->name('procurements.pr.pdf');
    Route::get('/procurements/{procurement}/rfq-pdf', [ProcurementPdfController::class, 'generateRfqPdf'])
        ->name('procurements.rfq.pdf');
    Route::get('/procurements/{procurement}/aoq-pdf', [ProcurementPdfController::class, 'generateAoqPdf'])
        ->name('procurements.aoq.pdf');
    Route::get('/procurements/{procurement}/bac-pdf', [ProcurementPdfController::class, 'generateBacPdf'])
        ->name('procurements.bac.pdf');
    Route::get('/procurements/{procurement}/po-pdf', [ProcurementPdfController::class, 'generatePoPdf'])
        ->name('procurements.po.pdf');
    Route::get('/procurements/rfq-response/{rfqResponseId}/pdf', [App\Http\Controllers\ProcurementPdfController::class, 'generateRfqResponsePdf'])
        ->name('procurements.rfq-response.pdf');
});