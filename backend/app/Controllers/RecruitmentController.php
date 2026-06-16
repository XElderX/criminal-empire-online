<?php
namespace App\Controllers;
use App\Core\Response;use App\Services\RecruitmentService;use Throwable;
final class RecruitmentController{public function index(array $p,array $c):void{Response::json(['data'=>(new RecruitmentService())->candidates($c['user'])]);}public function members(array $p,array $c):void{Response::json(['data'=>(new RecruitmentService())->members($c['user'])]);}public function hire(array $p,array $c):void{try{Response::json((new RecruitmentService())->hire($c['user'],(int)$p['id']),201);}catch(Throwable $e){Response::json(['message'=>$e->getMessage()],422);}}public function payOverdue(array $p,array $c):void{try{Response::json((new RecruitmentService())->payOverdue($c['user'],(int)$p['id']));}catch(Throwable $e){Response::json(['message'=>$e->getMessage()],422);}}}
