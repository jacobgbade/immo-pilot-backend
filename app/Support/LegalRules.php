<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Hard caps from Loi n°2022-30 (bail à usage d'habitation, Bénin) that the platform
 * enforces automatically. Each rule cites its article so the block explains itself —
 * see docs/PRODUCT_SPEC.md and the legal spec artifact for the full rule registry.
 * Only rules with an unambiguous, directly-applicable article are enforced here; rules
 * needing a barème or administrative apparatus not yet operational (e.g. the 8% rent
 * ceiling of Art. 57 al.1) are deliberately left out until confirmed by a Beninese jurist.
 */
class LegalRules
{
    public const DEPOSIT_MAX_MONTHS = 3;

    public const RENT_REVISION_MAX_INCREASE = 0.02;

    /** Art. 57 al.3 et Art. 69, 71 — le cautionnement ne peut excéder 3 mois de loyer. */
    public static function validateDeposit(int $rentAmount, ?int $depositAmount): void
    {
        if ($depositAmount === null) {
            return;
        }

        $max = $rentAmount * self::DEPOSIT_MAX_MONTHS;

        if ($depositAmount > $max) {
            throw ValidationException::withMessages([
                'deposit' => [sprintf(
                    'Le cautionnement (%s FCFA) dépasse le plafond légal de 3 mois de loyer (%s FCFA) — Art. 57 et 69 de la loi n°2022-30.',
                    number_format($depositAmount, 0, ',', ' '),
                    number_format($max, 0, ',', ' '),
                )],
            ]);
        }
    }

    /** Art. 68 al.2 — la révision du loyer ne peut excéder 2% du loyer initial du bail. */
    public static function validateRentRevision(int $initialRentAmount, int $newRentAmount): void
    {
        $max = (int) round($initialRentAmount * (1 + self::RENT_REVISION_MAX_INCREASE));

        if ($newRentAmount > $max) {
            throw ValidationException::withMessages([
                'rent_amount' => [sprintf(
                    'Le nouveau loyer (%s FCFA) dépasse le plafond légal de révision de 2%% du loyer initial (%s FCFA) — Art. 68 de la loi n°2022-30.',
                    number_format($newRentAmount, 0, ',', ' '),
                    number_format($max, 0, ',', ' '),
                )],
            ]);
        }
    }
}
