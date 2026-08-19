<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Dilempar saat sebuah mutasi debit akan membuat saldo dompet menjadi negatif.
 * Punya render() sendiri agar otomatis menjadi respons 422 yang rapi tanpa
 * perlu ditangkap manual di controller.
 */
class InsufficientBalanceException extends RuntimeException
{
    public function __construct(
        public readonly float $saldo,
        public readonly float $diminta,
    ) {
        parent::__construct('Saldo tidak mencukupi.');
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => 'Saldo tidak mencukupi.',
            'saldo' => $this->saldo,
            'diminta' => $this->diminta,
        ], 422);
    }
}
