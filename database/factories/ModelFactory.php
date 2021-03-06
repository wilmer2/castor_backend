<?php

/*
|--------------------------------------------------------------------------
| Model Factories
|--------------------------------------------------------------------------
|
| Here you may define all of your model factories. Model factories give
| you a convenient way to create models for testing and seeding your
| database. Just tell the factory how a default model should look.
|
*/

$factory->define(App\Models\Setting::class, function (Faker\Generator $faker) {
   $minimumHour = '04:00';

   return [
    'price_day' => 3400,
    'price_hour' => 600,
    'time_minimum' => createHour($minimumHour),
    'active_impost' => 1,
    'impost' => 12,
    'name' => 'Hotel Castor',
    'rif' => 'v123456789'
  ];
});

$factory->defineAs(App\Models\Role::class, 'admin', function (Faker\Generator $faker) {
   return [
     'name' => 'admin',
     'display_name' => 'administrador'
   ];
});

$factory->defineAs(App\Models\Role::class, 'user', function (Faker\Generator $faker) {
   return [
     'name' => 'user',
     'display_name' => 'usuario'
   ];
});

$factory->defineAs(App\Models\Role::class, 'super', function (Faker\Generator $faker) {
   return [
     'name' => 'super',
     'display_name' => 'super administrador'
   ];
});

$factory->define(App\Models\User::class, function (Faker\Generator $faker) {
   return [
     'name' => $faker->name,
     'email' => $faker->email,
     'password' => bcrypt('123456')
   ];
});

$factory->define(App\Models\Client::class, function (Faker\Generator $faker) {
   return [
     'first_name' => $faker->firstNameMale,
     'last_name' => $faker->lastName,
     'nationality' => $faker->randomElement(['E', 'V']),
     'identity_card' => $faker->bankRoutingNumber
   ];
});

$factory->defineAs(App\Models\Type::class, 'basic', function (Faker\Generator $faker) {
  return [
    'title' => 'Basica',
    'description' => 'Habitación con cama matrimonial, tv cable y aire acondicionado',
    'img_url' => 'http://castor_backend/img/default/default_room.jpg'
  ];

});

$factory->defineAS(App\Models\Type::class, 'special', function (Faker\Generator $faker) {
  return [
    'title' => 'Especial',
    'description' => 'Habitación con cama matrimonial, tv cable, aire acondicionado y calentador',
    'increment' => 200,
    'img_url' => 'http://castor_backend/img/default/default_room.jpg'
  ];
});



