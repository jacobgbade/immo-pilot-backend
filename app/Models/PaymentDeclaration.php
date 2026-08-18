<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentDeclaration extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'period',
        'amount',
        'method',
        'reference',
        'note',
        'status',
        'declared_at',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'declared_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }
}
