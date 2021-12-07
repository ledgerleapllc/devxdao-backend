<?php

use App\Mail\UserAlert;
use Illuminate\Support\Facades\Mail;
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

Route::get('/test-email', function () {
    $title = 'Test send email queue';
    $body = 'body send queue email';
    Mail::to('hieuvh1234@gmail.com')->queue(new UserAlert($title, $body));
    Mail::to('hieuvh1234@gmail.com')->later(now()->addSeconds(5),new UserAlert($title, $body));
    Mail::to('dhquan1910@gmail.com')->later(now()->addSeconds(5),new UserAlert($title, $body));
    echo 'oke';
});
