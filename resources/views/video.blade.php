@extends('layouts/app')

@section('title', 'Video ' . $video->id . ' ['. $client->name .']')

@section('style')
<style>
.green {
    color: green;
}
.red {
    color: red;
}

li {
    list-style: none;
}

.cli {
    margin-bottom: 20px;
}
</style>
@endsection

@section('content')


<video width="100%" controls class="video">
    <source src="{{substr($video->mp4(), 4)}}" type="video/mp4">
    Your browser does not support the video tag.
</video>

{{BootForm::open(['url' => Request::url() . '/process', 'method' => 'post'])}}
{!!BootForm::text('name', 'Video name')!!}
{!!BootForm::text('start', 'Start', '00:00:00')!!}
{!!BootForm::text('end', 'End', '00:00:00')!!}
{!!BootForm::submit('Render')!!}
{!!BootForm::close()!!}


<h2>Game Info</h2>



@if($video->cv_data != NULL)
    @for ($i = 0; $i < count($video->readableData()); $i++)
        <ul class="cli">
            <li class="game">
                <h3>Game {{$i+1}}</h3>
                <span class="hidden">{{$video->startSeconds($i)}}</span>
            </li>
            <li>Start time: {{$video->readableData()[$i][0]}}</li>
            <li>End time: {{$video->readableData()[$i][1]}}</li>
            <li>Game length: {{$video->gameLength($i)}}</li>
            @if($video->readableData()[$i][2] == 1)
                <li>Result: <span class="green">Victory!</span></li>
            @else
                <li>Result: <span class="red">Loss</span></li>
            @endif
            <li>Score: <img src="/{{$video->readableData()[$i][3]}}" width="650px" /></li>
        </ul>
    @endfor
@endif




@endsection

@section('script')


$(".game").each(function( index ) {
    console.log(this);

    $(this).on("click", function() {
        $(".video")[0].currentTime = $(this).find(".hidden")[0].innerHTML;
        var startTime = $(this).siblings()[0].innerHTML;
        var endTime = $(this).siblings()[1].innerHTML;
        $('#start')[0].value = startTime.substr(startTime.length - 8);
        $('#end')[0].value = endTime.substr(endTime.length - 8);
    });
});

@endsection
