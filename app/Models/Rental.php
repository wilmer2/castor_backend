<?php

namespace App\Models;

use LaravelArdent\Ardent\Ardent;
use App\Validators\RentalValidator;
use App\Events\RentalWasAssigned;

class Rental extends Ardent {
  
  public $autoPurgeRedundantAttributes = true;
  public $extra_hour = null;
  public $checkout_room = false;
  public $old_departure = null;
  protected $rooms_validation = [];
  protected $search_date = null;

  protected $fillable = [
    'client_id',
    'move_id',
    'arrival_time',
    'departure_time',
    'arrival_date',
    'departure_date',
    'checkout_date',
    'type',
    'amount',
    'room_ids',
    'renovate_hour',
    'state',
    'reservation',
    'checkout'
  ];

  public static $rules = [
    'room_ids' => 'required|exists:rooms,id',
    'type' => 'required|in:hours,days',
    'arrival_date' => 'required_if:reservation,1|date',
    'arrival_time' => 'required_if:reservation,1|date_format:H:i:s|date_hour',
    'departure_date' => 'required_if:type,days|date|after:arrival_date',
    'renovate_hour' => 'sometimes|required|in:01:00:00,02:00:00,03:00:00,04:00:00',
    'departure_time' => 'date_format:H:i:s',
    'state' => 'required_if:reservation,1|in:conciliado,pendiente',
    'amount_renovate' => 'numeric'
  ];

  public static $customMessages = [
    'arrival_date.required_if' => 'La fecha de ingreso es obligatoria',
    'arrival_date.date' => 'La fecha de ingreso es inválida',
    'arrival_date.after' => 'La fecha ya paso',
    'departure_date.required_if' => 'La fecha de salida es obligatoria',
    'departure_date.date' => 'La fecha de salida es inválida',
    'departure_date.after' => 'La fecha de salida debe ser mayor a la fecha de entrada',
    'arrival_time.required_if' => 'La hora de ingreso es obligatoria',
    'arrival_time.date_format' => 'La hora de ingreso es inválida',
    'arrival_time.date_hour' => 'La hora ya paso',
    'departure_time.date_format' => 'La hora de salida es inválida',
    'type.required' => 'El tipo es obligatorio',
    'type.in' => 'El tipo es inválido',
    'renovate_hour.required' => 'La hora de renovacion es obligatoria',
    'renovate_hour.in' => 'La hora para renovar es inválida',
    'extra_hour.date_format' =>  'La hora de finalización es inválida', 
    'room_ids.required' => 'Las habitaciones son obligatorias',
    'room_ids.exists' => 'Alguna de las habitaciones no existe ',
    'state.required_if' => 'El estado de pago es obligatorio',
    'state.in' => 'El estado de pago no es inválido'
  ];

  public function __construct($attributes = array()) {
    parent::__construct($attributes);

    $this->purgeFilters[] = function($key) {
        $purge = ['room_ids', 'renovate_hour', 'amount_renovate'];
        return ! in_array($key, $purge);
    };
  }

  public function client() {
    return $this->belongsTo(Client::class, 'client_id');
  }

  public function rooms() {
    return $this->belongsToMany(Room::class)
     ->withTimestamps()
     ->withPivot('check_in', 'check_out', 'check_timeout', 'check_timein', 'price_base');
  }
  
  public function move() {
    return $this->belongsTo(Move::class);
  }

  public function record() {
    return $this->hasOne(Record::class);
  }

  /** Model Events */

  public function afterValidate() {
    //Se introduce valores ya que al purgar la pierden su valor
    $this->rooms_validation = $this->room_ids;
     
    if($this->renovate_hour != null) {
      $this->extra_hour = $this->renovate_hour;
    }

    if($this->state == null) {
        $this->state = 'conciliado';
    }
  }

  public function beforeSave() {
    $valid = true;
    $rentalValidator = new RentalValidator();

    if(!$this->isValidArrivalDate($rentalValidator)) {
        $this->validationErrors->add('arrival_date', 'La fecha ya paso');
        $valid = false;
    }

    if(!$this->isValidRenovateDate()) {
        $this->validationErrors->add('departure_date', 'La fecha para renovar deber ser mayor a la de salida');
        $valid = false;
    }

    if($valid && !$this->isValidTimeRooms($rentalValidator)) {
        $valid = false;
    }

    return $valid;
  }

