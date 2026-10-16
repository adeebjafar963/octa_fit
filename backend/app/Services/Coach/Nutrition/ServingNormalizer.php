<?php

namespace App\Services\Coach\Nutrition;

use InvalidArgumentException;

/**
 * Phase 11D — deterministic unit normalization.
 * Does NOT assume ml == g (density unknown).
 */
class ServingNormalizer
{
    /**
     * @return array{grams: float, unit: string, quantity: float, count: float|null, notes: list<string>}
     */
    public function normalize(float $quantity, string $unit, ?float $pieceGrams = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('quantity_must_be_positive');
        }

        $unit = strtolower(trim($unit));
        $notes = [];

        return match ($unit) {
            'g', 'gram', 'grams' => [
                'grams' => $quantity,
                'unit' => 'g',
                'quantity' => $quantity,
                'count' => null,
                'notes' => $notes,
            ],
            'kg', 'kilogram', 'kilograms' => [
                'grams' => $quantity * 1000.0,
                'unit' => 'kg',
                'quantity' => $quantity,
                'count' => null,
                'notes' => $notes,
            ],
            'mg', 'milligram', 'milligrams' => [
                'grams' => $quantity / 1000.0,
                'unit' => 'mg',
                'quantity' => $quantity,
                'count' => null,
                'notes' => $notes,
            ],
            'piece', 'pieces', 'count', 'egg', 'eggs', 'banana', 'bananas', 'apple', 'apples', 'slice', 'slices', 'bar', 'bars' => $this->fromPiece($quantity, $unit, $pieceGrams),
            'ml', 'milliliter', 'milliliters', 'l', 'liter', 'liters' => throw new InvalidArgumentException('volume_without_density_unsupported'),
            default => throw new InvalidArgumentException('unsupported_unit'),
        };
    }

    /**
     * @return array{grams: float, unit: string, quantity: float, count: float|null, notes: list<string>}
     */
    private function fromPiece(float $quantity, string $unit, ?float $pieceGrams): array
    {
        if ($pieceGrams === null || $pieceGrams <= 0) {
            throw new InvalidArgumentException('piece_weight_unavailable');
        }

        return [
            'grams' => $quantity * $pieceGrams,
            'unit' => 'piece',
            'quantity' => $quantity,
            'count' => $quantity,
            'notes' => ['Used verified piece weight from food record ('.$pieceGrams.' g each).'],
        ];
    }
}
