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
use Carbon\Carbon;
use App\Jobs\UploadVideo;
use App\Jobs\ProcessVideo;
use App\Video;

Route::get('/test2', function() {
    \Mail::to('dtphase@gmail.com')->send(new App\Mail\ProcessingComplete());
});


Route::get('/test', function() {

});

Route::post('/storepushtoken/{token}', function($token) {
    //store push token to db
    \DB::table('device_token')->insert(['token' => $token]);
});

Route::get('/', ['as' => 'home', 'uses' => function () {
    $videos = App\Video::orderBy('id', 'desc')->whereNotIn('status', ['deleted', 'moved', 'moving'])->take(300)->get();
    return view('videos', ["videos" => $videos]);
}]);

Route::get('/video/{video}', function (App\Video $video) {
    $client = App\Client::find($video->client_id);
    return view('video', ["video" => $video, 'client' => $client]);
});

Route::get('/footage', function () {
    $clients = App\Client::get();
    $feet = [];
    foreach ($clients as $client) {
        $directory = "/mnt/g/Raw/" . $client->name;
        if(!File::exists($directory)) {
            File::makeDirectory($directory);
        }
        $files = File::allFiles($directory);
        foreach ($files as $file) {
            array_push($feet, [$client->name, $file]);
        }
    }
    return view('footage', ["feet" => $feet]);
});

//TODO: move to controller
Route::get('/footage/{file}', function ($processFile) {
    $clients = App\Client::get();
    $feet = [];
    foreach ($clients as $client) {
        $directory = "/mnt/g/Raw/" . $client->name;
        if(!File::exists($directory)) {
            File::makeDirectory($directory);
        }
        $files = File::allFiles($directory);
        foreach ($files as $file) {
            if($file->getFilename() == $processFile) {
                if($file->getExtension() == "mkv" || $file->getExtension() == "mp4") {
                    $modified = Carbon::createFromTimestamp(File::lastModified($file));
                    $now = Carbon::now();
                    \Log::info('Modified: ' . $modified);
                    \Log::info('Now: ' . $now);
                    if($now->diffInHours($modified) > -1) {
                        \Log::info($now->diffInHours($modified) . ' hours have passed since last modification, okay to begin backup');
                        //Create a new video of type footage
                        $video = new Video;
                        $video->client_id = $client->id;
                        $video->status = 'moving';
                        $video->type = 'footage';
                        $video->save();

                        //Create a directory for the footage and move the file to the new directory
                        $directory = '/mnt/g/Footage/'. $client->name . '/[Video' . $video->id . '] Footage';
                        File::makeDirectory($directory, 0777, true);
                        $filePath = $file->getPathname();
                        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
                        $newPath = $directory . '/Footage ' . $video->id . ' ['. $fileName .'].' . $file->getExtension();
                        $newPathCopy = $directory . '/Footage ' . $video->id . ' ['. $fileName .'].mp4';
                        $video->path = $newPath;
                        $video->save();
                        File::move($filePath, $newPath);
                        if(!File::exists('/mnt/g/Templates/' . $client->name)) {
                            File::makeDirectory('/mnt/g/Templates/' . $client->name);
                        }
                        $video->status('moved');

                        //Upload the footage to YouTube as a backup
                        UploadVideo::dispatch($video)->onConnection('upload');
                        ProcessVideo::dispatch($video->path, NULL, NULL, NULL, $video);

                    }
                    flash('Footage ' . $file->getFilename() . ' has been queued for processing.')->success();
                }
                return Redirect::to('footage');
            }
        }
    }
    return view('footage', ["feet" => $feet]);
});

Route::get('video/{video}/cv')->uses('VideoController@cvVideo')->name('cv');

Route::get('video/{video}/cv2')->uses('VideoController@cvRendered')->name('cv2');

Route::get('/video/{video}/publish', function (App\Video $video) {
    $client = App\Client::find($video->client_id);
    return view('publish', ["video" => $video, 'client' => $client]);
});

Route::post('/video/{video}/publish', ['uses' => 'VideoController@schedule']);

Route::get('/video/{video}/publish/overedit', ['uses' => 'VideoController@overedit']);

Route::put('/video/{video}/publish', ['uses' => 'VideoController@update']);

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
