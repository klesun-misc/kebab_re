<?php

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

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

Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});
Route::get('/bet-spice', function (Request $request) {
    return \App\Versioned\JsApiController::betSpice($request);
});
Route::get('/start-battle', function (Request $request) {
    return \App\Versioned\JsApiController::startBattle($request);
});