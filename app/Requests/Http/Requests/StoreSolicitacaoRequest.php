<?php

namespace App\Requests\Http\Requests;

use App\Auth\Enums\TipoUsuario;
use App\Categories\Models\Categoria;
use App\PropertyHistory\Property;
use App\Requests\EscopoTemplateValidator;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreSolicitacaoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->tipo === TipoUsuario::Cliente;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'property_id' => ['required', 'uuid', 'exists:properties,id'],
            'category_id' => ['required', 'uuid', 'exists:categorias,id'],
            'description' => ['required', 'string', 'max:5000'],
            'scope' => ['required', 'array'],
            'desired_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $this->validateOwnership($validator);
            $this->validateEscopo($validator);
        });
    }

    private function validateOwnership(Validator $validator): void
    {
        $usuario = $this->user();
        $property = Property::query()->find((string) $this->input('property_id'));

        if ($usuario === null || $property === null || ! $property->isCurrentOwner($usuario)) {
            $validator->errors()->add(
                'property_id',
                'O imóvel deve pertencer ao cliente autenticado como dono vigente.',
            );
        }
    }

    private function validateEscopo(Validator $validator): void
    {
        $categoria = Categoria::query()->find((string) $this->input('category_id'));

        if ($categoria === null || ! $categoria->ativo) {
            $validator->errors()->add('category_id', 'Categoria inválida ou inativa.');

            return;
        }

        $escopo = $this->input('scope');

        if (! is_array($escopo)) {
            $validator->errors()->add('scope', 'O escopo deve ser um objeto.');

            return;
        }

        $errors = app(EscopoTemplateValidator::class)->validate(
            $categoria->template_escopo ?? [],
            $escopo,
        );

        foreach ($errors as $campo => $messages) {
            foreach ($messages as $message) {
                $validator->errors()->add('scope.'.$campo, $message);
            }
        }
    }
}
