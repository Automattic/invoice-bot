<?php

use App\Http\Controllers\DocsController;
use App\Http\Controllers\OAuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [OAuthController::class, 'redirect']);
Route::get('callback', [OAuthController::class, 'callback']);

Route::get('docs',[DocsController::class, 'index']);
Route::get('docs/create',[DocsController::class, 'create']);
Route::get('docs/copy',[DocsController::class, 'copy']);
Route::get('docs/export',[DocsController::class, 'export']);
Route::get('docs/setUp',[DocsController::class, 'setUp']);