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

    <h2>Footage</h2>
    <ul>
    @foreach ($feet as $footage)
        <li>{{$footage[0].'/'.$footage[1]->getFilename()}} <a href="/footage/{{$footage[1]->getFilename()}}">Process</a></li>
    @endforeach
    </ul>
@endsection
