<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use FFMpeg;
use Input;
use Google;
use Youtube;
use File;
use Image;
use App\Client;
use \App\Video;
use App\Jobs\ProcessVideo;
use App\Jobs\ScheduleVideo;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;


class VideoController extends Controller
{
    public function index(Video $video, Request $request) {

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

    public function marketVideo($video) {
        $video->upload_status = 'marketed';
        $video->save();
    }

    public static function saveFrame($pathToVideo, $pathToSave, $second) {
        FFMpeg::fromDisk('root')
        ->open($pathToVideo)
        ->getFrameFromSeconds($second)
        ->export()
        ->toDisk('root')
        ->save($pathToSave);
    }

    public static function saveFrames($pathToVideo, $pathToSave) {
        dump($pathToVideo, $pathToSave);
        $video = FFMpeg::fromDisk('root')->open($pathToVideo);

        $video->filters()
        ->extractMultipleFrames(\FFMpeg\Filters\Video\ExtractMultipleFramesFilter::FRAMERATE_EVERY_10SEC, $pathToSave)
        ->synchronize();

        $format = new FFMpeg\Format\Video\X264();
        $format->setAudioCodec("libmp3lame");
        $format->setKiloBitrate(16000);
        $format->setAudioKiloBitrate(256);

        $video->save($format, $pathToSave . 'newfile.mp4');
    }

    public static function launchMatcher($template, $imageToMatch) {
        $process = new Process('python /mnt/c/Users/dt/code/CVPy/compareImages.py "'. $template . '" "' . $imageToMatch . '"');
        //dd('python /mnt/c/Users/dt/code/CVPy/compareImages.py "'. $template . '" "' . $imageToMatch . '"');
        $process->start();


        /*if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return $process->getOutput();*/
    }

    public static function cropImageForTemplate($templateID, $imagePath, $croppedPath) {
        $img = Image::make($imagePath);
        /*if($templateID == 'death') {
            if($img->width() > 1900) {
                $img->crop(100, 100, 25, 25);
            } else {
                $img->crop(100, 100, 640-50, 545-50);
            }
        }
        if($templateID == 'pick') {

        }*/
        $img->resize(640, 480);
        $img->save($croppedPath);
    }

    public function processFootage() {
        $path = \App\Video::find(23)->path;
        $framePath = explode('.', $path)[0] . 'frame.png';
        $croppedPath = explode('.', $path)[0] . 'cropped.png';
        $deadImage = '/mnt/g/Essentials/Processing/dead.png';
        $pickImage = '/mnt/g/Essentials/Processing/pick.png';
        $queueImage = '/mnt/g/Essentials/Processing/queue.png';
        $ffprobe = \FFMpeg\FFProbe::create();
        $duration = (int)$ffprobe->format($path)->get('duration');

        $deathFrames = [];
        $pickFrames = [];
        $queueFrames = [];
        $deathTimes = [];
        $pickTimes = [];
        $queueTimes = [];
        for($i=400; $i < $duration; $i+=10) { //test 400
            $this->saveFrame($path, $framePath, $i);
            //$this->cropImageForTemplate('death', $framePath, $croppedPath);
            $maxVal = $this->getImageMatchValue($deadImage, $framePath);
            if($maxVal > 0.6) {
                //confirm death or spawn
                $deathFrames = [];
                for($j=-30; $j <= 30; $j++) {
                    $seconds = $i + $j;
                    if($seconds > 0) {
                        $this->saveFrame($path, $framePath, $seconds);
                        //$this->cropImageForTemplate('death', $framePath, $croppedPath);
                        $maxVal = $this->getImageMatchValue($deadImage, $framePath);
                        if($maxVal > 0.6) {
                            array_push($deathFrames, $seconds);
                        }
                    }
                }
                if(count($deathFrames) > 0) {
                    $start = min($deathFrames);
                    $end = max($deathFrames);
                    if($end > $i) {
                        $i = $end;
                    }
                    array_push($deathTimes, 'Death Time: [' . sprintf('%02d:%02d:%02d', ($start/3600),($start/60%60), $start%60) . ',' . sprintf('%02d:%02d:%02d', ($end/3600),($end/60%60), $end%60) . ']');
                }
            } else {
                //$this->cropImageForTemplate('pick', $framePath, $croppedPath);
                $maxVal = $this->getImageMatchValue($pickImage, $framePath);
                $pickFrames = [];
                if($maxVal > 0.6) {
                    for($j=-30; $j <= 60; $j++) {
                        $seconds = $i + $j;
                        if($seconds > 0) {
                            $this->saveFrame($path, $framePath, $seconds);
                            //$this->cropImageForTemplate('pick', $framePath, $croppedPath);
                            $maxVal = $this->getImageMatchValue($deadImage, $framePath);
                            if($maxVal > 0.6) {
                                array_push($pickFrames, $seconds);
                            } else {
                                break;
                            }
                        }
                    }
                    if(count($pickFrames) > 0) {
                        $start = min($pickFrames);
                        $end = max($pickFrames);
                        if($end > $i) {
                            $i = $end;
                        }
                        array_push($pickTimes,  'Picking Time: [' . sprintf('%02d:%02d:%02d', ($start/3600),($start/60%60), $start%60) . ',' . sprintf('%02d:%02d:%02d', ($end/3600),($end/60%60), $end%60) . ']');
                    }
                } else {
                    //$this->cropImageForTemplate('queue', $framePath, $croppedPath);
                    $maxVal = $this->getImageMatchValue($pickImage, $framePath);
                    $queueFrames = [];
                    if($maxVal > 0.6) {
                        for($j=-30; $j <= 600; $j++) {
                            $seconds = $i + $j;
                            if($seconds > 0) {
                                $this->saveFrame($path, $framePath, $seconds);
                                //$this->cropImageForTemplate('queue', $framePath, $croppedPath);
                                $maxVal = $this->getImageMatchValue($deadImage, $framePath);
                                if($maxVal > 0.6) {
                                    array_push($queueFrames, $seconds);
                                } else {
                                    break;
                                }
                            }
                        }
                        if(count($queueFrames) > 0) {
                            $start = min($queueFrames);
                            $end = max($queueFrames);
                            if($end > $i) {
                                $i = $end;
                            }
                            array_push($queueTimes,  'Queue Time: [' . sprintf('%02d:%02d:%02d', ($start/3600),($start/60%60), $start%60) . ',' . sprintf('%02d:%02d:%02d', ($end/3600),($end/60%60), $end%60) . ']');
                        }
                    }
                }
            }
        }
        dump($deathTimes);
        echo '<br>';
        dump($pickTimes);
        echo '<br>';
        dump($queueTimes);
        echo '<br>';
    }
}
