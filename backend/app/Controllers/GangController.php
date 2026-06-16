<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

final class GangController
{
    public function index(array $params = [], array $context = []): void { Response::json(['data'=>Database::pdo()->query('SELECT * FROM gangs ORDER BY reputation DESC')->fetchAll()]); }
    public function create(array $params, array $context): void
    {
        $data = Request::json();
        try {
            $pdo=Database::pdo(); $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO gangs (name,boss_user_id,treasury,reputation,created_at,updated_at) VALUES (?,?,0,0,NOW(),NOW())')->execute([trim($data['name'] ?? ''),$context['user']['id']]);
            $gangId=(int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO gang_members (gang_id,user_id,`rank`,joined_at) VALUES (?,?,?,NOW())')->execute([$gangId,$context['user']['id'],'boss']);
            $pdo->commit(); Response::json(['message'=>'Gang created','gang_id'=>$gangId],201);
        } catch (Throwable $e) { if(Database::pdo()->inTransaction()) Database::pdo()->rollBack(); Response::json(['message'=>$e->getMessage()],422); }
    }
}
