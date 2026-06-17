<?php
require_once __DIR__ . '/../app/Core/Autoload.php';
use App\Core\App;use App\Core\Database;use App\Services\SalaryService;
App::boot(dirname(__DIR__));
$command=$argv[1]??'status';$pdo=Database::pdo();
if($command==='status'){echo json_encode(['time'=>date(DATE_ATOM),'active_jobs'=>(int)$pdo->query("SELECT COUNT(*) FROM job_runs WHERE status='active'")->fetchColumn(),'available_recruits'=>(int)$pdo->query("SELECT COUNT(*) FROM recruitment_candidates WHERE status='available' AND (expires_at IS NULL OR expires_at>NOW())")->fetchColumn(),'gang_members'=>(int)$pdo->query("SELECT COUNT(*) FROM player_gang_members WHERE status<>'dismissed'")->fetchColumn()],JSON_PRETTY_PRINT).PHP_EOL;exit;}
if($command==='process-week'){$result=(new SalaryService())->processDue();echo json_encode($result,JSON_PRETTY_PRINT).PHP_EOL;exit;}
if(in_array($command,['process-hour','process-day'],true)){echo json_encode(['command'=>$command,'processed_at'=>date(DATE_ATOM),'note'=>'Foundation tick completed; jobs remain backend-timer authoritative.'],JSON_PRETTY_PRINT).PHP_EOL;exit;}
fwrite(STDERR,"Usage: php commands/world.php status|process-hour|process-day|process-week\n");exit(1);
