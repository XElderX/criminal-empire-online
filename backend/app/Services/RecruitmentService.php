<?php
namespace App\Services;

use App\Config\GameConfig;
use App\Core\Database;
use RuntimeException;
use Throwable;

final class RecruitmentService
{
    public function candidates(array $user): array
    {
        $sql="SELECT rc.*,n.first_name,n.last_name,n.nickname,n.age,n.biography,n.background,n.occupation,n.personal_cash,n.health,n.max_health,n.morale,n.loyalty,t.name territory_name FROM recruitment_candidates rc JOIN npcs n ON n.id=rc.npc_id JOIN territories t ON t.id=rc.territory_id WHERE rc.status='available' AND rc.available_from<=NOW() AND (rc.expires_at IS NULL OR rc.expires_at>NOW()) ORDER BY rc.recruitment_fee";
        $items=Database::pdo()->query($sql)->fetchAll();
        foreach($items as &$item){$item['traits']=$this->traits((int)$item['npc_id']);$item['can_hire']=(int)$user['cash']>=(int)$item['recruitment_fee']&&(int)($user['reputation']??0)>=(int)$item['reputation_required'];}
        return $items;
    }

    public function members(array $user): array
    {
        $stmt=Database::pdo()->prepare("SELECT pgm.*,n.first_name,n.last_name,n.nickname,n.age,n.biography,n.background,n.occupation,n.personal_cash,n.arrested_until,t.name territory_name FROM player_gang_members pgm JOIN npcs n ON n.id=pgm.npc_id LEFT JOIN territories t ON t.id=n.home_territory_id WHERE pgm.user_id=? AND pgm.status<>'dismissed' ORDER BY pgm.id");
        $stmt->execute([$user['id']]);$items=$stmt->fetchAll();foreach($items as &$item)$item['traits']=$this->traits((int)$item['npc_id']);return $items;
    }

    public function hire(array $user,int $candidateId): array
    {
        $pdo=Database::pdo();$pdo->beginTransaction();
        try{
            $q=$pdo->prepare("SELECT rc.*,n.personal_cash,n.morale,n.loyalty,n.health,n.max_health FROM recruitment_candidates rc JOIN npcs n ON n.id=rc.npc_id WHERE rc.id=? FOR UPDATE");$q->execute([$candidateId]);$c=$q->fetch();
            if(!$c||$c['status']!=='available'||($c['expires_at']&&strtotime($c['expires_at'])<=time()))throw new RuntimeException('Candidate is no longer available');
            $q=$pdo->prepare('SELECT * FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);$fresh=$q->fetch();
            if((int)$fresh['cash']<(int)$c['recruitment_fee'])throw new RuntimeException('Not enough cash');
            if((int)($fresh['reputation']??0)<(int)$c['reputation_required'])throw new RuntimeException('Not enough reputation');
            $q=$pdo->prepare("SELECT COUNT(*) FROM player_gang_members WHERE user_id=? AND status<>'dismissed'");$q->execute([$fresh['id']]);if((int)$q->fetchColumn()>=GameConfig::MAX_GANG_MEMBERS)throw new RuntimeException('Gang capacity reached');
            $pdo->prepare('UPDATE users SET cash=cash-?,updated_at=NOW() WHERE id=?')->execute([$c['recruitment_fee'],$fresh['id']]);
            $pdo->prepare("INSERT INTO player_gang_members(user_id,npc_id,recruitment_candidate_id,salary_weekly,personal_expenses_weekly,strength,shooting,driving,intelligence,stealth,intimidation,discipline,street_knowledge,endurance,level,experience,health,max_health,morale,loyalty,status,recruited_at,last_salary_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW(),NOW(),NOW(),NOW())")->execute([$fresh['id'],$c['npc_id'],$c['id'],$c['salary_weekly'],$c['personal_expenses_weekly'],$c['strength'],$c['shooting'],$c['driving'],$c['intelligence'],$c['stealth'],$c['intimidation'],$c['discipline'],$c['street_knowledge'],$c['endurance'],$c['level'],$c['experience'],$c['health'],$c['max_health'],$c['morale'],$c['loyalty']]);
            $memberId=(int)$pdo->lastInsertId();
            $pdo->prepare("UPDATE recruitment_candidates SET status='hired',hired_by_user_id=?,hired_at=NOW() WHERE id=?")->execute([$fresh['id'],$c['id']]);
            $pdo->prepare("UPDATE npcs SET role='gang_member',status='employed',personal_cash=personal_cash+?,updated_at=NOW() WHERE id=?")->execute([$c['recruitment_fee'],$c['npc_id']]);
            (new EconomyLedgerService())->record('recruitment_fee',(int)$c['recruitment_fee'],'Recruitment fee transferred to the NPC recruit',['source_type'=>'player','source_id'=>$fresh['id'],'destination_type'=>'npc','destination_id'=>$c['npc_id'],'user_id'=>$fresh['id'],'npc_id'=>$c['npc_id'],'gang_member_id'=>$memberId,'territory_id'=>$c['territory_id']]);
            $pdo->commit();return ['message'=>'Gang member hired','gang_member_id'=>$memberId];
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    public function payOverdue(array $user,int $memberId): array
    {
        $pdo=Database::pdo();$pdo->beginTransaction();try{$q=$pdo->prepare('SELECT pgm.*,n.personal_cash FROM player_gang_members pgm JOIN npcs n ON n.id=pgm.npc_id WHERE pgm.id=? AND pgm.user_id=? FOR UPDATE');$q->execute([$memberId,$user['id']]);$m=$q->fetch();if(!$m)throw new RuntimeException('Gang member not found');$amount=(int)$m['unpaid_salary'];if($amount<=0)throw new RuntimeException('No overdue salary');$q=$pdo->prepare('SELECT cash FROM users WHERE id=? FOR UPDATE');$q->execute([$user['id']]);if((int)$q->fetchColumn()<$amount)throw new RuntimeException('Not enough cash');$pdo->prepare('UPDATE users SET cash=cash-? WHERE id=?')->execute([$amount,$user['id']]);$pdo->prepare('UPDATE npcs SET personal_cash=personal_cash+? WHERE id=?')->execute([$amount,$m['npc_id']]);$pdo->prepare('UPDATE player_gang_members SET unpaid_salary=0,morale=LEAST(100,morale+8),loyalty=LEAST(100,loyalty+5) WHERE id=?')->execute([$memberId]);(new EconomyLedgerService())->record('salary_payment',$amount,'Overdue salary paid',['source_type'=>'player','source_id'=>$user['id'],'destination_type'=>'gang_member','destination_id'=>$memberId,'user_id'=>$user['id'],'npc_id'=>$m['npc_id'],'gang_member_id'=>$memberId]);$pdo->commit();return ['message'=>'Overdue salary paid','amount'=>$amount];}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw $e;}
    }

    private function traits(int $npcId): array{$q=Database::pdo()->prepare('SELECT nt.code,nt.name,nt.polarity,nt.description,nt.effects FROM npc_trait_assignments a JOIN npc_traits nt ON nt.id=a.trait_id WHERE a.npc_id=?');$q->execute([$npcId]);$items=$q->fetchAll();foreach($items as &$i)$i['effects']=json_decode($i['effects'],true);return $items;}
}
