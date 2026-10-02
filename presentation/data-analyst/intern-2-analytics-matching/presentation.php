<?php
/** Data Analyst Intern 2: candidate scoring, analytics and job matching.
 * Presentation extract only. Source of truth: /index.php
 */

function match_requirement($resumeText,$term){
  $hay=' '.norm_key($resumeText).' ';
  foreach(keyword_variants($term) as $v){
    if(mb_strlen($v)<2) continue;
    if(mb_strpos($hay,$v)!==false){
      $pos=mb_strpos($hay,$v); $start=max(0,$pos-100); $evidence=trim(mb_substr($resumeText,$start,260));
      return ['status'=>'existing','evidence'=>$evidence];
    }
  }
  $tokens=array_values(array_filter(preg_split('/\s+/u',norm_key($term)),fn($x)=>mb_strlen($x)>=4));
  if(count($tokens)>=2){ $hit=0; foreach(array_unique($tokens) as $tk){ if(mb_strpos($hay,$tk)!==false) $hit++; } if($hit>=max(2,(int)ceil(count(array_unique($tokens))*.65))) return ['status'=>'existing','evidence'=>'Related keywords found in resume.']; }
  return ['status'=>'missing','evidence'=>''];
}

function candidate_analytics($groups,$counts,$visual=[]){
  $category=[];$weightedTotal=0.0;$weightedEarned=0.0;$existingTotal=0;$evidenceExisting=0;$reviewed=0;$all=0;$gaps=[];
  foreach($groups as $g=>$rows){
    $gt=0.0;$ge=0.0;$gc=['existing'=>0,'partial'=>0,'missing'=>0,'not_required'=>0];
    foreach($rows as $r){
      $s=$r['final_status']??($r['review_status']?:$r['auto_status']);$w=max(.01,(float)$r['weight']);$all++;
      if(!empty($r['review_status']))$reviewed++; if(isset($gc[$s]))$gc[$s]++;
      if($s!=='not_required'){$gt+=$w;$weightedTotal+=$w;$credit=$s==='existing'?1:($s==='partial'?.5:0);$ge+=$w*$credit;$weightedEarned+=$w*$credit;}
      if($s==='existing'){$existingTotal++;if(trim((string)($r['evidence']??''))!=='')$evidenceExisting++;}
      if($s==='missing')$gaps[]=['id'=>(int)$r['id'],'group'=>$g,'term'=>$r['term'],'weight'=>round($w,2),'mentions'=>(int)$r['mentions'],'impact'=>round($w*max(1,(int)$r['mentions']),2)];
    }
    $category[$g]=['existing'=>$gc['existing'],'partial'=>$gc['partial'],'missing'=>$gc['missing'],'not_required'=>$gc['not_required'],'match'=>$gt>0?round($ge/$gt*100,1):0];
  }
  usort($gaps,fn($a,$b)=>$b['impact']<=>$a['impact']);$gaps=array_slice($gaps,0,12);
  $wm=$weightedTotal>0?$weightedEarned/$weightedTotal:0;$ev=$existingTotal>0?$evidenceExisting/$existingTotal:0;$rv=$all>0?$reviewed/$all:0;
  $sm=($category['skills']['match']??0)/100;$em=($category['experience']['match']??0)/100;$pm=($category['projects']['match']??0)/100;$cm=($category['certificates']['match']??0)/100;
  $visualScore=clamp01(((float)($visual['overall_visual_score']??50))/100);$clarity=clamp01(((float)($visual['six_second_clarity_score']??50))/100);$personaAccept=0.5;$personaReject=0.5;if(!empty($visual['persona_reviews'])){$pa=[];$pr=[];foreach($visual['persona_reviews'] as $x){$pa[]=(float)($x['accept_probability']??50);$pr[]=(float)($x['reject_probability']??50);}$personaAccept=clamp01((array_sum($pa)/max(1,count($pa)))/100);$personaReject=clamp01((array_sum($pr)/max(1,count($pr)))/100);}
  $short=clamp01(.08+.34*$wm+.12*$sm+.10*$em+.06*$pm+.04*$cm+.06*$ev+.10*$visualScore+.07*$clarity+.08*$personaAccept-.05*$personaReject);
  $select=clamp01(.06+.24*$wm+.19*$em+.15*$pm+.09*$ev+.05*$rv+.08*$visualScore+.08*$personaAccept-.04*$personaReject);
  $actions=[];foreach($gaps as $x){$eff=$x['group']==='certificates'?4:($x['group']==='projects'?3:2);$action=$x['group']==='skills'?'Build and demonstrate this skill in a focused proof task.':($x['group']==='experience'?'Prepare a quantified STAR story or supervised delivery assignment.':($x['group']==='projects'?'Create a production-style project with measurable outcomes and a demo link.':'Validate whether this certification is mandatory before scheduling it.'));$actions[]=$x+['estimated_effort'=>$eff,'priority'=>round($x['impact']/max(1,$eff),2),'action'=>$action];}
  usort($actions,fn($a,$b)=>$b['priority']<=>$a['priority']);$actions=array_slice($actions,0,10);
  return ['descriptive'=>['status_counts'=>$counts,'category_match'=>$category,'weighted_match'=>round($wm*100,1),'evidence_completeness'=>round($ev*100,1),'review_completeness'=>round($rv*100,1),'visual_score'=>round($visualScore*100,1)],'diagnostic'=>['critical_missing'=>$gaps,'acceptance_causes'=>$visual['acceptance_causes']??[],'rejection_causes'=>$visual['rejection_causes']??[],'layout_gaps'=>$visual['layout_gaps']??[]],'predictive'=>['shortlist_probability'=>round($short*100,1),'interview_selection_probability'=>round($select*100,1),'persona_acceptance'=>round($personaAccept*100,1),'persona_rejection'=>round($personaReject*100,1),'method'=>'Decision-support estimate combining role evidence, market demand, resume presentation, evidence visibility and persona review. It is not a hiring guarantee.'],'prescriptive'=>['actions'=>$actions,'visual_fixes'=>$visual['priority_fixes']??[]]];
}

