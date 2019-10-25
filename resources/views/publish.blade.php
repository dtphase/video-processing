@extends('layouts/app')

@section('title', $video->name() . ' - ' . $client->name)

@section('content')
    {{$video->status}}
@if($video->upload_status == 'unready')
    {{BootForm::open(['url' => Request::url(), 'method' => 'post'])}}
    {!!BootForm::text('title', 'Video title')!!}
    {!!BootForm::textarea('description', 'Description', $client->default_description)!!}
    {!!BootForm::text('tags', 'Tags', $client->default_tags)!!}
    {!!BootForm::submit('Publish')!!}
    {!!BootForm::close()!!}
    @if($video->cv_data != NULL)
        {{$video->cv_data}}
    @endif
@else
    {{BootForm::open(['url' => Request::url(), 'method' => 'put'])}}
    {!!BootForm::text('title', 'Video title', $video->title)!!}
    {!!BootForm::textarea('description', 'Description', $video->description)!!}
    {!!BootForm::text('tags', 'Tags', $video->tags)!!}
    {!!BootForm::submit('Save changes')!!}
    {!!BootForm::close()!!}
@endif
    <a href="{{Request::url()}}/overedit">Run Overedit</a>
@endsection
