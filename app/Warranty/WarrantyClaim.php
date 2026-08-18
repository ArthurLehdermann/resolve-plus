<?php

namespace App\Warranty;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WarrantyClaim extends Model
{
    use HasUuids;

    public const CREATED_AT = 'criado_em';

    public const UPDATED_AT = null;

    protected $fillable = [
        'garantia_id',
        'descricao',
        'photos',
    ];

    /**
     * @return BelongsTo<Garantia, $this>
     */
    public function garantia(): BelongsTo
    {
        return $this->belongsTo(Garantia::class);
    }

    protected function casts(): array
    {
        return [
            'photos' => 'array',
            'criado_em' => 'immutable_datetime',
        ];
    }
}
