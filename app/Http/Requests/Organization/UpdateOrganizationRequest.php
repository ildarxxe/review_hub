<?php

namespace App\Http\Requests\Organization;

use App\Rules\YandexMapsUrl;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'string', 'max:2048', new YandexMapsUrl],
        ];
    }
}
