<?php

namespace App\Ratings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRatingRequest extends FormRequest
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
            'score' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function nota(): int
    {
        return $this->integer('score');
    }

    public function comentario(): ?string
    {
        $comment = $this->input('comment');

        if ($comment === null || $comment === '') {
            return null;
        }

        return (string) $comment;
    }
}
