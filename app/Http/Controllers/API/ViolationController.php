<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ViolationName;
use Illuminate\Http\Request;

class ViolationController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 20);
        $page = $request->get('page', 1);

        $violations = ViolationName::orderBy('violation_name')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($violations);
    }

    public function show($id)
    {
        $violation = ViolationName::findOrFail($id);
        return response()->json($violation);
    }

    public function store(Request $request)
    {
        $request->validate([
            'violation_name' => 'required|string|unique:violation_names,violation_name',
            'fine_amount' => 'required|numeric|min:0',
        ]);

        $violation = ViolationName::create($request->all());

        return response()->json([
            'message' => 'Violation created successfully',
            'violation' => $violation
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $violation = ViolationName::findOrFail($id);

        $request->validate([
            'violation_name' => 'sometimes|string|unique:violation_names,violation_name,' . $id . ',violation_id',
            'fine_amount' => 'sometimes|numeric|min:0',
        ]);

        $violation->update($request->all());

        return response()->json([
            'message' => 'Violation updated successfully',
            'violation' => $violation
        ]);
    }

    public function destroy($id)
    {
        $violation = ViolationName::findOrFail($id);
        $violation->delete();

        return response()->json([
            'message' => 'Violation deleted successfully'
        ]);
    }
}