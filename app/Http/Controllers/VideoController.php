<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use FFMpeg;
use Input;
use Google;
use Youtube;
use File;
use App\Client;
use \App\Video;
use App\Jobs\ProcessVideo;
use App\Jobs\ScheduleVideo;
class VideoController extends Controller
{
    public function index(Video $video, Request $request) {
        if($video->status == 'rendering') {
            //Cancel render and readd to queue
            return \Redirect::route('home');
        }

        $validatedData = $request->validate([
            'name' => 'required|min:3|max:16',
            'start' => 'required|regex:/[0-9]*\:[0-9]*\:[0-9]*/',
            'end' => 'required|regex:/[0-9]*\:[0-9]*\:[0-9]*/',
        ]);

        $client = Client::find($video->client_id);
        $inPoint = $request->post('start');
        $outPoint = $request->post('end');
        $name = $request->post('name');
        $inArray = explode(":", $inPoint);
        $outArray = explode(":", $outPoint);
        if(count($inArray) != 3 || count($outArray) != 3) {
            throw new \Exception("Time must be in format XX:XX:XX", 1);
            return;
        } else {
            $inValue = $inArray[0] * 60 * 60 + $inArray[1] * 60 + $inArray[2];
            $outValue = $outArray[0] * 60 * 60 + $outArray[1] * 60 + $outArray[2];
            $duration = $outValue - $inValue;
            if($duration <= 0) {
                throw new \Exception("The end time must be after the start time", 1);
            }
        }

        $start = \FFMpeg\Coordinate\TimeCode::fromSeconds($inValue);
        $end = \FFMpeg\Coordinate\TimeCode::fromSeconds($duration);
        $clipFilter = new \FFMpeg\Filters\Video\ClipFilter($start, $end);

        $v = new Video();
        $v->client_id = $video->client_id;
        $v->footage_id = $video->id;
        $v->type = 'video';
        $v->upload_status = 'unready';
        $v->save();

        $newPath = explode("/", $video->path);
        $fileName = array_pop($newPath);
        $oldName = $this->get_string_between($fileName, '[', ']');
        $clientDir = '/mnt/g/Videos/'. $client->name;
        $newDir = $clientDir . '/[Video ' . $v->id . '] ' . $name;
        $newPath = $newDir . '/RenderedFootage [' . $oldName . '].mp4';

        $v->path = $newPath;
        $v->save();

        if(!File::exists($clientDir)) {
            File::makeDirectory($clientDir);
        }
        if(!File::exists($newDir)) {
            File::makeDirectory($newDir);
        }
        if(!File::exists($newDir . '/Publish')) {
            File::makeDirectory($newDir . '/Publish');
        }
        try {
            File::copy('/mnt/g/Templates/' . $client->name . '/Template.prproj', $newDir . '/Template' . $v->id . '.prproj');
            File::copy('/mnt/g/Templates/' . $client->name . '/Thumbnail.psd', $newDir . '/Thumbnail' . $v->id . '.psd');
        } catch(\Exception $e) {
            \Log::error($e . 'Missing template files');
        }

        $format = new FFMpeg\Format\Video\X264();
        $format->setAudioCodec("libmp3lame");
        $format->setKiloBitrate(16000);
        $format->setAudioKiloBitrate(256);
        /*$format->on('progress', function ($video, $format, $percentage) {
            echo "$percentage % transcoded";
        });*/


        $path = $str = substr($video->path, 1);
        ProcessVideo::dispatch($path, $clipFilter, $format, $newPath, $video);
        $video->status('rendering');


        //TODO: Video render queue
        flash('Video has been added to the render queue')->success();
        return \Redirect::route('home');

    }

    public function schedule(Video $video, Request $request) {
        $validated = $request->validate([
            'title' => 'required|min:3|max:255',
            'tags' => 'required',
            'description' => 'required',
        ]);

        $video->title = $validated['title'];
        $video->description = $validated['description'];
        $video->tags = $validated['tags'];

        if($files = $video->publishFilesReady()) {
            $video->upload_status = 'queued';
            ScheduleVideo::dispatch($video, $files['video'], $files['thumb'])->onConnection('upload');
            flash('Video successfully scheduled')->success();
        } else {
            $video->upload_status = 'checking_files';
            flash('Video added to publish queue, waiting for files')->info();
        }

        $video->save();

        return \Redirect::route('home');
    }

    public function get_string_between($string, $start, $end){
        $string = ' ' . $string;
        $ini = strpos($string, $start);
        if ($ini == 0) return '';
        $ini += strlen($start);
        $len = strpos($string, $end, $ini) - $ini;
        return substr($string, $ini, $len);
    }

    public function login() {
        $part = 'subscriberSnippet';
        $params = [
            'myRecentSubscribers' => true,
        ];

        $client = Google::getClient();
        $youtube = Google::make('YouTube');
        $client->addScope('https://www.googleapis.com/auth/youtube');
        $url = $client->createAuthUrl();

        \Redirect::to($url)->send();
        //$youtube = Google::make('YouTube');
        //dd($youtube->subscriptions->listSubscriptions($part, $params));
    }

    public function callback(\Illuminate\Http\Request $request) {
        $auth_code = $request->input('code');

        $access_token = Google::getClient()->authenticate($auth_code);
        //dump($access_token);
        \Auth::user()->youtube_token = serialize($access_token);
        \Auth::user()->save();
        //flash()->success('You\'ve signed in with YouTube successfully');
        \Redirect::to('/')->send();
    }



    protected function getToken() {
        $client = new Google_Client();
        $client->setAccessToken(unserialize(\Auth::user()->youtube_token));
        if($client->isAccessTokenExpired()) {
            $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
            \Auth::user()->youtube_token = serialize($client->getAccessToken());
            \Auth::user()->save();
        }
    }



}
