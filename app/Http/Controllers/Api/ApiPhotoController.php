<?php

namespace App\Http\Controllers\Api;

use App\Contracts\ImagePathServiceInterface;
use App\Enums\FaceStatusEnum;
use App\Enums\ImageStatusEnum;
use App\Http\Controllers\Controller;
use App\Models\Image;
use App\Services\ImagePathService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiPhotoController extends Controller
{
    /*
     *

**Основные изменения:**

| Было | Стало |
|------|-------|
| Загрузка всех faces | Только `status = ok` |
| `people` — массив строк | `people` — массив объектов `{id, name}` |
| Нет пагинации meta | Полная мета-информация |
| Нет prev_page_url | Добавлен |
| Нет сортировки | Параметры `sort`, `direction` |
| Нет фильтра по person_id | Добавлен `person_ids` |
| Нет фильтра по статусу | Добавлен `only_completed` |

---

**Примеры запросов:**
```
GET /api/photos?people[]=Олег&people[]=Анна
    GET /api/photos?person_ids[]=1&person_ids[]=2
        GET /api/photos?only_completed=true
            GET /api/photos?sort=updated_at_file&direction=desc
                GET /api/photos?date_from=2024-01&date_to=2024-12&cities[]=Berlin

         {
          "data": [
            {
              "id": 1,
              "thumbnail": "/storage/thumbnails/img1.jpg",
              "image": "/storage/img1.jpg",
              "date": "2023-07-10",
              "city": "Berlin",
              "people": ["Oleg", "Anna"]
            }
          ],
          "next_page_url": "/api/photos?page=2&people[]=Oleg"
        }
         */

    public function __construct(
        private ImagePathServiceInterface $pathService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Image::query()
            ->with([
                'faces' => fn($q) => $q->where('status', FaceStatusEnum::Ok->value)->select(['id', 'image_id', 'person_id']),
                'faces.person:id,name',
                'geolocationAddress'
            ]);

        // Только обработанные фото (опционально)
        if ($request->boolean('only_completed', false)) {
            $query->where('status', ImageStatusEnum::Ok->value);
        }

        // Фильтр по людям (через persons)
        /*
        if ($request->has('people')) {
            $query->whereHas('faces', function ($q) use ($request) {
                $q->where('status', FaceStatusEnum::Ok->value)
                    ->whereHas('person', fn($q2) => $q2->whereIn('name', $request->people));
            });
        }
        */

        // Фильтр по person_id (более точный)
        if ($request->has('person_ids')) {
            $query->whereHas('faces', function ($q) use ($request) {
                $q->where('status', FaceStatusEnum::Ok->value)
                    ->whereIn('person_id', $request->person_ids);
            });
        }

        // Фильтр по городам
        if ($request->has('cities')) {
            $query->whereHas('geolocationAddress', function ($q) use ($request) {
                $q->whereIn(
                    DB::raw("JSON_UNQUOTE(JSON_EXTRACT(address, '$.address.city'))"),
                    $request->cities
                );
            });
        }

        // Фильтр по тегам (путям/папкам)
        if ($request->has('tags')) {
            $query->whereIn('path', $request->tags);
        }

        // Фильтр по дате
        if ($request->filled('date_from') && $request->filled('date_to')) {

            $dateFrom = Carbon::parse($request->date_from)->startOfMonth();
            $dateTo = Carbon::parse($request->date_to)->endOfMonth();
            $query->whereBetween('updated_at_file', [$dateFrom, $dateTo]);
        }

        // Сортировка
        $sortField = $request->input('sort', 'updated_at_file');
        $sortDirection = $request->input('direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        $photos = $query->paginate($request->input('per_page', 20));

        $data = $photos->through(fn($image) => $this->transformImage($image));

        // Вернуть простую структуру
        return response()->json([
            'data' => $data->items(),
            'meta' => [
                'current_page' => $photos->currentPage(),
                'last_page' => $photos->lastPage(),
                'per_page' => $photos->perPage(),
                'total' => $photos->total(),
            ],
            'next_page_url' => $photos->nextPageUrl(),
            'prev_page_url' => $photos->previousPageUrl(),
        ]);
    }

    /**
     * Трансформация изображения для фронтенда
     */
    private function transformImage(Image $image): array
    {
        return [
            'id' => $image->id,
            'image' => $this->pathService->getImageUrl($image),
            'thumbnail' => $this->pathService->getThumbnailUrl($image),
            'date' => $image->updated_at_file?->toDateString(),
            'city' => $image->geolocationAddress?->city_name,
            'people' => $image->faces
                ->pluck('person')
                ->filter()
                ->map(fn($person) => [
                    'id' => $person->id,
                    'name' => $person->name,
                ])
                ->unique('id')
                ->values(),
            'tags' => [$image->path],
            'status' => $image->status,
        ];
    }
}
