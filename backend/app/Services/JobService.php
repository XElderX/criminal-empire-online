<?php
namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class JobService
{
    public function listForUser(array $user): array
    {
        $stmt = Database::pdo()->prepare("SELECT jo.id opportunity_id,j.*,t.name territory_name,n.first_name giver_first_name,n.last_name giver_last_name,n.nickname giver_nickname FROM job_opportunities jo JOIN jobs j ON j.id=jo.job_id JOIN territories t ON t.id=jo.territory_id LEFT JOIN npcs n ON n.id=jo.giver_npc_id WHERE jo.status='available' AND j.active=1 AND jo.available_from<=NOW() AND (jo.expires_at IS NULL OR jo.expires_at>NOW()) ORDER BY j.reward_min,j.id");
        $stmt->execute();
        $items = $stmt->fetchAll();
        foreach ($items as &$item) {
            $item['duration_seconds_effective'] = max(1, (int)round((int)$item['duration_seconds'] * GameConfig::jobDurationMultiplier()));
            $item['can_start'] = (int)$user['energy'] >= (int)$item['energy_cost'] && (int)($user['reputation'] ?? 0) >= (int)$item['min_reputation'];
        }
        return $items;
    }

    public function active(array $user): array
    {
        $stmt=Database::pdo()->prepare("SELECT jr.*,j.name,j.description,j.reward_min,j.reward_max,t.name territory_name,TIMESTAMPDIFF(SECOND,NOW(),jr.completes_at) seconds_remaining FROM job_runs jr JOIN job_opportunities jo ON jo.id=jr.opportunity_id JOIN jobs j ON j.id=jo.job_id JOIN territories t ON t.id=jo.territory_id WHERE jr.user_id=? AND jr.status='active' ORDER BY jr.id DESC");
        $stmt->execute([$user['id']]); return $stmt->fetchAll();
    }

    public function start(array $user, int $opportunityId, array $memberIds, string $idempotencyKey): array
    {
        if ($idempotencyKey === '') $idempotencyKey = bin2hex(random_bytes(16));
        $pdo=Database::pdo(); $pdo->beginTransaction();
        try {
            $q=$pdo->prepare("SELECT jo.*,j.* FROM job_opportunities jo JOIN jobs j ON j.id=jo.job_id WHERE jo.id=? FOR UPDATE"); $q->execute([$opportunityId]); $job=$q->fetch();
            if(!$job || $job['status']!=='available') throw new RuntimeException('Job opportunity is not available');
            $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE'); $q->execute([$user['id']]); $fresh=$q->fetch();
            if((int)$fresh['energy']<(int)$job['energy_cost']) throw new RuntimeException('Not enough energy');
            if((int)($fresh['reputation']??0)<(int)$job['min_reputation']) throw new RuntimeException('Not enough reputation');
            $memberIds=array_values(array_unique(array_map('intval',$memberIds)));
            if(count($memberIds)<(int)$job['min_gang_size']) throw new RuntimeException('This job requires more gang members');
            foreach($memberIds as $memberId){
                $m=$pdo->prepare("SELECT * FROM player_gang_members WHERE id=? AND user_id=? FOR UPDATE");$m->execute([$memberId,$fresh['id']]);$member=$m->fetch();
                if(!$member || $member['status']!=='active') throw new RuntimeException('Selected gang member is unavailable');
            }
            $duration=max(1,(int)round((int)$job['duration_seconds']*GameConfig::jobDurationMultiplier()));
            $pdo->prepare('UPDATE users SET energy=energy-?,updated_at=NOW() WHERE id=?')->execute([$job['energy_cost'],$fresh['id']]);
            $pdo->prepare("INSERT INTO job_runs(user_id,opportunity_id,idempotency_key,status,started_at,completes_at,created_at,updated_at) VALUES(?,?,?,'active',NOW(),DATE_ADD(NOW(),INTERVAL ? SECOND),NOW(),NOW())")->execute([$fresh['id'],$opportunityId,$idempotencyKey,$duration]);
            $runId=(int)$pdo->lastInsertId();
            foreach($memberIds as $memberId){$pdo->prepare('INSERT INTO job_assignments(job_run_id,gang_member_id) VALUES(?,?)')->execute([$runId,$memberId]);$pdo->prepare("UPDATE player_gang_members SET status='busy',current_assignment_type='job',current_assignment_id=?,updated_at=NOW() WHERE id=?")->execute([$runId,$memberId]);}
            $pdo->prepare("UPDATE job_opportunities SET status='active' WHERE id=?")->execute([$opportunityId]);
            $pdo->commit(); return ['message'=>'Job started','job_run_id'=>$runId,'duration_seconds'=>$duration];
        } catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function complete(array $user,int $runId): array
    {
        $pdo=Database::pdo();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare("SELECT jr.*,jo.job_id,jo.territory_id,jo.giver_npc_id,jo.source_budget,j.* FROM job_runs jr JOIN job_opportunities jo ON jo.id=jr.opportunity_id JOIN jobs j ON j.id=jo.job_id WHERE jr.id=? AND jr.user_id=? FOR UPDATE");$q->execute([$runId,$user['id']]);$run=$q->fetch();
            if(!$run)throw new RuntimeException('Job run not found');
            if($run['status']!=='active')throw new RuntimeException('Job has already been resolved');
            if(strtotime($run['completes_at'])>time())throw new RuntimeException('Job is not complete yet');
            $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);$fresh=$q->fetch();
            $members=$pdo->prepare('SELECT pgm.* FROM job_assignments ja JOIN player_gang_members pgm ON pgm.id=ja.gang_member_id WHERE ja.job_run_id=?');$members->execute([$runId]);$members=$members->fetchAll();
            $statBonus=(int)$fresh['intelligence']; foreach($members as $m){$stat=$run['required_stat'] ?: 'discipline';$statBonus+=(int)($m[$stat]??0)/5;}
            $district=$pdo->prepare('SELECT * FROM territories WHERE id=?');$district->execute([$run['territory_id']]);$district=$district->fetch();
            $chance=max(5,min(95,(int)$run['base_success_rate']+$statBonus-(int)$run['difficulty']*3-(int)($district['police_presence']??50)/10));
            if($run['category']==='legal')$chance=max(92,$chance);
            $success=random_int(1,100)<=$chance;$reward=$success?random_int((int)$run['reward_min'],(int)$run['reward_max']):0;$heat=$success?random_int((int)$run['heat_min'],(int)$run['heat_max']):max((int)$run['heat_max'],1);
            $pdo->prepare('UPDATE users SET cash=cash+?,heat=heat+?,experience=experience+?,reputation=reputation+?,updated_at=NOW() WHERE id=?')->execute([$reward,$heat,$run['experience_gain'],$success?$run['reputation_gain']:0,$fresh['id']]);
            $pdo->prepare("UPDATE job_runs SET status=?,success=?,reward=?,heat_gained=?,completed_at=NOW(),result=?,updated_at=NOW() WHERE id=?")->execute([$success?'completed':'failed',$success?1:0,$reward,$heat,json_encode(['success_chance'=>$chance]),$runId]);
            $pdo->prepare("UPDATE job_opportunities SET status='completed' WHERE id=?")->execute([$run['opportunity_id']]);
            foreach($members as $m){$pdo->prepare("UPDATE player_gang_members SET status='active',current_assignment_type=NULL,current_assignment_id=NULL,experience=experience+?,updated_at=NOW() WHERE id=?")->execute([$run['experience_gain'],$m['id']]);}
            if($reward>0)(new EconomyLedgerService())->record('job_reward',$reward,'NPC or external contract paid a completed job',['source_type'=>$run['giver_npc_id']?'npc':'external_contract','source_id'=>$run['giver_npc_id'],'destination_type'=>'player','destination_id'=>$fresh['id'],'user_id'=>$fresh['id'],'npc_id'=>$run['giver_npc_id'],'job_run_id'=>$runId,'territory_id'=>$run['territory_id']]);
            $pdo->commit();return ['success'=>$success,'reward'=>$reward,'heat_gained'=>$heat,'success_chance'=>$chance];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }
}
