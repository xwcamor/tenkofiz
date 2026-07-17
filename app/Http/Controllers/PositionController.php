<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Http\Request;

class PositionController extends Controller
{
    /** Quick creation from the employee form (AJAX) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:positions,name'],
        ], [
            'name.unique' => __('That position already exists.'),
        ]);

        $position = Position::create($data);

        return response()->json(['id' => $position->id, 'name' => $position->name]);
    }
}
