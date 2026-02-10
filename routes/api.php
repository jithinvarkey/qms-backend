<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentCategoryController;

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
    
    Route::get('/document-categories', [DocumentCategoryController::class, 'index']);
    Route::post('/document-categories', [DocumentCategoryController::class, 'store']);
    Route::put('/document-categories/{id}', [DocumentCategoryController::class, 'update']);
    Route::patch('/document-categories/{id}/status', [DocumentCategoryController::class, 'toggleStatus']);
    
    Route::get('/documents', [DocumentController::class, 'index']);

    
});

