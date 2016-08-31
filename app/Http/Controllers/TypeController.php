<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Requests;
use App\Models\Type;

class TypeController extends Controller {

  public function index() {
    $types = Type::all();

    return response()->json($types);
  }

  
  public function store(Request $request) {
    $inputData = $request->all();
    $newType = new Type($inputData);

    if($newType->save()) {
        return response()->json($newType);
    } else {
        return response()->validation_error($newType->errors());
    }
  }

  public function update(Request $request, $typeId) {
    $inputData = $request->only('title', 'description', 'increment');

    $type = Type::findOrFail($typeId);

    if($type->update($inputData)) {
        return response()->json($type);
    } else {
        return response()->validation_error($type->errors());
    }
  }

  public function delete(Request $request, $typeId) {
    $type = Type::findOrFail($typeId);

    if($type->countRooms()) {
        return response()->validation_error('No se puede borrar tipo mientas tenga habitaciones asignadas');
    }

    $type->delete();

    return response()->json(['message' => 'El tipo ha sido borrado']);
  }
}
