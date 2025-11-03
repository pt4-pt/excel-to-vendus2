<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FieldMappingController;

Route::get('/', function () {
    return view('welcome');
});

// Rotas para upload de produtos
Route::get('/upload', [UploadController::class, 'index'])->name('upload');
Route::post('/upload', [UploadController::class, 'store'])->name('upload.store');
Route::post('/upload/preview', [UploadController::class, 'preview'])->name('upload.preview');

// Rotas para mapeamento de campos
Route::get('/field-mappings', [FieldMappingController::class, 'index'])->name('field-mappings.index');
Route::post('/field-mappings', [FieldMappingController::class, 'store'])->name('field-mappings.store');
Route::post('/field-mappings/reset', [FieldMappingController::class, 'resetToDefault'])->name('field-mappings.reset');
Route::post('/field-mappings/add', [FieldMappingController::class, 'addMapping'])->name('field-mappings.add');
Route::delete('/field-mappings/{id}', [FieldMappingController::class, 'destroy'])->name('field-mappings.destroy');
Route::get('/api/field-mappings', [FieldMappingController::class, 'getActiveMappings'])->name('field-mappings.api');

// Rota para upload de Excel de exemplo
Route::post('/field-mappings/upload-example', [FieldMappingController::class, 'uploadExample'])->name('field-mappings.upload-example');
