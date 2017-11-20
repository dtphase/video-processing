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


use Ixudra\Curl\Facades\Curl;

Route::get('/test2', function() {
    $videos = App\Video::where('upload_status', 'scheduled_for_publishing')->where('bot_updated', 1)->get();
    foreach($videos as $video) {
        //Check if video should have been published
        if($video->publish_time != NULL) {
            $dt = Carbon\Carbon::parse($video->publish_time);
            if(Carbon\Carbon::now()->gt($dt)) {
                echo 'hi';
                $status = Youtube::checkPrivacyStatus($video->url);
                dump($status);
                if($status == 'public') {
                    $video->upload_status = 'published';
                    $video->save();
                } //else {
                //    $video->upload_status = 'failed_to_finish_publishing';
                //    $video->save();
                //}
            }
        }
    }
});


Route::get('/test', function() {
    $path = \App\Video::find(23)->path;
    $framePath = explode('.', $path)[0] . 'frame.bmp';
    $folderPath = explode('.', $path)[0] . '/frames/';
    $croppedPath = explode('.', $path)[0] . 'cropped.png';
    $deadImage = '/mnt/g/Essentials/Processing/dead.png';
    $deadImage = '/mnt/g/Essentials/Processing/queue.png';
    /*$start = microtime(true);
    \App\Http\Controllers\VideoController::launchMatcher($deadImage, $framePath);
    $m = DB::table('youtube_access_tokens')->where('id', 1)->update(['access_token' => 'frame']);
    $ffprobe = \FFMpeg\FFProbe::create();
    $duration = (int)$ffprobe->format($path)->get('duration');
    for($i = 0; $i < $duration; $i+=10) {
        \App\Http\Controllers\VideoController::saveFrame($path, $framePath, $i);
        $m = DB::table('youtube_access_tokens')->where('id', 1)->update(['access_token' => 'frame']);
        while(DB::table('youtube_access_tokens')->first()->access_token == 'frame') {
            usleep(100);
        }
        $maxVal = DB::table('youtube_access_tokens')->first()->access_token;
        echo $maxVal . '<br>';
    }
    $end = microtime(true);
    echo ($end - $start).' seconds<br>';
    $m = DB::table('youtube_access_tokens')->where('id', 1)->update(['access_token' => 'shutdown']);*/
    dump($deadImage, $framePath);
});

Route::post('/storepushtoken/{token}', function($token) {
    //store push token to db
    \DB::table('device_token')->insert(['token' => $token]);
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

Route::post('video/{video}/process')->uses('VideoController@index')->name('ProcessPost');

//Route::get('youtube/login', 'VideoController@login');

//Route::get('youtube/callback', 'VideoController@callback');

Auth::routes();

Route::get('upload', 'VideoController@upload');

Route::get('logs', '\Rap2hpoutre\LaravelLogViewer\LogViewerController@index');
