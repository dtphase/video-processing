<?php

namespace App\Jobs;

use App\Video;
use App\Client;
use Youtube;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class UploadVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $video;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Video $video)
    {
        $this->video = $video;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $video = $this->video;
        if($video->type == 'footage') {
            $attempts = 0;
            $tries = 5;
            $video->upload_status = 'uploading';
            $video->save();
            $upload = NULL;
            do {
                try {
                    $fileName = pathinfo($video->path, PATHINFO_FILENAME);
                    $upload = $this->upload($video, $fileName);
                    $video->url = $upload->getVideoId();
                    $video->upload_status = 'uploaded';
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

    protected function upload(Video $video, $name) {
        /*$youtube = Google::make('YouTube');
        $client = Google::getClient();

        getToken();*/
        //dd($youtube);
        $client = Client::find($video->client_id);

        $params = [
            'title'       => $client->name . $name,
            'description' => '',
            'tags'	      => [$client->name],
            'category_id' => 20, //gaming
        ];


        $upload = Youtube::upload($video->path, $params, 'private');
        return $upload;
        //$upload = $youtube->insert($path, $youtube, $params, 'snippet,status');
    }
}
