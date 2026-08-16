<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Mise en demeure — Art. 75-76 loi n°2022-30. */
class DemandLetter extends Model
{
    use HasFactory;

    protected $fillable = [
        'lease_id',
        'period',
        'amount',
        'sent_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'date',
            'resolved_at' => 'date',
        ];
    }

    public function lease(): BelongsTo
    {
        return $this->belongsTo(Lease::class);
    }

    /** Art. 76 al.1: le locataire dispose d'un mois à compter de la mise en demeure. */
    public function legalDeadline(): \Illuminate\Support\Carbon
    {
        return $this->sent_at->copy()->addMonth();
    }
}
