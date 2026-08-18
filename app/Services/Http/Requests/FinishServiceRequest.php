<?php

namespace App\Services\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FinishServiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:5000'],
            'photos' => ['nullable', 'array', 'max:20'],
            'photos.*' => ['string', 'max:2048'],
        ];
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) ? $notes : null;
    }

    /**
     * @return list<string>
     */
    public function photos(): array
    {
        /** @var list<string>|null $photos */
        $photos = $this->validated('photos');

        return $photos ?? [];
    }
}
