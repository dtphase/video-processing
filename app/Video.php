<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Events\VideoStatusChanged;
use File;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;
use Carbon\Carbon;

class Video extends Model
{





    protected $fillable = [
        'client_id', 'path', 'url',
    ];

    public function currentTask() {
        $s = $video->status;
        $us = $video->upload_status;



        //$statuses = ['fresh', 'moving', 'moved', 'cved', 'processing_failed', 'waiting_for_processing',  'unready', 'uploaded', 'moved', 'failed', 'published', 'failed_to_finish_publishing', 'rendering', 'marketed', 'queued', 'rendered'];
    }

    public function readableData() {
        $data = $this->cv_data;
        if($data != NULL) {
            return json_decode($data);
        } else {
            return 'failed';
        }
    }

    public function startSeconds($videoNumber) {
        $startTime = $this->readableData()[$videoNumber][0];
        $explode = explode(':', $startTime);
        $seconds = ($explode[0] * 60 * 60) + ($explode[1] * 60) + $explode[2];
        return $seconds;
    }

    public function gameLength($gameNumber) {
        $startTime = Carbon::parse($this->readableData()[$gameNumber][0]);
        $finishTime = Carbon::parse($this->readableData()[$gameNumber][1]);
        return $finishTime->diff($startTime)->format('%H:%I:%S');
    }

    public function client() {
        return Client::find($this->client_id);
    }

    public function status($newStatus) {
        $this->status = $newStatus;
        $this->save();
        //broadcast(new VideoStatusChanged($this));
    }

    public function name() {
        $path = explode('/', $this->path);
        $name = $path[count($path) -2];
        return $name;
    }

    public function path() {
        $path = explode('/', $this->path);
        $last = array_pop($path);
        $path = implode('/', $path);
        return $path;
    }

    public function mp4() {
        $path = pathinfo($this->path);
        $path = $path['dirname'] . '/' . $path['filename'] . '.mp4';
        return $path;
    }

    public function publishPath() {
        $path = $this->path() . '/Publish';
        return $path;
    }

    public function publishFilesReady() {
        $path = $this->publishPath();
        $files = File::allFiles($path);
        foreach ($files as $file)
        {
            $filePath = $file->getPathname();
            if($file->getExtension() == "mp4") {
                $vPath = $filePath;
            }

            if($file->getExtension() == "png") {
                $thumbPath = $filePath;
            }
        }
        if(isset($vPath) && isset($thumbPath)) {
            return ['video' => $vPath, 'thumb' => $thumbPath];
        } else {
            return false;
        }
    }

    public function pushNotification() {
        $optionBuilder = new OptionsBuilder();
$optionBuilder->setTimeToLive(60*20);

$notificationBuilder = new PayloadNotificationBuilder('my title');
$notificationBuilder->setBody('Hello world')
				    ->setSound('default');

$dataBuilder = new PayloadDataBuilder();
$dataBuilder->addData(['a_data' => 'my_data']);

$option = $optionBuilder->build();
$notification = $notificationBuilder->build();
$data = $dataBuilder->build();

// You must change it to get your tokens
$tokens = \DB::table('device_token')->pluck('token')->toArray();

$downstreamResponse = FCM::sendTo($tokens, $option, $notification, $data);

$downstreamResponse->numberSuccess();
$downstreamResponse->numberFailure();
$downstreamResponse->numberModification();

//return Array - you must remove all this tokens in your database
$downstreamResponse->tokensToDelete();

//return Array (key : oldToken, value : new token - you must change the token in your database )
$downstreamResponse->tokensToModify();

//return Array - you should try to resend the message to the tokens in the array
$downstreamResponse->tokensToRetry();

// return Array (key:token, value:errror) - in production you should remove from your database the tokens present in this array
$downstreamResponse->tokensWithError();
    }
}
