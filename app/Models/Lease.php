<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lease extends Model
{
    use HasFactory;

    protected $fillable = [
        'unit_id',
        'tenant_id',
        'start_date',
        'end_date',
        'rent_amount',
        'initial_rent_amount',
        'deposit',
        'due_day',
        'status',
        'deposit_refund_amount',
        'deposit_refund_notes',
        'deposit_refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'deposit_refunded_at' => 'date',
        ];
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class);
    }

    public function demandLetters(): HasMany
    {
        return $this->hasMany(DemandLetter::class);
    }

    public function paymentDeclarations(): HasMany
    {
        return $this->hasMany(PaymentDeclaration::class);
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'unit_id', 'unit_id');
    }

    /** Payment already recorded for the given "YYYY-MM" period, if any. */
    public function paymentForPeriod(string $period): ?Payment
    {
        return $this->payments->firstWhere('period', $period);
    }
}
