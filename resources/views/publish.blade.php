@extends('layouts/app')

@section('title', $video->name() . ' - ' . $client->name)

@section('content')
{{BootForm::open(['url' => Request::url(), 'method' => 'post'])}}
{!!BootForm::text('title', 'Video title')!!}
{!!BootForm::textarea('description', 'Description', $client->default_description)!!}
{!!BootForm::text('tags', 'Tags', $client->default_tags)!!}
{!!BootForm::submit('Publish')!!}
{!!BootForm::close()!!}
@endsection
