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
            $person = Person::firstOrCreate(['name' => $request->name]);
            $newPersonId = $person->id;
        }

        $face->update([
            'status' => $request->status,
            'person_id' => $newPersonId,
        ]);

        // Пересчитать centroid для затронутых persons
        if ($newPersonId) {
            $this->personService->recalculateCentroid(Person::find($newPersonId));
            $linked = $this->personService->linkSimilarFaces($face, Person::find($newPersonId));
        }

        if ($oldPersonId && $oldPersonId !== $newPersonId) {

            $person = Person::find($oldPersonId);

            if ($person) {
                $this->personService->recalculateCentroid($person);

            }
        }

        // Обновить дочерние faces
        $this->updateChildFaces($face);

        return response()->json(['success' => true]);
    }

    /**
     * Обновить дочерние лица
     */
    private function updateChildFaces(Face $face): void
    {
        $faceId = $face->parent_id ?? $face->id;

        Face::where('parent_id', $faceId)
            ->where('status', FaceStatusEnum::Process->value)
            ->update([
                'person_id' => $face->person_id,
                'status' => $face->status,
            ]);

        // Пересчитать centroid с учётом новых лиц
        if ($face->person_id) {
            $this->personService->recalculateCentroid(Person::find($face->person_id));
        }
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
