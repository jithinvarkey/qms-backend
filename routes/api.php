<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentTypeController;

/*
  |--------------------------------------------------------------------------
  | API Routes
  |--------------------------------------------------------------------------
  |
  | Here is where you can register API routes for your application. These
  | routes are loaded by the RouteServiceProvider within a group which
  | is assigned the "api" middleware group. Enjoy building your API!
  |
 */


// routes/api.php
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/issues', [QualityIssueController::class, 'index']);
    Route::get('/me', function () {
        return auth()->user()->load('roles');
    });

    Route::middleware('role:admin')->group(function () {
        Route::post('/issues', [QualityIssueController::class, 'store']);
    });
    //    Document category handling
    Route::get('/document-categories', [DocumentCategoryController::class, 'index']);
    Route::post('/document-categories', [DocumentCategoryController::class, 'store']);
    Route::put('/document-categories/{id}', [DocumentCategoryController::class, 'update']);
    Route::patch('/document-categories/{id}/status', [DocumentCategoryController::class, 'toggleStatus']);
    Route::get('/document-categories/dropdown', [DocumentCategoryController::class, 'dropdown']);
    //    Document type handling
    Route::get('/document-types', [DocumentTypeController::class, 'index']);
    Route::post('/document-types', [DocumentTypeController::class, 'store']);
    Route::put('/document-types/{id}', [DocumentTypeController::class, 'update']);
    Route::patch('/document-types/{id}/status', [DocumentTypeController::class, 'toggleStatus']);
    Route::get('/document-types/dropdown', [DocumentTypeController::class, 'dropdown']);
    //    Document  handling
    Route::get('/documents', [DocumentController::class, 'index']);
    Route::post('/documents', [DocumentController::class, 'store']);
    Route::put('/documents/{id}', [DocumentController::class, 'update']);
    Route::delete('/documents/{id}', [DocumentController::class, 'destroy']);

    Route::patch('/documents/{id}/submit', [DocumentController::class, 'submitForReview']);
    Route::patch('/documents/{id}/approve', [DocumentController::class, 'approve']);
    Route::patch('/documents/{id}/reject', [DocumentController::class, 'reject']);
});

