<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FaceDeleteRequest;
use App\Http\Requests\FaceSaveRequest;
use App\Models\Face;
use App\Models\Person;
use App\Services\PersonService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ApiFaceController extends Controller
{
    public function __construct(
        private PersonService $personService
    ) {}

    public function list(Request $request)
    {
        $imageId = $request->input('image_id');

        $faces = Face::where('image_id', $imageId)
            ->orderBy('face_index')
            ->get(['id', 'face_index', 'name', 'status', 'person_id', 'quality_score']);

        return response()->json($faces);
    }

    public function save(FaceSaveRequest $request)
    {
        $validated = $request->validated();

        $face = Face::where('image_id', $request->image_id)
            ->where('face_index', $request->face_index)
            ->firstOrFail();

        $oldPersonId = $face->person_id;
        $newPersonId = null;

        // Если статус OK и есть имя — привязываем к Person
        if ($request->status == Face::STATUS_OK && $request->name) {
            $person = Person::firstOrCreate(
                ['name' => $request->name],
                ['name' => $request->name]
            );
            $newPersonId = $person->id;
        }

        $face->update([
            'name' => $request->status == Face::STATUS_OK ? $request->name : null,
            'status' => $request->status,
            'person_id' => $newPersonId,
        ]);

        // Пересчитать centroid для затронутых persons
        if ($newPersonId) {
            $this->personService->recalculateCentroid(Person::find($newPersonId));
        }
        if ($oldPersonId && $oldPersonId !== $newPersonId) {
            $this->personService->recalculateCentroid(Person::find($oldPersonId));
        }

        // Обновить дочерние faces (у которых parent_id = этому лицу)
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
            ->where('status', Face::STATUS_PROCESS)
            ->update([
                'name' => $face->name,
                'person_id' => $face->person_id,
                'status' => $face->status, // OK если родитель OK
            ]);

        // Пересчитать centroid с учётом новых лиц
        if ($face->person_id) {
            $this->personService->recalculateCentroid(Person::find($face->person_id));
        }
    }

    public function remove(FaceDeleteRequest $request)
    {
        $validated = $request->validated();

        $face = Face::where('image_id', $request->image_id)
            ->where('face_index', $request->face_index)
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
