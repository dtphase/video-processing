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
    {!!BootForm::open(['model' => $client, 'store' => 'ClientController@store', 'update' => 'ClientController@update'])!!}
    {!!BootForm::text('name', 'Client name')!!}
    {!!BootForm::text('publish_time')!!}
    {!!BootForm::text('default_tags')!!}
    {!!BootForm::textarea('default_description')!!}
    {!!BootForm::submit('Save')!!}
    {!!BootForm::close()!!}

    <a href="/youtube/login/{{ $client->id }}">Connect YouTube</a>
@endsection
