<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FaceDeleteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        //  return $this->user()->can('update', $this->post);
        // return auth()->check() && auth()->user()->is_admin;
        return Auth::check(); // Аналогично auth()->check()
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
        ];
    }
}
