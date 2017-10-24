<?php

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


Route::get('/test', function() {

});

Route::get('/', ['as' => 'home', 'uses' => function () {
    $videos = App\Video::orderBy('id', 'desc')->take(20)->get();
    return view('videos', ["videos" => $videos]);
}]);

Route::get('/video/{video}', function (App\Video $video) {
    $client = App\Client::find($video->client_id);
    return view('video', ["video" => $video, 'client' => $client]);
});

Route::get('/video/{video}/publish', function (App\Video $video) {
    $client = App\Client::find($video->client_id);
    return view('publish', ["video" => $video, 'client' => $client]);
});

Route::post('/video/{video}/publish', ['uses' => 'VideoController@schedule']);

Route::get('/clients', ['uses' => function (App\Video $video) {
    $clients = App\Client::orderBy('id', 'asc')->take(20)->get();
    return view('client.clients', ["clients" => $clients]);
}, 'as' => 'clients']);

Route::get('/clients/{client}/edit', function (App\Client $client) {

    return view('client.edit', ["client" => $client]);
});

Route::put('/clients/{client}/edit', ['as' => 'client.update', 'uses' => 'ClientController@update']);

Route::post('video/{video}/process', 'VideoController@index');

//Route::get('youtube/login', 'VideoController@login');

//Route::get('youtube/callback', 'VideoController@callback');

Auth::routes();

Route::get('upload', 'VideoController@upload');
