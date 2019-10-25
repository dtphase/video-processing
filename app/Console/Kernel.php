<?php

namespace App\Console;

use File;
use Log;
use Youtube;
use App\Video;
use App\Client;
use Carbon\Carbon;
use App\Jobs\UploadVideo;
use App\Jobs\ScheduleVideo;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->call(function() {
        //     $clients = Client::get();
        //
        //     foreach ($clients as $client) {
        //         $directory = "/mnt/g/Raw/" . $client->name;
        //         if(!File::exists($directory)) {
        //             File::makeDirectory($directory);
        //         }
        //         $files = File::allFiles($directory);
        //         foreach ($files as $file) {
                    
        //
        //                         //Upload the footage to YouTube as a backup
        //                         UploadVideo::dispatch($video)->onConnection('upload');
        //
        //                         if($file->getExtension() == "mkv") {
        //                             $process = new \Symfony\Component\Process\Process('ffmpeg -i "'.$newPath.'" -vcodec copy -acodec copy "' . $newPathCopy . '"');
        //                             $process->setTimeout(4*60*60);
        //                             $process->run();
        //                             if (!$process->isSuccessful()) {
        //                                 throw new \Symfony\Component\Process\Exception\ProcessFailedException($process);
        //                                 return;
        //                             } else {
        //                                 $video->status('copied');
        //                                 $newPath = $newPathCopy;
        //                             }
        //                         }
        //
        //
        //
        //                         //Get duration of footage
        //                         $ffprobe = \FFMpeg\FFProbe::create();
        //                         $duration = $ffprobe->format($newPath)->get('duration');
        //
        //                         //Get thumbnail for site
        //                         if($duration > 600) { //10 mins
        //                             $v = \FFMpeg::fromDisk('root')->open($newPath);
        //                             $v
        //                             ->frame(\FFMpeg\Coordinate\TimeCode::fromSeconds(540)) //9 mins
        //                             ->save('/mnt/c/Users/dt/code/miharo/public/images/thumbs/'. $video->id . '.png');
        //                         }
        //
        //                         //TODO: Remove hardcoding
        //                         $args = ' "' . $newPath . '" ';
        //                         if($client->id == 1) {
        //                             $args .= 'KR ';
        //                         } else {
        //                             $args .= 'EN ';
        //                         }
        //                         $args .= (int)$duration*60 . ' ';
        //                         $args .= $client->id . ' ';
        //                         $args .= $video->id;
        //
        //                         //Run python footage analysis
        //                         $process = new \Symfony\Component\Process\Process('python /mnt/g/Scripts/Overspy.py' . $args);
        //                         $process->setTimeout(6*60*60);
        //                         $process->run();
        //                         if (!$process->isSuccessful()) {
        //                             \Mail::to('dtphase@gmail.com')->send(new \App\Mail\ProcessingComplete('Footage failed to process'));
        //                             throw new \Symfony\Component\Process\Exception\ProcessFailedException($process);
        //                         } else {
        //                             \Mail::to('dtphase@gmail.com')->send(new \App\Mail\ProcessingComplete('Footage processed'));
        //                             $video->status('cved');
        //                         }
        //
        //                         $video->cv_data = $process->getOutput();
        //                         $video->save();
        //
        //
        //                     }
        //                 }
        //             }
        //         }
        //     })->everyMinute();

            // $videos = Video::where('upload_status', 'waiting')->where('status','<>','deleted')->get();
            //
            // foreach ($videos as $video) {
            //     if($video->status == 'moving') {
            //         break;
            //     }
            //     $video->upload_status = 'queued';
            //     $video->save();
            //     try {
            //         UploadVideo::dispatch($video)->onConnection('upload');
            //     } catch(\Exception $e) {
            //         \Log::error('ERAIS' . $e);
            //     }
            //
            // }

       $schedule->call(function() {
           $videos = Video::where('upload_status', 'checking_files')->where('status', '<>', 'deleted')->get();
           foreach($videos as $video) {
               if($files = $video->publishFilesReady()) {
                   $video->upload_status = 'queued';
                   ScheduleVideo::dispatch($video, $files['video'], $files['thumb'])->onConnection('upload');
               }
           }
       })->everyMinute();
       $schedule->call(function() {
           $publish = '/mnt/g/Publish/';
           $files = File::allFiles($publish);
           foreach($files as $file) {
               if($file->getExtension() == "prproj") {
                   if($file->getRelativePath() == '') {
                       return;
                   }
                   $project = $file;
               }
               if($file->getExtension() == "mp4") {
                   $v = $file;
               }
           }
           if(isset($v) && isset($project)) {
               $id = preg_replace('/\D/', '', $project->getFilename());
               if($video = Video::find($id)) {
                   $new = $video->publishPath() . '/Publish'. $id . '.mp4';
                   File::move($v, $new);
                   File::deleteDirectory($project->getPath());
               }
           }

           $videos = '/mnt/g/Videos/';
           $files = File::allFiles($videos);
           foreach($files as $file) {
               if($file->getExtension() == "prproj") {
                   $rp = $file->getRelativePath();
                   $path = explode('/', $rp);
                   $last = array_pop($path);
                   $np = implode('/', $path);
                   if($last == 'Publish') {
                       File::copy($file, $publish . $file->getFilename());
                       File::move($file, $videos . $np . '/' . $file->getFilename());
                       return;
                   }
               }
           }
       })->everyMinute();
       //Check if waiting_for_processing videos have processed
        $schedule->call(function() {
            $videos = Video::where('upload_status', 'waiting_for_processing')->where('status', '<>', 'deleted')->get();
            foreach($videos as $video) {
                $status = Youtube::checkProcessingStatus($video->url);
                if($status == 'succeeded') {
                    $client = $video->client();
                    $process = new \Symfony\Component\Process\Process('/mnt/c/Windows/System32/cmd.exe /c start "" "C:/Program Files (x86)/Google/Chrome/Application/chrome.exe" -incognito --new-window "https://www.youtube.com/my_videos?o=U^&runscript=true^&end=' . $client->end_screen . '^&video_id=' . $video->url . '^&name='. $client->name . '"');
                    $process->run();
                    if (!$process->isSuccessful()) {
                        throw new \Symfony\Component\Process\Exception\ProcessFailedException($process);
                    } else {
                        $video->upload_status = 'scheduled_for_publishing';
                        $video->save();
                    }
                }
            }

        })->everyMinute();

        $schedule->call(function() {
            $videos = \App\Video::where('upload_status', 'scheduled_for_publishing')->where('status', '<>', 'deleted')->get();
            foreach($videos as $video) {
                //Check if video should have been published
                if($video->publish_time != NULL) {
                    $dt = Carbon::parse($video->publish_time);
                    if(Carbon::now()->gt($dt)) {
                        $status = Youtube::checkPrivacyStatus($video->url);
                        if($status == 'public') {
                            $video->upload_status = 'published';
                            $video->save();
                        } else {
                            $video->upload_status = 'published';
                            $video->save();
                        }
                    }
                }
            }
        })->everyMinute();//->everyTenMinutes();

        $schedule->call(function() {
            $videos = \App\Video::where('status', 'moved')->get();
            foreach($videos as $video) {
                //Check if video should have been published
                if(!file_exists($video->path)) {
                    $video->status('deleted');
                }
            }
        })->hourly();//->everyTenMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
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
