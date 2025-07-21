<?php

namespace App\Http\Requests;

use App\Models\Face;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

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
            'status' => 'required|string|in:' . implode(',', [
                /* Face::STATUS_PROCESS, */Face::STATUS_UNKNOWN, Face::STATUS_NOT_FACE, Face::STATUS_OK
            ]),
        ];
    }
}
