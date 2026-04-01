<?php

namespace App\Http\Requests;

use App\Enums\FaceStatusEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class FaceSaveRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //  return $this->user()->can('update', $this->post);
        // return auth()->check() && auth()->user()->is_admin;
        // return Auth::check(); // Аналогично auth()->check()
        return true;
    }

    public function validationData(): array
    {
        return array_merge($this->all(), [
            'image_id' => $this->route('image')?->id,
            'face_index' => $this->route('faceIndex'),
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'image_id' => 'required|integer',
            'face_index' => 'required|integer',
            'name' => 'nullable|string|max:255',
            'status' => ['required', new Enum(FaceStatusEnum::class)],
        ];
    }
}
