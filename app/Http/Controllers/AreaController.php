<?php

namespace App\Http\Controllers;

use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /** Quick creation from the employee form (AJAX) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('areas', 'name')->where('company_id', current_company_id())],
        ], [
            'name.unique' => __('That area already exists.'),
        ]);

        $area = Area::create($data);

        return response()->json(['id' => $area->id, 'name' => $area->name]);
    }
}
