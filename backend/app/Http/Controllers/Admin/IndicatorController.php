<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Indicator;

class IndicatorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $indicators = Indicator::with('category')->get();
        return response()->json($indicators);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|max:20',
            'description' => 'nullable|max:300',
            'unit' => 'required|max:20',
            'category_id' => 'required|exists:categories,id'
        ]);

        $indicator = Indicator::create($validated);
        return response()->json($indicator, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Indicator $indicator)
    {
        return response()->json($indicator);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Indicator $indicator)
    {
        $validated = $request->validate([
            'name' => 'required|max:20',
            'description' => 'nullable|max:300',
            'unit' => 'required|max:20',
            'category_id' => 'required|exists:categories,id'
        ]);

        $indicator->update($validated);
        return response()->json($indicator);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Indicator $indicator)
    {
        $indicator->delete();
        return response()->json(['message' => 'Indikator berhasil dihapus']);
    }
}
