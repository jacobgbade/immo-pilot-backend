<?php

namespace App\Http\Controllers\Api\Concerns;

trait NormalizesPhone
{
    /**
     * Users naturally type phone numbers with spaces (the field's own placeholder shows
     * "+229 90 00 00 00"), so storage and lookups both normalize to digits-and-leading-+
     * only — otherwise "+229 90 00 00 00" and "+22990000000" would be treated as different
     * accounts and login would fail depending on how the user happened to type it that time.
     */
    private function normalizePhone(string $phone): string
    {
        return preg_replace('/[^\d+]/', '', $phone);
    }
}
