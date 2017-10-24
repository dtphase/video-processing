<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Client;

class ClientController extends Controller
{
    public function update(Client $client, Request $request) {

        $timeE = explode(":", $request->input('publish_time'));
        if(count($timeE) != 3) {
            throw new \Exception("Time must be in format XX:XX:XX", 1);
        }

        $client->name = $request->input('name');
        $client->publish_time = $request->input('publish_time');
        $client->default_tags = $request->input('default_tags');
        $client->default_description = $request->input('default_description');

        $client->save();

        flash($client->name . ' successfully edited')->success();
        return \Redirect::route('clients');
    }

    public function storeToken(Client $client) {

    }
}