  public function moveDispatch() {
    event(new RentalWasAssigned($this));
  }

  public function isValidArrivalDate($rentalValidator) {
    if($this->reservation) {
        return $rentalValidator->isValidDate($this->arrival_date);
    } else {
        return true;
    }
  }

  public function isValidRenovateDate() {
    if($this->old_departure != null && $this->old_departure >= $this->departure_date) {
        return false;
    } 

    return true;
  }

  public function isValidTimeRooms($rentalValidator) {
    $hourFrom = $this->getHourFrom();

    if($this->extra_hour != null || $this->type == 'hours') {
        $this->validationErrors->add('departure_time', 'Algunas de las habitaciones ya estan ocupadas por estas horas');
        $dateFrom = $this->getDateFrom();
        
        $this->setDepartureTime($hourFrom);
        $this->setDepartureDate($dateFrom, $hourFrom);
        
        $valid = $rentalValidator->isValidRoomHour(
           $this->rooms_validation,
           $hourFrom,
           $this->departure_time,
           $dateFrom,
           $this->departure_date,
           $this->id
        );

    } else {
        $this->validationErrors->add('departure_date', 'Algunas de las habitaciones ya estan ocupadas por esta fechas');

        $valid = $rentalValidator->isValidRoomDate(
           $this->rooms_validation,
           $this->arrival_date,
           $this->departure_date,
           $hourFrom,
           $this->search_date,
           $this->id
        );
     }

     return $valid;
  }

  public function getData() {
    $rooms = $this->rooms()
    ->get();

    $data = [
      'id' => $this->id,
      'arrival_time' => $this->arrival_time,
      'departure_time' => $this->departure_time,
      'arrival_date' => $this->arrival_date,
      'departure_date' => $this->departure_date,
      'checkout_date' => $this->checkout_date,
      'type' => $this->type,
      'amount' => $this->amount,
      'amount_impost' => $this->amount_impost,
      'amount_total' => $this->amount_total,
      'state' => $this->state,
      'reservation' => $this->reservation,
      'checkout' => $this->checkout,
      'rooms' => $rooms,
      'client' => $this->client,
      'record' => $this->record
    ];

    return $data;
  }

  public function addDateTime() {
    if($this->arrival_time == null && !$this->reservation) {
        $this->arrival_date = currentDate();
        $this->arrival_time = currentHour();
    }
      
  }

  public function getHourFrom() {
    if($this->extra_hour != null) {
        $hourFrom = $this->departure_time;
    } else {
        $hourFrom = $this->arrival_time;
    }

    return $hourFrom;
  }

  public function setDepartureTime($hourFrom) {
    if($this->extra_hour != null) {
        $this->departure_time = sumHour($hourFrom, $this->extra_hour);
    } else {
        $setting = getSetting();

        if($this->departure_time == null) {
            $this->departure_time = sumHour($hourFrom, $setting->time_minimum);
        }
    }

  }

  public function getDateFrom() {
     if($this->departure_date == null) {
        return $this->arrival_date;
    } else {
        return $this->departure_date;
    }
  }

  public function setDepartureDate($dateFrom, $hourFrom) {
    if(
        $hourFrom > $this->departure_time && 
        $this->departure_date == null
    ) { 
        $this->departure_date = addDay($dateFrom);
    }
  }

  public function setOldDeparture($oldType) {
    $date = currentDate();

    if(
        $this->departure_date == null || 
        $oldType == 'hours' && 
        $this->departure_date != null &&
        $this->departure_date > $date
    ) {
        $this->old_departure = $this->arrival_date;
    } else {
        $this->old_departure = $this->departure_date;
    }
    
    $this->search_date = $date;
  }

  public function isCheckout() {
    if($this->reservation) {
        return false;
    }

    $currentDate = currentDate();

    if(
        $this->departure_date == null && 
        $this->arrival_date < $currentDate ||
        $this->departure_date != null &&
        $this->departure_date < $currentDate
    ) {  
        $this->checkout = 1;
        $this->forceSave();
    }

    return $this->checkout;
  }

