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
        $schedule->call(function() {
            $clients = Client::get();

            foreach ($clients as $client) {
                $directory = "/mnt/g/Raw/" . $client->name;
                if(!File::exists($directory)) {
                    File::makeDirectory($directory);
                }
                $files = File::allFiles($directory);
                foreach ($files as $file)
                {
                    if($file->getExtension() == "mkv" || $file->getExtension() == "mp4") {
                        //Move file to backup drive and copy files for editing
                        $video = new Video;
                        $video->client_id = $client->id;
                        $video->status = 'moving';
                        $video->type = 'footage';
                        $video->save();
                        $directory = '/mnt/g/Footage/'. $client->name . '/[Video' . $video->id . '] Footage';
                        File::makeDirectory($directory, 0777, true);
                        $filePath = $file->getPathname();
                        $fileName = pathinfo($filePath, PATHINFO_FILENAME);
                        $newPath = $directory . '/Footage ' . $video->id . ' ['. $fileName .'].' . $file->getExtension();
                        $video->path = $newPath;
                        $video->save();
                        File::move($file, $newPath);
                        if(!File::exists('/mnt/g/Templates/' . $client->name)) {
                            File::makeDirectory('/mnt/g/Templates/' . $client->name);
                        }
                        $video->status('moved');
                    }
                }
            }

            $videos = Video::where('upload_status', 'waiting')->get();

            foreach ($videos as $video) {
                if($video->status == 'moving') {
                    break;
                }
                $video->upload_status = 'queued';
                $video->save();
                try {
                    UploadVideo::dispatch($video)->onConnection('upload');
                } catch(\Exception $e) {
                    \Log::error('ERAIS' . $e);
                }

            }
       })->everyMinute();
       $schedule->call(function() {
           $videos = Video::where('upload_status', 'checking_files')->get();
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
