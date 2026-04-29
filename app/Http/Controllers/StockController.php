<?php

namespace App\Http\Controllers;

use App\Models\Quantity;
use App\Models\Plant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

class StockController extends Controller
{
    public function index()
    {
        $plantId = session('active_plant_id');
        
        $stocks = Quantity::where('plant_id', $plantId)
            ->with(['product', 'plant', 'uom'])
            ->latest('date')
            ->get();

        $plants = Plant::all();

        return Inertia::render('Stocks/Index', [
            'stocks' => $stocks,
            'plants' => $plants
        ]);
    }
}
