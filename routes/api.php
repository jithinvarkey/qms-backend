<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentCategoryController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\RequestController;
use App\Http\Controllers\Api\RequestAttachmentController;
use App\Http\Controllers\Api\RequestCommentController;

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

Route::middleware(['auth:api'])->group(function () {
    
});
Route::prefix('v1')->group(function () {

    Route::post('/login', [AuthController::class, 'login']);
    Route::middleware('auth:sanctum')->group(function () {

        Route::get('/issues', [QualityIssueController::class, 'index']);
        Route::get('/me', function () {
            return auth()->user()->load('roles');
        });

        Route::middleware('role:Admin|Manager|Quality Manager|User|Quality officer')->group(function () {
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

        Route::get('/documents/view/{id}', [DocumentController::class, 'view']);
        Route::get('/documents/download/{id}', [DocumentController::class, 'download']);

        // Master Data
        Route::get('statuses', 'Api\MasterController@statuses');
        Route::get('request-types', 'Api\MasterController@types');
        Route::get('departments', 'Api\MasterController@departments');

        //QMS Request related routing
        Route::apiResource('requests', 'Api\RequestController');
        Route::prefix('requests')->group(function () {

            // Requests
            Route::post('/', [RequestController::class, 'store']);
            // 🔹 View single request (with relations)
            Route::get('{id}', [RequestController::class, 'show']);

            Route::patch('{id}/submit', 'Api\RequestController@submit');
            Route::patch('{id}/update-status', 'Api\RequestController@updateStatus');

            // 🔹 Add comment
            Route::post('comments', [RequestCommentController::class, 'store']);
            Route::get('{id}/comments', [RequestCommentController::class,'index']);

            // 🔹 Approve / Reject
            Route::patch('{id}/approve', [RequestController::class, 'approve']);
            Route::patch('{id}/reject', [RequestController::class, 'reject']);

            // 🔹 Logs
            Route::get('{id}/logs', [RequestLogController::class, 'index']);

            // Attachments
            Route::post('{id}/attachments', 'Api\RequestAttachmentController@store');
            Route::delete('attachments/{id}', 'Api\RequestAttachmentController@destroy');
            Route::get('documents/preview/{id}', [RequestAttachmentController::class,'preview']);
            Route::get('documents/download/{id}', [RequestAttachmentController::class,'download']);
            Route::get('{id}/attachments', 'Api\RequestAttachmentController@list');
            

            // Approvals
            Route::post('approvals/{id}/approve', 'Api\RequestApprovalController@approve');
            Route::post('approvals/{id}/reject', 'Api\RequestApprovalController@reject');
        });
    });
});