  public function isTimeout() {
    $date = currentDate();
    $hour = currentHour();
    
    if(!$this->reservation) {
        if(
            $this->departure_date == null && 
            $this->departure_time < $hour ||
            $this->departure_date == $date  &&
            $this->departure_time < $hour
        ) { 
            
            return true;
        }
    }

    return false;
  }

  public function reservationExpired() {
    $currentDate = currentDate();
    
    if(
        $this->reservation &&
        $this->departure_date != null &&
        $this->departure_date < $currentDate ||
        $this->reservation &&
        $this->departure_date == null &&
        $this->arrival_date < $currentDate
      ) { 
          if($this->state == 'conciliado') {
              $this->reservation = 0;
              $this->checkout = 1;
              $this->forceSave();
          } else {
              $this->delete();

              return true;
          }

          
    }

    return $this->checkout;
  }

  public function confirmCheckoutRoom() {
     $enabledRooms = $this->getEnabledRooms();

     if($enabledRooms->count() == 0) {
        $this->checkout = 1;
        $this->forceSave();
     }
  }

  public function stateRoomCheckout() {
    $date = currentDate();

    $rooms = $this->getRoomsCheckout()
    ->wherePivot('check_out', '<=', $date)
    ->where('state', 'ocupada');

    if($rooms->count() > 0) {
        $rooms = $rooms->get();

        foreach ($rooms as $room) {
            $room->state = 'mantenimiento';
            $room->save();
        }
    }
  }

  public function cancelRooms() {
    if(!$this->reservation) {
        $rooms = $this->getEnabledRooms()
        ->get();

        foreach ($rooms as $room) {
          $room->state = 'disponible';
          $room->save();
        }
    } 
  }

  public function syncRooms($roomIds, $change = false) {
    $this->rooms()->sync($roomIds, $change);
  }

  /** Model Querys */

  public function findRoom($roomId) {
    $room =  $this->rooms()
    ->where('id', $roomId)
    ->first();

    $room->type;

    return $room;
  }

  public function getRooms() {
    return $this->rooms()
    ->selectRooms()
    ->get();
  }

  public function whereInRooms($roomIds) {
    return $this->rooms()
    ->selectRooms()
    ->whereIn('rooms.id', $roomIds)
    ->get();
  }

  public function getRoomsId() {
    return $this->rooms()
    ->lists('id')
    ->toArray();
  }

  public function getEnabledRooms() {
    return $this->rooms()
    ->wherePivot('check_out', null);
  }

  public function getRoomsCheckout() {
    return $this->rooms()
    ->wherePivot('check_out', '<>', null);
  }

  public function getEnabledRoomsId() {
    return $this->getEnabledRooms()
    ->lists('id')
    ->toArray();
  }

  public function getRoomsIdCheckout() {
    return $this->getRoomsCheckout()
    ->lists('id')
    ->toArray();
  }

  public function getRoomsCheckDate($date) {
    return $this->rooms()
    ->wherePivot('check_in', '>=', $date);
  }

  public function getRoomsCheckoutDate($date) {
    return $this->rooms()
    ->wherePivot('check_out', '>', $date);
  }

  public function getHourRoomsCheckout() {
    return $this->rooms()
    ->wherePivot('check_timeout', '<>', null);
  }

  public function changeCheckoutDate($date) {
    $rooms = $this->getRoomsCheckoutDate($date);

    if($rooms->count() > 0) {
        $roomsCheckout = $rooms
        ->lists('id')
        ->toArray();

        $syncRoomsCheckout = syncDataCheckout($roomsCheckout, $date);

        $this->syncRooms($syncRoomsCheckout);
    }
  }

  public function deleteCheckRoomsDate($date) {
    $rooms = $this->getRoomsCheckDate($date);

    if($rooms->count() > 0) {
        $roomsRemove = $rooms
        ->lists('id')
        ->toArray();

        $this->detachRooms($roomsRemove);
    }
  }

  public function detachSameCheckinCheckout() {
    $roomsDetach = $this->rooms()
    ->whereRaw('check_in = check_out')
    ->wherePivot('check_timein', null);

    if($roomsDetach->count() > 0) {
        foreach ($roomsDetach as $rooms) {
          $room->state = 'disponible';
          $room->save();

          $this->detachRooms($room->id);
        }
    } 
  }

  public function detachRooms($roomIds) {
      $this->rooms()->detach($roomIds);
  }
}