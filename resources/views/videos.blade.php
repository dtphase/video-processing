@extends('layouts.app')

@section('title', 'Video Processing')

@section('style')
<style>

    .panel td {
        border: 1px solid #464545;
        padding: 8px;
    }

    .fresh, .rendering, .rendered .uploading, .uploaded, .moving, .moved {
        color: #333;
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
</style>
@endsection



@section('content')

    <h2>Videos</h2>
        <table>
            <tr><th>Status</th><th>Client</th><th>Video</th><th>URL</th><th>Tools</th></tr>
        @foreach ($videos as $video)
            <tr>
                <td class="{{ $video->status }}">{{ $video->status }}</td>
                <td>{{ $video->client()->name }}</td>
                <td>{{$video->name()}}</td>
                <td><a href="https://www.youtube.com/watch?v={{$video->url}}">Link</a></td>
                @if($video->type == 'footage')
                    <td><a href="/video/{{ $video->id }}">Render</a></td>
                @else
                    <td><a href="/video/{{ $video->id }}/publish">Publish</a></td>
                @endif
            </tr>
        @endforeach
        </table>
@endsection
