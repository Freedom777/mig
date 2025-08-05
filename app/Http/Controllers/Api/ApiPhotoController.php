<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\ImagePathService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApiPhotoController extends Controller
{
    public function index(Request $request)
    {
        /*
         {
          "data": [
            {
              "id": 1,
              "thumbnail": "/storage/thumbnails/img1.jpg",
              "image" : "/storage/img1.jpg",
              "date": "2023-07-10",
              "city": "Berlin",
              "people": ["Oleg", "Anna"]
            }
          ],
          "next_page_url": "/api/photos?page=2&people[]=Oleg"
        }
         */

        $query = Image::query()
            ->with(['faces:name', 'geolocationAddress']);

        // Фильтр по людям
        if ($request->has('people')) {
            $query->whereHas('faces', function ($q) use ($request) {
                $q->whereIn('name', $request->people);
            });
        }

        // Фильтр по городам (берем city из JSON в address)
        if ($request->has('cities')) {
            $query->whereHas('geolocationAddress', function ($q) use ($request) {
                $q->whereIn(
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(address, '$.address.city'))"),
                    $request->cities
                );
            });
        }

        // Фильтр по тегам
        if ($request->has('tags')) {
            $query->whereIn('path', $request->tags);
        }

        // Фильтр по дате
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween(
                DB::raw('YEAR(updated_at_file)'),
                [$request->date_from, $request->date_to]
            );
        }

        // dd($query->toSql());
        $photos = $query->paginate(20);


        // Преобразуем ответ под формат фронтенда
        $data = $photos->through(function ($image) {
            // dd(ImagePathService::getThumbnailUrl($image));
            return [
                'id' => $image->id,
                'image' => ImagePathService::getImageUrl($image),
                'thumbnail' => ImagePathService::getThumbnailUrl($image),
                'date' => Carbon::parse($image->updated_at_file)->toDateString(),
                'city' => optional($image->geolocationAddress)->city_name,
                'people' => $image->faces->pluck('name'),
                'tags' => [$image->path]
            ];
        });

        return response()->json([
            'data' => $data,
            'next_page_url' => $photos->nextPageUrl(),
        ]);
    }
}
