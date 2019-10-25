@extends('layouts.app')

@section('title', 'Video Processing')

@section('style')
<style>



    .fresh, .rendering, .rendered .uploading, .uploaded, .moving, .moved {

    }

    .fresh {
        background: #7caeff;
    }

    .rendering {
        background: #ffac38;
    }

    .rendered, .uploaded {
        background: #2fd631;
    }

    .video {
        border: 1px solid #464545;
        padding: 8px;
    }

    .thumb {
        font-size: 11px;
        text-align: center;
    }

</style>
@endsection



@section('content')

    <h2>Videos</h2>
        @foreach ($videos as $video)
            <div class="video clearfix">
            @if($video->type == 'footage')
                <a href="/video/{{ $video->id }}">
                    <div class="col-sm-3 thumb">
                        <img src="/images/thumbs/{{ $video->id }}.png" width="100%" />
                        {{ $video->client()->name }}
                    </div>
                    <div class="col-sm-9">
                        {{$video->name()}}
                    </div>
                @else
                    <div class="col-sm-3 thumb">
                        <a href="/video/{{ $video->id }}/publish">
                        <img src="/images/thumbs/{{ $video->id }}.png" width="100%" />
                        {{ $video->client()->name }}
                    </div>
                    <div class="col-sm-9">
                            @if($video->title == NULL)
                                {{$video->name()}}
                            @else
                                {{$video->title}}
                            @endif
                    </div>
                @endif
                </a>
            </div>
        @endforeach
@endsection
