<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:150',
            'description' => 'nullable|string',
            'quantity' => 'required|integer|min:0',
            'condition' => 'required|string|max:50',
            'pickup_location' => 'nullable|string|max:255',
            'min_borrowing_days' => 'nullable|required_with:max_borrowing_days|integer|min:1|max:3650',
            'max_borrowing_days' => 'nullable|required_with:min_borrowing_days|integer|min:1|max:3650|gte:min_borrowing_days',
            'image' => 'nullable|image|max:5120',
            'remove_image' => 'nullable|boolean',
            'existing_images' => 'nullable|array',
            'existing_images.*' => 'string',
            'clear_gallery' => 'nullable|boolean',
            'gallery' => 'nullable|array|max:10',
            'gallery.*' => 'image|max:5120',
        ];
    }
}
