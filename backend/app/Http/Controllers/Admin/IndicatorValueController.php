<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Region;

class IndicatorValueController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $values = IndicatorValue::with(['indicator', 'region'])->get();
        return response()->json($values);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'indicator_id' => 'required|exists:indicators, id',
            'region_id' => 'required|exists:regions,id',
            'year' => 'required|integer',
            'value' => 'required|numeric'
        ]);

        $value = IndicatorValue::create($validated);
        return response()->json($value, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(IndicatorValue $indicatorValue)
    {
        return response()->json($indicatorValue->load(['indicator', 'region']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, IndicatorValue $indicatorValue)
    {
        $validated = $request->validate([
            'indicator_id' => 'required|exists:indicators, id',
            'region_id' => 'required|exists:regions,id',
            'year' => 'required|integer',
            'value' => 'required|numeric'
        ]);

        $indicatorValue->update($validated);
        return response()->json($indicatorValue);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(IndicatorValue $indicatorValue)
    {
        $indicatorValue->delete();
        return response()->json(['message' => 'Data indikator berhasil dihapus']);
    }
}
