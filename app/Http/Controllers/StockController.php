<?php

namespace App\Http\Controllers;

use App\Models\Quantity;
use App\Models\Plant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;

use App\Http\Controllers\Concerns\AuthorizesModule;

class StockController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'stocks';

    public function index()
    {
        $this->authorizeModule('menu');
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
