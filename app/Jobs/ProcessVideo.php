<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

use FFMpeg;
use App\Video;

class ProcessVideo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $path, $clipFilter, $format, $newPath, $video;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($path, $clipFilter, $format, $newPath, Video $video)
    {
        $this->path = $path;
        $this->clipFilter = $clipFilter;
        $this->format = $format;
        $this->newPath = $newPath;
        $this->video = $video;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        FFMpeg::fromDisk('root')->open($this->path)
        ->addFilter($this->clipFilter)
        ->addFilter(function ($filters) {
            $filters->framerate(new \FFMpeg\Coordinate\FrameRate(60), 250);
        })
        ->export()
        ->toDisk('root')
        ->inFormat($this->format)
        ->save($this->newPath);



        $this->video->status('rendered');

        
    }
}
