<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Client;

class ClientController extends Controller {

  public function index() {
    $clients = Client::all();

    return response()->json($clients);
  }

  public function store(Request $request) {
    $inputData = $request->all();

    $newClient = new Client($inputData);

    if($newClient->save()) {
        return response()->json($newClient);
    } else {
        return response()->validation_error($newClient->errors());
    }
  }

  public function show($clientId) {
    $client = Client::findOrFail($clientId);

    return response()->json($client);
  }

  public function update(Request $request, $clientId) {
    $inputData = $request->only('identity_card', 'first_name', 'last_name', 'nationality');

    $client = Client::findOrFail($clientId);

    if($client->update($inputData)) {
        return response()->json($client);
    } else {
        return response()->validation_error($client->errors());
    }
  }

  public function delete($clientId) {
    $client = Client::findOrFail($clientId);

    $client->delete();

    return response()->json(['message' => 'Cliente ha sido borrado']);
  }

  public function getRentals($clientId) {
    $client = Client::findOrFail($clientId);
    $rentals = $client->rentals()
    ->where('reservation', 0)
    ->get();

    return response()->json($rentals);
  }

  public function getReservations($clientId) {
    $client = Client::findOrFail($clientId);

    $reservations = $client->rentals()
    ->where('reservation', 1)
    ->orderBy('arrival_date', 'desc')
    ->get();

    return response()->json($reservations);
  }

  public function searchForIdentityCard($identityCard) {
     $client =  Client::searchForIdentityCard($identityCard);

     return response()->json($client);
  }

  public function search(Request $request) {
    $name = $request->get('name');
    $clients = Client::name($name);

    return response()->json($clients);
  }

}