function profile_match_corpus($candidate,$requirements){
  $parts=[(string)($candidate['resume_text']??''),(string)($candidate['current_role']??''),(string)($candidate['target_role']??''),(string)($candidate['notes']??'')];
  foreach($requirements as $r){$st=$r['final_status']??'';if(in_array($st,['existing','partial'],true)){$parts[]=(string)$r['term'];$parts[]=(string)($r['evidence']??'');$parts[]=(string)($r['ai_evidence']??'');}}
  return implode("\n",$parts);
}

function match_job_group($items,$corpus){
  if(!is_array($items)||!$items)return ['score'=>0,'existing'=>[],'partial'=>[],'missing'=>[],'total'=>0];$tw=0;$earned=0;$ex=[];$pa=[];$mi=[];
  foreach($items as $it){if(is_string($it)){$term=trim($it);$w=1;}elseif(is_array($it)){$term=trim((string)($it['term']??$it['name']??''));$w=max(.1,(float)($it['weight']??1));}else continue;if($term==='')continue;$tw+=$w;$m=match_requirement($corpus,$term);if($m['status']==='existing'){$earned+=$w;$ex[]=$term;}else{$tokens=array_values(array_filter(preg_split('/\s+/u',norm_key($term)),fn($x)=>mb_strlen($x)>=4));$hits=0;foreach(array_unique($tokens) as $t)if(mb_strpos(norm_key($corpus),$t)!==false)$hits++;if(count($tokens)>=2&&$hits>=max(1,(int)ceil(count(array_unique($tokens))*.45))){$earned+=$w*.5;$pa[]=$term;}else $mi[]=$term;}}
  return ['score'=>$tw>0?round($earned/$tw*100,1):0,'existing'=>$ex,'partial'=>$pa,'missing'=>$mi,'total'=>count($ex)+count($pa)+count($mi)];
}

