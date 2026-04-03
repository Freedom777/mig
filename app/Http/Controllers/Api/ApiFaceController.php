<?php

namespace App\Http\Controllers\Api;

use App\Enums\FaceStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\FaceDeleteRequest;
use App\Http\Requests\FaceSaveRequest;
use App\Models\Face;
use App\Models\Image;
use App\Models\Person;
use App\Services\PersonService;
use Illuminate\Support\Facades\Log;

class ApiFaceController extends Controller
{
    public function __construct(
        private PersonService $personService
    ) {}

    public function list(Image $image)
    {
        $faces = $image->faces()
            ->with('person:id,name')
            ->orderBy('face_index')
            ->get(['id', 'image_id', 'face_index', 'status', 'person_id', 'quality_score']);

        // Преобразуем для фронтенда
        $result = $faces->map(function ($face) {
            return [
                'id' => $face->id,
                'face_index' => $face->face_index,
                'name' => $face->person?->name,
                'status' => $face->status,
                'person_id' => $face->person_id,
                'quality_score' => $face->quality_score,
            ];
        });

        return response()->json($result);
    }

    public function save(FaceSaveRequest $request, Image $image, int $faceIndex)
    {
        $face = $image->faces()
            ->where('face_index', $faceIndex)
            ->firstOrFail();

        $oldPersonId = $face->person_id;
        $newPersonId = null;

        // Если статус OK и есть имя — привязываем к Person
        if ($request->status == FaceStatusEnum::Ok->value && $request->name) {
            $person = Person::whereRaw('LOWER(name) = ?', [mb_strtolower($request->name)])->first();
            if (!$person) {
                $person = Person::create(['name' => $request->name]);
            }
            $newPersonId = $person->id;
        }

        $face->update([
            'status' => $request->status,
            'person_id' => $newPersonId,
        ]);

        $linkedCount = 0;

        // Если подтвердили лицо (Process/Suggested → Ok)
        if ($newPersonId) {
            $person = Person::find($newPersonId);
            $threshold = config('image.face_api.threshold', 0.6);

            // 1. Линкуем похожие лица (автоматически находит и присваивает)
            //    linkSimilarFaces внутри вызывает recalculateCentroid
            $linkedCount = $this->personService->linkSimilarFaces($face, $person, $threshold);

            // 2. Если это первое лицо Person ИЛИ качество хорошее — пересчитываем centroid
            //    (linkSimilarFaces уже вызвал recalculateCentroid, но если нашли новые лица — нужно ещё раз)
            $minQuality = config('image.face_api.min_quality_for_centroid', 50);

            if ($linkedCount > 0 || $face->quality_score >= $minQuality) {
                // Пересчёт уже сделан в linkSimilarFaces, но если были новые лица — делаем ещё раз
                if ($linkedCount > 0) {
                    $this->personService->recalculateCentroid($person);
                }
            }
        }

        // Если отвязали от Person (было присвоено, стало Unknown/NotFace/Rejected)
        if ($oldPersonId && $oldPersonId !== $newPersonId) {
            $oldPerson = Person::find($oldPersonId);
            if ($oldPerson) {
                // Пересчитываем centroid старой персоны (убрали одно лицо)
                $this->personService->recalculateCentroid($oldPerson);
            }
        }

        return response()->json([
            'success' => true,
            'linked_faces' => $linkedCount,
        ]);
    }

    public function remove(Image $image, int $faceIndex)
    {
        $face = $image->faces()
            ->where('face_index', $faceIndex)
            ->first();

        if ($face && $face->person_id) {
            $personId = $face->person_id;
            $face->delete();

            // Пересчитать centroid после удаления лица
            $person = Person::find($personId);
            if ($person) {
                $this->personService->recalculateCentroid($person);
            }
        } else {
            $face?->delete();
        }

        return response()->json(['success' => true]);
    }
}
