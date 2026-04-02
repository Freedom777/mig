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

        // Создаём/находим Person
        if ($request->status == FaceStatusEnum::Ok->value && $request->name) {
            $person = Person::firstOrCreate(['name' => $request->name]);
            $newPersonId = $person->id;
        }

        // Обновляем Face
        $face->update([
            'status' => $request->status,
            'person_id' => $newPersonId,
        ]);

        $linkedCount = 0;

        // Если привязали к Person
        if ($newPersonId) {
            $person = Person::find($newPersonId);

            // Линкуем похожие лица
            $linkedCount = $this->personService->linkSimilarFaces($face, $person);
        }

        // Пересчитываем centroid для старой персоны
        if ($oldPersonId && $oldPersonId !== $newPersonId) {
            $oldPerson = Person::find($oldPersonId);
            if ($oldPerson) {
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

            // Пересчитать centroid после удаления
            $this->personService->recalculateCentroid(Person::find($personId));
        } else {
            $face?->delete();
        }

        return response()->json(['success' => true]);
    }
}
