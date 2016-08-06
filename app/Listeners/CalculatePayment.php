<?php

namespace App\Listeners;

use App\Events\RentalWasAssigned;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Carbon\Carbon;

class CalculatePayment
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  RentalWasAssigned  $event
     * @return void
     */
    public function handle(RentalWasAssigned $event) {
      $rental = $event->rental;
      $setting = getSetting();
      $rooms = $rental->rooms()
      ->selectRooms()
      ->get();
      
      if($rental->type == 'days') {
          if($rental->extra_hour != null) {
             $amountRental = $this->calculateAmountDayTimeExtra($rental, $setting);
          } else {
             $amountRental = $this->calculateAmountDay($rental, $setting, $rooms);
          }
          
      } else {
          $amountRental = $this->calculateAmountTime($rental, $setting, $rooms);
      }

      $amount = $this->calculateDiscount($amountRental, $rental->discount);
      $impost = $setting->calculateImpost($amount);
      $total = $amount + $impost;

      $rental->amount = $amount;
      $rental->amount_impost = $impost;
      $rental->amount_total = $total;

      $rental->forceSave();
    }

    public function calculateAmountDay($rental, $setting, $rooms) {
      if($rental->checkout_date != null) {
          $endDate = Carbon::parse($rental->checkout_date);
      } else {
          $endDate = Carbon::parse($rental->departure_date);
      }

      $amount = 0;  

      foreach ($rooms as $room) {

          if($room->pivot->check_in != null) {
              $startDate = Carbon::parse($room->pivot->check_in);
          } else {
              $startDate = Carbon::parse($rental->arrival_date);
          }
             
          if($room->pivot->check_out != null) {
              $checkOut = Carbon::parse($room->pivot->check_out);

              $days = $startDate->diff($checkOut)->days;
          } else {
              $days = $startDate->diff($endDate)->days;
          }
          
          $amountExtra = $room->increment + $setting->price_day;
          $amountPerRoom = $amountExtra * $days;

          $amount += $amountPerRoom;
      }

      return $amount;
    }

    public function calculateAmountTime($rental, $setting, $rooms) {
      $amount = 0;

      foreach ($rooms as $room) {
        

        if($room->pivot->check_timeout != null) {
            $departureTime = $room->pivot->check_timeout;
        } else {
            $departureTime = $rental->departure_time;
        }

        if($rental->departure_date != null) {
            $toTime = strtotime($rental->departure_date.' '.$departureTime);
        } else {
            $toTime = strtotime($rental->arrival_date.' '.$departureTime);  
        }

        $fromTime = strtotime($rental->arrival_date.' '.$rental->arrival_time);
        $time = round(abs($fromTime - $toTime) / 60,2);
        $totalTime = ceil($time * (1/60));

        $amountPerHour = $totalTime * $setting->price_hour;
        $amountIncrement = $amountPerHour + $room->increment;

        $amount += $amountIncrement;
      }

      return $amount;
    }

    public function calculateAmountDayTimeExtra($rental, $setting) {
      $totalTime = explode(':', $rental->extra_hour);
      $extraHours = $totalTime[0];
      $amountPerHour = $extraHours * $setting->price_hour;
      $amountAccumulative = $rental->amount + $amountPerHour; 

      $rental->extra_hour = null;
      return $amountAccumulative;
    }

    public function calculateDiscount($amount, $discount) {
      $result = $amount - $discount;

      if($result < 1) {
          $result = 0;
      }

      return $result;
    }

}
