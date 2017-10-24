<?php

namespace App\Jobs;

use App\Video;
use App\Client;
use Youtube;
use Carbon\Carbon;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ScheduleVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $video, $path, $thumb;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Video $video, $path, $thumb)
    {
        $this->video = $video;
        $this->path = $path;
        $this->thumb = $thumb;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $video = $this->video;
        if($video->type != 'footage') {
            $attempts = 0;
            $tries = 5;
            $video->upload_status = 'uploading';
            $video->save();
            $upload = NULL;
            do {
                try {
                    $fileName = pathinfo($this->path, PATHINFO_FILENAME);
                    $upload = $this->upload($video, $this->path); //process thumbnail
                    $video->url = $upload->getVideoId();
                    $video->upload_status = 'published';
                    $video->save();
                    return;
                } catch(\Exception $e) {
                    \Log::error($e);
                    \Log::error($upload);
                    $attempts++;
                }
            } while($attempts < $tries);
            if($attempts == 5) {
                $video->upload_status = 'failed';
                $video->save();
                return;
            }
        }
    }

    protected function upload(Video $video, $filePath) {
        $client = Client::find($video->client_id);
        $dt = Carbon::now('GMT+1200');
        $pt = Carbon::parse($client->publish_time, 'GMT+1200');

        if($pt->isFuture()) {
            $time = $pt;
        } else {
            $time = $pt->addDay();
        }

        $params = [
            'title'       => $video->title,
            'description' => $video->description,
            'tags'	      => explode(',', $video->tags),
            'category_id' => 20, //gaming
            'publishAt' => $time->format('Y-m-d\TH:i:s.uP'),
        ];


        $upload = Youtube::upload($filePath, $params, 'private', $client)->withThumbnail($this->thumb);
        return $upload;
        //$upload = $youtube->insert($path, $youtube, $params, 'snippet,status');
    }
}
