<?php

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

Route::get('/', function () {
    return view('welcome');
});

// Auth::routes();
if (config('app.install_route_enabled')) {
    Route::get('/install', 'InstallController@install');
    Route::get('/install-emailer', 'InstallController@installEmailer');
    Route::get('/clear', 'InstallController@clear');
}
