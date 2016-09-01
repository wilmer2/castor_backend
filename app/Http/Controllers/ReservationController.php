<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Client;
use App\Models\Rental;
use App\Http\Tasks\RentalTask;
use App\Http\Tasks\RoomTask;

class ReservationController extends Controller {

  public function addReservation(Request $request, RentalTask $rentalTask) {
    if($request->has('clientId')) {
        $client = Client::findOrFail($request->get('clientId'));
    } else {
        $client = Client::searchForIdentityCard($request->get('identity_card'));
    }

    $inputData = $request->all();
    $newReservation = new Rental($inputData);

    $newReservation->client_id = $client->id;
    $newReservation->reservation = 1;

    if($newReservation->save()) {
        $rentalTask->registerPayment($newReservation, $inputData['room_ids']);

        $newReservation->moveDispatch();
        return response()->json($newReservation);
    } else {
        return response()->validation_error($newReservation->errors());
    }
  }


  public function updateReservationForHour(Request $request, RentalTask $rentalTask, $rentalId) {
    $rental = Rental::findOrFail($rentalId);

    if(!$rental->reservation) {
        return response()->validation_error('El hospedaje no se puede editar');
    }

    $rental->type = 'hours';
    $rental->departure_date = null;

    $inputData = $request->only('arrival_date', 'arrival_time', 'departure_time', 'room_ids');
     
    if($rental->update($inputData)) {
       $rentalTask->registerPayment($rental, $inputData['room_ids']);
        
        $rental->moveDispatch();
        return response()->json($rental);
    } else {
        return response()->validation_error($rental->errors());
    }
    
  }
  

  public function updateReservationForDate(Request $request, RentalTask $rentalTask, $rentalId) {
    $rental = Rental::findOrFail($rentalId);

    if(!$rental->reservation) {
        return response()->validation_error('El hospedaje no se puede editar');
    }

    $rental->type = 'days';
    $rental->departure_time = createHour('12:00:00');

    $inputData = $request->only('arrival_date', 'arrival_time', 'departure_date', 'room_ids');

    if($rental->update($inputData)) {
        $rentalTask->registerPayment($rental, $inputData['room_ids']);
        
        $rental->moveDispatch();
        return response()->json($rental);
    } else {
        return response()->validation_error($rental->errors());
    }

  }

  public function getAvailableDateRoom(
    Request $request, 
    RoomTask $roomTask,
    $rentalId,
    $statDate,
    $endDate,
    $time
  ) {
      $rental = Rental::findOrFail($rentalId);
      $roomsId = $rental->getEnabledRoomsId();
      
      $roomTask->setData($statDate, $time, $endDate);

      if(!$roomTask->isValidDataQuery()) {
          return response()->validation_error($roomTask->getMessage());
      }

      $rooms = $roomTask->getRoomDateReservation($rental->id, $roomsId);

      return response()->json($rooms);
  }

  public function getAvailableHourRoom(
    Request $request, 
    RoomTask $roomTask, 
    $rentalId,
    $startDate,
    $startTime,
    $departureTime
  ) {
      $rental = Rental::findOrFail($rentalId);
      $roomsId = $rental->getEnabledRoomsId();

      $roomTask->setData($startDate, $startTime, null, $departureTime);

      if(!$roomTask->isValidDataQuery()) {
          return response()->validation_error($roomTask->getMessage());
      }

      $rooms = $roomTask->getRoomHourReservation($rental->id ,$roomsId);
      return response()->json($rooms);
  }

  public function confirmReservation(Request $request, $rentalId) {
    $rental = Rental::findOrFail($rentalId);
    $date = currentDate();

    if($rental->isCheckout()) {
        return response()->validation_error('El hospedaje ya tiene salida');
    }

    if(!$rental->reservation) {
        return response()->validation_error('La reservación  ya fue confirmada');
    }

    if($rental->arrival_date > $date) {
        return response()->validation_error('Aun no es la fecha de reservación');
    }

    $rental->state = 'conciliado';
    $rental->reservation = 0;
    $rental->forceSave();


    return response()->json(['message' => 'Reservación confirmada']);
  }

  public function getReservation($startDate, $endDate) {
    $reservations = Rental::where('reservation', 1)
    ->whereBetween('arrival_date', array($startDate, $endDate))
    ->get();

    return response()->json($reservations);
  }
}
