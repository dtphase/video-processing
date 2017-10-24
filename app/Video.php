<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use App\Events\VideoStatusChanged;
use File;

class Video extends Model
{
    protected $fillable = [
        'client_id', 'path', 'url',
    ];

    public function client() {
        return Client::find($this->client_id);
    }

    public function status($newStatus) {
        $this->status = $newStatus;
        $this->save();
        broadcast(new VideoStatusChanged($this));
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
}
