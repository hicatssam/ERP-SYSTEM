<?php

namespace App\Services\Procurement;

use App\Models\DocumentSequence;
use Illuminate\Database\QueryException;

class DocumentNumberService
{
    /**
     * Must be called inside the transaction that creates the document.
     * The unique sequence row serialises concurrent requests per document/year.
     */
    public function next(string $documentType, string $prefix): string
    {
        $year = (int) now()->format('Y');

        $sequence = DocumentSequence::query()
            ->where('document_type', $documentType)
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $sequence) {
            try {
                DocumentSequence::query()->create([
                    'document_type' => $documentType,
                    'year' => $year,
                    'last_number' => 0,
                ]);
            } catch (QueryException) {
                // Another transaction created the unique row between the read and insert.
            }

            $sequence = DocumentSequence::query()
                ->where('document_type', $documentType)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $sequence->increment('last_number');
        $sequence->refresh();

        return sprintf('%s-%d-%05d', $prefix, $year, $sequence->last_number);
    }
}
