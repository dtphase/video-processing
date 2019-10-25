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

    protected $path, $clipFilter, $format, $newPath, $video, $v;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($path, $clipFilter, $format, $newPath, Video $video, Video $v = NULL)
    {
        $this->path = $path;
        $this->clipFilter = $clipFilter;
        $this->format = $format;
        $this->newPath = $newPath;
        $this->video = $video;
        $this->v = $v;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(substr($this->path, -4) == '.mkv') {
            $this->mkvToMp4();
        }
        if($this->v != NULL) {
            FFMpeg::fromDisk('root')->open($this->path)
            ->addFilter($this->clipFilter)
            ->addFilter(function ($filters) {
                $filters->framerate(new \FFMpeg\Coordinate\FrameRate(60), 250);
            })
            ->export()
            ->toDisk('root')
            ->inFormat($this->format)
            ->save($this->newPath);
            $this->cv($this->v);
        } else {
            $this->cv($this->video);
        }
    }

    protected function mkvToMp4() {
        $newPath = substr_replace($this->path, '.mp4', -4);
        $process = new \Symfony\Component\Process\Process('ffmpeg -i "'.$this->path.'" -vcodec copy -acodec copy "' . $newPath . '"');
        $process->setTimeout(4*60*60);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new \Symfony\Component\Process\Exception\ProcessFailedException($process);
            return;
        } else {
            $this->video->path = substr_replace($this->path, '.mp4', -4);
            $this->video->status('copied');
            $this->video->save();
        }
    }

    protected function cv(Video $video) {
        $client = $video->client();
        $ffprobe = \FFMpeg\FFProbe::create();
        $duration = $ffprobe->format($video->path)->get('duration');
        //TODO: Remove hardcoding
        $args = '';
        if($client->id == 1) {
            $args .= 'KR ';
        } else {
            $args .= 'EN ';
        }
        $args .= (int)$duration*60 . ' ';
        $args .= $client->id . ' ';
        $args .= $video->id;

        //Get thumbnail for site
        if($duration > 120) { //2 mins
            $v = \FFMpeg::fromDisk('root')->open($video->path);
            $v
            ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(60)) //1 mins
            ->save('/mnt/c/Users/dt/code/miharo/public/images/thumbs/'. $video->id . '.png');
        }
        if ($video->type == "footage") {
            $process = new \Symfony\Component\Process\Process('python /mnt/g/Scripts/Overspy.py "'. $video->path .'" ' . $args);
            
            
        } else {
            $process = new \Symfony\Component\Process\Process('python /mnt/g/Scripts/Overedit.py "'. $video->path .'" ' . $args);
            \Log::info('python /mnt/g/Scripts/Overedit.py "'. $video->path .'" ' . $args);
        }

        $process->setTimeout(6*60*60);
        $process->run();
        if (!$process->isSuccessful()) {
            \Mail::to('dtphase@gmail.com')->send(new \App\Mail\ProcessingComplete('Render '.$video->path.' failed to process'));
            throw new \Symfony\Component\Process\Exception\ProcessFailedException($process);
        } else {
            \Mail::to('dtphase@gmail.com')->send(new \App\Mail\ProcessingComplete('Render '.$video->path.' processed successfully'));
            $video->status('cved');
        }

        $video->cv_data = $process->getOutput();
        $video->save();
    }
}
