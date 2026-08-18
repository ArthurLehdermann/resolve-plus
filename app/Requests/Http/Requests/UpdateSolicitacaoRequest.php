<?php

namespace App\Requests\Http\Requests;

use App\Auth\Enums\TipoUsuario;
use App\Categories\Models\Categoria;
use App\PropertyHistory\Property;
use App\Requests\EscopoTemplateValidator;
use App\Requests\Solicitacao;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSolicitacaoRequest extends FormRequest
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
            'property_id' => ['sometimes', 'uuid', 'exists:properties,id'],
            'category_id' => ['sometimes', 'uuid', 'exists:categorias,id'],
            'description' => ['sometimes', 'string', 'max:5000'],
            'scope' => ['sometimes', 'array'],
            'desired_date' => ['sometimes', 'nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->exists('property_id')) {
                $this->validateOwnership($validator);
            }

            if ($this->exists('scope') || $this->exists('category_id')) {
                $this->validateEscopo($validator);
            }
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
        $solicitacao = $this->route('solicitacao');
        $categoriaId = (string) ($this->input('category_id') ?? ($solicitacao instanceof Solicitacao ? $solicitacao->categoria_id : ''));
        $categoria = Categoria::query()->find($categoriaId);

        if ($categoria === null || ! $categoria->ativo) {
            $validator->errors()->add('category_id', 'Categoria inválida ou inativa.');

            return;
        }

        $escopo = $this->exists('scope')
            ? $this->input('scope')
            : ($solicitacao instanceof Solicitacao ? $solicitacao->escopo : null);

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
