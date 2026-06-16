<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Response;
use App\Services\AuditService;
use Throwable;

final class ShopController
{
    public function weapons(array $params = [], array $context = []): void { Response::json(['data' => Database::pdo()->query('SELECT * FROM weapons ORDER BY price ASC')->fetchAll()]); }
    public function inventory(array $params, array $context): void
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT uw.quantity,w.* FROM user_weapons uw JOIN weapons w ON w.id=uw.weapon_id WHERE uw.user_id=?');
        $stmt->execute([$context['user']['id']]);
        $weapons = $stmt->fetchAll();
        $stmt = $pdo->prepare('SELECT ud.quantity,d.* FROM user_drugs ud JOIN drugs d ON d.id=ud.drug_id WHERE ud.user_id=?');
        $stmt->execute([$context['user']['id']]);
        Response::json(['weapons' => $weapons, 'drugs' => $stmt->fetchAll()]);
    }
    public function buyWeapon(array $params, array $context): void
    {
        try {
            $pdo = Database::pdo(); $pdo->beginTransaction();
            $weapon = $pdo->prepare('SELECT * FROM weapons WHERE id=?'); $weapon->execute([(int)$params['id']]); $weapon = $weapon->fetch();
            if (!$weapon) throw new \RuntimeException('Weapon not found');
            $user = $pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE'); $user->execute([$context['user']['id']]); $user = $user->fetch();
            if ((int)$user['cash'] < (int)$weapon['price']) throw new \RuntimeException('Not enough cash');
            $pdo->prepare('UPDATE users SET cash=cash-? WHERE id=?')->execute([$weapon['price'], $user['id']]);
            $pdo->prepare('INSERT INTO user_weapons (user_id,weapon_id,quantity,created_at,updated_at) VALUES (?,?,1,NOW(),NOW()) ON DUPLICATE KEY UPDATE quantity=quantity+1, updated_at=NOW()')->execute([$user['id'], $weapon['id']]);
            AuditService::log((int)$user['id'], 'shop.buy_weapon', ['weapon_id'=>$weapon['id']]);
            $pdo->commit(); Response::json(['message'=>'Weapon purchased']);
        } catch (Throwable $e) { if (Database::pdo()->inTransaction()) Database::pdo()->rollBack(); Response::json(['message'=>$e->getMessage()],422); }
    }
}