function compute_candidate_company_matches($cid,$filters=[],$options=[]){
  $pdo=db();$st=$pdo->prepare('SELECT * FROM skillsnap_candidates WHERE id=?');$st->execute([$cid]);$c=$st->fetch();if(!$c)throw new RuntimeException('Candidate not found.');
  $st=$pdo->prepare("SELECT *,COALESCE(review_status,ai_status,auto_status) final_status FROM skillsnap_candidate_requirements WHERE candidate_id=?");$st->execute([$cid]);$reqs=$st->fetchAll();$corpus=profile_match_corpus($c,$reqs);
  $dateCol=first_job_column(['created_at','posted_at','job_date','posted_date','date','CreatedAt','Date']);$applyCol=first_job_column(['Apply', 'apply_url','ApplyLink','apply_link','job_url','JobLink','URL','Link','source_url']);
  $select='`ID`,`Title`,`Company`,`Location`,`parsed_json`'.($dateCol?',`'.$dateCol.'` AS filter_date':'').($applyCol?',`'.$applyCol.'` AS apply_value':'');
  $where=['analysed=1'];$args=[];
  $start=trim((string)($filters['start_date']??''));$end=trim((string)($filters['end_date']??''));$company=trim((string)($filters['company']??''));$location=trim((string)($filters['location']??''));$q=trim((string)($filters['q']??''));
  if($dateCol&&$start!==''){$where[]='DATE(`'.$dateCol.'`)>=?';$args[]=$start;}if($dateCol&&$end!==''){$where[]='DATE(`'.$dateCol.'`)<=?';$args[]=$end;}
  if($company!==''){$where[]='`Company` LIKE ?';$args[]='%'.$company.'%';}if($location!==''){$where[]='`Location` LIKE ?';$args[]='%'.$location.'%';}
  if($q!==''){$where[]='(`Title` LIKE ? OR `Company` LIKE ? OR `Location` LIKE ? OR `parsed_json` LIKE ?)';$args=array_merge($args,array_fill(0,4,'%'.$q.'%'));}
  $quick=!empty($options['quick']);$rowLimit=$quick?max(80,min(400,(int)($options['row_limit']??220))):1500;$resultLimit=$quick?max(20,min(120,(int)($options['result_limit']??60))):300;$deadline=$quick?(microtime(true)+max(1.5,min(6.0,(float)($options['seconds']??3.5)))):0;
  $sql='SELECT '.$select.' FROM skillsnap_job_details WHERE '.implode(' AND ',$where).' ORDER BY `ID` DESC LIMIT '.$rowLimit;$st=$pdo->prepare($sql);$st->execute($args);
  $jobs=[];$scanned=0;while($r=$st->fetch()){if($quick&&$deadline>0&&microtime(true)>=$deadline)break;$scanned++;$pj=decode_json($r['parsed_json']??'');$groups=[];foreach(['skills','experience','projects','certificates'] as $g)$groups[$g]=match_job_group($pj[$g]??($g==='certificates'?($pj['certifications']??[]):[]),$corpus);
    $weights=['skills'=>.40,'experience'=>.30,'projects'=>.20,'certificates'=>.10];$active=0;$earned=0;foreach($groups as $g=>$x){if($x['total']>0){$active+=$weights[$g];$earned+=$weights[$g]*$x['score'];}}$score=$active>0?round($earned/$active,1):0;
    $apply=trim((string)($r['apply_value']??''));if($apply!==''&&!preg_match('~^https?://~i',$apply))global $SKILLSNAP_EXTERNAL_BASE_URL; $apply=$SKILLSNAP_EXTERNAL_BASE_URL.'/'.ltrim($apply,'/');
    $jobs[]=['job_id'=>(int)$r['ID'],'company'=>(string)$r['Company'],'title'=>(string)$r['Title'],'location'=>(string)$r['Location'],'date'=>(string)($r['filter_date']??''),'salary'=>job_salary_display($pj),'apply_url'=>$apply,'overall_score'=>$score,'groups'=>$groups];
  }
  usort($jobs,fn($a,$b)=>($b['overall_score']<=>$a['overall_score'])?:strcmp($a['company'],$b['company']));
  return ['jobs'=>array_slice($jobs,0,$resultLimit),'total'=>count($jobs),'scanned'=>$scanned,'quick'=>$quick,'date_filter_available'=>(bool)$dateCol,'generated_at'=>now_sql()];
}
