<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FieldMappingController;
use App\Http\Controllers\DocumentMappingController;

// Rotas para upload de produtos
Route::get('/', [UploadController::class, 'index'])->name('upload');
Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
Route::post('/upload/preview', [UploadController::class, 'preview'])->name('upload.preview');
Route::post('/upload/preview-invoices', [UploadController::class, 'previewInvoices'])->name('upload.preview-invoices');

// Rotas para mapeamento de campos
Route::get('/field-mappings', [FieldMappingController::class, 'index'])->name('field-mappings.index');
Route::post('/field-mappings', [FieldMappingController::class, 'store'])->name('field-mappings.store');
Route::post('/field-mappings/reset', [FieldMappingController::class, 'resetToDefault'])->name('field-mappings.reset');
Route::post('/field-mappings/add', [FieldMappingController::class, 'addMapping'])->name('field-mappings.add');
Route::delete('/field-mappings/{id}', [FieldMappingController::class, 'destroy'])->name('field-mappings.destroy');
Route::get('/api/field-mappings', [FieldMappingController::class, 'getActiveMappings'])->name('field-mappings.api');

// Rotas para mapeamento de faturas (Excel)
Route::get('/document-mappings', [DocumentMappingController::class, 'index'])->name('document-mappings.index');
Route::post('/document-mappings', [DocumentMappingController::class, 'store'])->name('document-mappings.store');
Route::post('/document-mappings/reset', [DocumentMappingController::class, 'resetToDefault'])->name('document-mappings.reset');

// Rota para upload de Excel de exemplo
Route::post('/field-mappings/upload-example', [FieldMappingController::class, 'uploadExample'])->name('field-mappings.upload-example');

// Rota para obter unidades disponíveis na conta Vendus
Route::get('/api/units', [FieldMappingController::class, 'getUnits'])->name('units.api');
