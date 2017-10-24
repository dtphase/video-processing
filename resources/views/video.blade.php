@extends('layouts/app')

@section('title', 'Video ' . $video->id . ' ['. $client->name .']')

@section('content')
{{BootForm::open(['url' => Request::url() . '/process', 'method' => 'post'])}}
{!!BootForm::text('name', 'Video name')!!}
{!!BootForm::text('start', 'Start', '00:00:00')!!}
{!!BootForm::text('end', 'End', '00:00:00')!!}
{!!BootForm::submit('Render')!!}
{!!BootForm::close()!!}
@endsection
