@extends('layouts.app')

@section('title', 'Clients')

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
        <table>
            <tr><th>Client</th><th>Tools</th></tr>
        @foreach ($clients as $client)
            <tr>
                <td>{{ $client->name }}</td>
                <td><a href="/clients/{{ $client->id }}/edit">Edit</a></td>
            </tr>
        @endforeach
        </table>
@endsection
