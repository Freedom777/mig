<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Face;
use App\Models\Image;
use App\Models\ImageGeolocationAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApiFilterController extends Controller
{


    public function index() : JsonResponse
    {
         /*
         {
          "people": ["Oleg", "Anna", "Ivan"],
          "cities": ["Berlin", "Munich", "Kyiv"],
          "tags": ["Vacation", "Work", "Family"],
          "dateRange": [2018, 2025]
        }
        */
        $data = [
            'people' => Face::distinct()->pluck('name'),
            'cities' => ImageGeolocationAddress::getCitiesList(),
            'tags' => Image::distinct()->pluck('path'),
            'dateRange' => [
                Image::min(DB::raw('YEAR(updated_at_file)')),
                Image::max(DB::raw('YEAR(updated_at_file)')),
            ],
        ];

        return response()->json($data);
    }
}
