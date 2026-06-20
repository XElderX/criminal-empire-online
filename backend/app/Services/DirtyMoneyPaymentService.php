<?php

namespace App\Services;

use App\Core\Database;

final class DirtyMoneyPaymentService
{
    public function deduct(int $userId, int $amount, string $paymentType): void
    {
        $column = $paymentType === 'dirty_money' ? 'dirty_money' : ($paymentType === 'bank' ? 'bank_cash' : 'cash');
        Database::pdo()->prepare("UPDATE users SET {$column} = {$column} - ?, updated_at = NOW() WHERE id = ?")
            ->execute([$amount, $userId]);
    }

    public function add(int $userId, int $amount, string $paymentType): void
    {
        $column = $paymentType === 'dirty_money' ? 'dirty_money' : ($paymentType === 'bank' ? 'bank_cash' : 'cash');
        Database::pdo()->prepare("UPDATE users SET {$column} = {$column} + ?, updated_at = NOW() WHERE id = ?")
            ->execute([$amount, $userId]);
    }
}
