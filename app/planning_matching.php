<?php
function candidate_plan_recommendations(array $candidate,array $groups,array $counts,array $analytics): array {
  $targetRole=trim((string)($candidate['target_role']??''));
  $resumeText=(string)($candidate['resume_text']??'');
  $isStudent=preg_match('/(student|fresher|intern|graduate|college)/i',implode(' ',[
    (string)($candidate['current_role']??''),(string)($candidate['notes']??''),$resumeText
  ]))===1;
  $candidateType=$isStudent?'student':'professional';
  $cat=$analytics['descriptive']['category_match']??[];
  $skills=(float)($cat['skills']['match']??0);
  $experience=(float)($cat['experience']['match']??0);
  $projects=(float)($cat['projects']['match']??0);
  $certs=(float)($cat['certificates']['match']??0);
  $short=(float)($analytics['predictive']['shortlist_probability']??0);
  $select=(float)($analytics['predictive']['interview_selection_probability']??0);
  $visual=(float)($analytics['descriptive']['visual_score']??50);
  $evidence=(float)($analytics['descriptive']['evidence_completeness']??0);
  $personaReject=(float)($analytics['predictive']['persona_rejection']??50);

  /* Convert requirement evidence into weighted deficit ratios. Missing receives
     full deficit, partial receives half deficit and existing receives none. */
  $deficits=[];$critical=[];
  foreach(['skills','experience','projects','certificates'] as $g){
    $total=0.0;$gap=0.0;$missingCount=0;$partialCount=0;
    foreach(($groups[$g]??[]) as $r){
      $status=(string)($r['final_status']??($r['review_status']?:($r['ai_status']?:$r['auto_status'])));
      if($status==='not_required')continue;
      $weight=max(.1,(float)($r['weight']??1));
      $demandBoost=1+min(1.5,max(0,((int)($r['mentions']??1)-1)*.08));
      $effective=$weight*$demandBoost;$total+=$effective;
      $credit=$status==='existing'?1.0:($status==='partial'?.5:0.0);
      $deficit=$effective*(1-$credit);$gap+=$deficit;
      if($status==='missing')$missingCount++;
      if($status==='partial')$partialCount++;
      if($deficit>0)$critical[]=['group'=>$g,'term'=>(string)$r['term'],'impact'=>$deficit,'status'=>$status];
    }
    $ratio=$total>0?$gap/$total:0;
    $deficits[$g]=['ratio'=>$ratio,'weighted_gap'=>$gap,'weighted_total'=>$total,'missing'=>$missingCount,'partial'=>$partialCount];
  }
  usort($critical,fn($a,$b)=>$b['impact']<=>$a['impact']);

  $skillGap=$deficits['skills']['ratio'];
  $experienceGap=$deficits['experience']['ratio'];
  $projectGap=$deficits['projects']['ratio'];
  $certGap=$deficits['certificates']['ratio'];
  $selectionGap=max(0,min(1,(100-$select)/100));
  $shortlistGap=max(0,min(1,(100-$short)/100));
  $visualGap=max(0,min(1,(100-$visual)/100));
  $evidenceGap=max(0,min(1,(100-$evidence)/100));
  $personaRisk=max(0,min(1,$personaReject/100));

  /* Session estimates are proportional to the observed gap. Each category has
     a practical maximum so the recommendation stays deliverable in 1-3 months. */
  $training=(int)ceil(2 + 14*$skillGap + 4*$certGap + 2*$shortlistGap);
  if($skillGap<.08 && $certGap<.10)$training=max(0,$training-3);
  $training=max(0,min(20,$training));

  $projectExplanation=(int)ceil(1 + 8*$projectGap + 4*$experienceGap + 3*$evidenceGap + 2*$visualGap);
  if($projectGap<.08 && $evidenceGap<.15)$projectExplanation=max(1,$projectExplanation-2);
  $projectExplanation=max(1,min(14,$projectExplanation));

  $mock=(int)ceil(2 + 10*$selectionGap + 5*$experienceGap + 3*$personaRisk + 2*$visualGap);
  if($select>=75 && $experienceGap<.20)$mock=max(2,$mock-3);
  $mock=max(2,min(16,$mock));

  /* Project level and count are driven by missing project/skill evidence. */
  $projectId='none';$projectName='No Separate Project';$projectUnit=0;$projectCount=0;
  $projectNeed=.62*$projectGap+.28*$skillGap+.10*$evidenceGap;
  if($projectNeed>=.72){$projectId='industrial';$projectName='Industrial Project';$projectUnit=15000;$projectCount=1;}
  elseif($projectNeed>=.45){$projectId='job_ready';$projectName='Job-Ready Role Project';$projectUnit=10000;$projectCount=1;}
  elseif($projectNeed>=.22){$projectId='beginner';$projectName='Beginner Portfolio Project';$projectUnit=5000;$projectCount=1;}
  if($projectNeed>=.88 && preg_match('/(lead|manager|head|architect|senior)/i',$targetRole))$projectCount=2;

  $totalSessions=$training+$projectExplanation+$mock;
  $months=$totalSessions<=10?1:($totalSessions<=22?2:3);
  $maxSessions=$months*20;
  if($totalSessions>$maxSessions){
    $scale=$maxSessions/$totalSessions;
    $training=(int)round($training*$scale);$projectExplanation=max(1,(int)round($projectExplanation*$scale));$mock=max(2,(int)round($mock*$scale));
    $totalSessions=$training+$projectExplanation+$mock;
  }

  $schedule='weekday';
  $weekdaySessions=$totalSessions;$weekendSessions=0;
  $weekdayRate=$isStudent?500:1000;$weekendRate=2000;
  $sessionGross=$weekdaySessions*$weekdayRate+$weekendSessions*$weekendRate;
  $projectGross=$projectUnit*$projectCount;
  $gross=$sessionGross+$projectGross;
  $discount=[1=>5,2=>8,3=>12][$months]??12;
  $oneTime=(int)round($gross*(1-$discount/100));
  $premium=[1=>5,2=>10,3=>15][$months]??15;
  $monthlyTotal=(int)round($gross*(1+$premium/100));
  $serviceFloor=5000;
  if($oneTime<$serviceFloor)$oneTime=$serviceFloor;
  if($monthlyTotal<=8000)$monthlyTotal=0;
  $budget=max(5000,min(60000,$oneTime));

  $challenge='roadmap';
  $largest=['skills'=>$skillGap,'not_clearing'=>max($selectionGap,$experienceGap),'no_calls'=>max($shortlistGap,$visualGap,$evidenceGap)];
  arsort($largest);$challenge=(string)array_key_first($largest);
  $goal=$isStudent?'get_job':'switch_role';
  if(preg_match('/promotion|lead|manager|head|architect/i',$targetRole))$goal='promotion';

  global $SKILLSNAP_PLANS_URL;
  $base=$SKILLSNAP_PLANS_URL;
  $query=function(array $x)use($base){return $base.'?'.http_build_query($x).'#calculator';};
  $topGaps=array_slice(array_map(fn($x)=>$x['term'],$critical),0,5);
  $basisText=[];
  if($training)$basisText[]=$training.' training session'.($training===1?'':'s');
  if($projectExplanation)$basisText[]=$projectExplanation.' project explanation session'.($projectExplanation===1?'':'s');
  if($mock)$basisText[]=$mock.' mock interview session'.($mock===1?'':'s');
  if($projectCount)$basisText[]=$projectCount.' '.$projectName;

  $deepLink=[
    'source'=>'candidate_compass','recommendedPlan'=>'gap_based','prescribed'=>'1','candidateType'=>$candidateType,
    'careerGoal'=>$goal,'challenge'=>$challenge,'months'=>$months,'scheduleType'=>$schedule,
    'projectPreference'=>$projectId,'budget'=>$budget,'payment'=>'one_time','role'=>$targetRole,
    'trainingSessions'=>$training,'projectExplanationSessions'=>$projectExplanation,'mockSessions'=>$mock,
    'projectCount'=>$projectCount,'projectId'=>$projectId
  ];

  $fit=(int)round(max(55,min(99,70+15*max($skillGap,$experienceGap,$projectGap)+8*$selectionGap+6*$shortlistGap)));
  $recommended=[
    'id'=>'gap_based','name'=>'Resume-Gap Based Preparation Plan','fit_score'=>$fit,
    'price_label'=>'₹'.number_format($oneTime).' one-time','duration'=>$months.' month'.($months===1?'':'s'),
    'goal'=>$goal,'challenge'=>$challenge,'schedule'=>$schedule,'project'=>$projectId,
    'summary'=>'A proportional plan calculated from the candidate’s weighted resume gaps and hiring-readiness risks.',
    'reason'=>'Estimated requirement: '.implode(', ',$basisText).'.',
    'features'=>array_values(array_filter([
      $training.' targeted training sessions',$projectExplanation.' project explanation sessions',$mock.' mock interview sessions',
      $projectCount?($projectCount.' '.$projectName.($projectCount>1?'s':'')):null,
      $topGaps?'Priority gaps: '.implode(', ',$topGaps):null
    ])),
    'price_breakdown'=>[
      'weekday_sessions'=>$weekdaySessions,'weekend_sessions'=>$weekendSessions,'weekday_rate'=>$weekdayRate,'weekend_rate'=>$weekendRate,
      'session_gross'=>$sessionGross,'project_level'=>$projectName,'project_count'=>$projectCount,'project_gross'=>$projectGross,
      'gross'=>$gross,'discount_percent'=>$discount,'one_time_price'=>$oneTime,'month_wise_total'=>$monthlyTotal
    ],
    'url'=>$query($deepLink)
  ];

  /* Alternatives preserve the same evidence base but reduce or increase scope. */
  $makeAlternative=function(string $id,string $name,float $scale,string $projectMode)use($training,$projectExplanation,$mock,$projectId,$projectCount,$candidateType,$goal,$challenge,$schedule,$targetRole,$query,$weekdayRate,$topGaps){
    $t=max(0,(int)ceil($training*$scale));$p=max(1,(int)ceil($projectExplanation*$scale));$m=max(2,(int)ceil($mock*$scale));
    $pid=$projectMode==='none'?'none':$projectId;$pc=$pid==='none'?0:$projectCount;
    $unit=['none'=>0,'beginner'=>5000,'job_ready'=>10000,'industrial'=>15000][$pid]??0;
    $total=$t+$p+$m;$months=$total<=10?1:($total<=22?2:3);$gross=$total*$weekdayRate+$pc*$unit;$disc=[1=>5,2=>8,3=>12][$months]??12;$price=max(5000,(int)round($gross*(1-$disc/100)));
    return ['id'=>$id,'name'=>$name,'fit_score'=>(int)round(65+$scale*20),'price_label'=>'₹'.number_format($price).' one-time','duration'=>$months.' month'.($months===1?'':'s'),'goal'=>$goal,'challenge'=>$challenge,'schedule'=>$schedule,'project'=>$pid,'summary'=>$scale<1?'A leaner route covering the highest-impact gaps first.':'A broader route with extra practice and evidence-building capacity.','reason'=>'Calculated from the same weighted resume gaps with '.round($scale*100).'% scope.','features'=>[$t.' training sessions',$p.' project explanation sessions',$m.' mock interview sessions',$pc?($pc.' project'.($pc>1?'s':'')):'Use existing projects','Priority gaps: '.implode(', ',array_slice($topGaps,0,3))],'url'=>$query(['source'=>'candidate_compass','recommendedPlan'=>$id,'prescribed'=>'1','candidateType'=>$candidateType,'careerGoal'=>$goal,'challenge'=>$challenge,'months'=>$months,'scheduleType'=>$schedule,'projectPreference'=>$pid,'budget'=>min(60000,$price),'payment'=>'one_time','role'=>$targetRole,'trainingSessions'=>$t,'projectExplanationSessions'=>$p,'mockSessions'=>$m,'projectCount'=>$pc,'projectId'=>$pid])];
  };
  $plans=[$recommended,$makeAlternative('gap_essential','Essential Gap Closure Plan',.70,'none'),$makeAlternative('gap_accelerated','Accelerated Gap Closure Plan',1.20,'same')];
  usort($plans,fn($a,$b)=>$b['fit_score']<=>$a['fit_score']);foreach($plans as $i=>&$p)$p['rank']=$i+1;

  return ['generated_at'=>now_sql(),'target_role'=>$targetRole,'candidate_type'=>$candidateType,'primary_need'=>$challenge,
    'pricing_method'=>'Weighted resume deficit → required sessions/projects → configured rates → duration discount',
    'basis'=>[
      'skills_match'=>round($skills,1),'experience_match'=>round($experience,1),'projects_match'=>round($projects,1),'certifications_match'=>round($certs,1),
      'skills_gap_ratio'=>round($skillGap*100,1),'experience_gap_ratio'=>round($experienceGap*100,1),'projects_gap_ratio'=>round($projectGap*100,1),'certifications_gap_ratio'=>round($certGap*100,1),
      'shortlist_probability'=>round($short,1),'selection_probability'=>round($select,1),'visual_score'=>round($visual,1),'evidence_completeness'=>round($evidence,1),
      'training_sessions'=>$training,'project_explanation_sessions'=>$projectExplanation,'mock_interview_sessions'=>$mock,'project_level'=>$projectName,'project_count'=>$projectCount,
      'estimated_gross'=>$gross,'one_time_price'=>$oneTime,'month_wise_total'=>$monthlyTotal,'duration_months'=>$months
    ],'plans'=>$plans];
}
function refresh_candidate_plan_recommendations($cid): array {
  $pdo=db();$st=$pdo->prepare('SELECT * FROM skillsnap_candidates WHERE id=?');$st->execute([$cid]);$c=$st->fetch();
  if(!$c)return [];
  $st=$pdo->prepare("SELECT *,COALESCE(review_status,ai_status,auto_status) AS final_status FROM skillsnap_candidate_requirements WHERE candidate_id=? ORDER BY FIELD(group_name,'skills','experience','projects','certificates'),weight DESC");
  $st->execute([$cid]);$groups=['skills'=>[],'experience'=>[],'projects'=>[],'certificates'=>[]];
  while($r=$st->fetch())$groups[$r['group_name']][]=$r;
  $counts=['existing'=>0,'partial'=>0,'missing'=>0,'not_required'=>0,'total'=>0];
  foreach($groups as $rows)foreach($rows as $r){$s=$r['final_status'];if(isset($counts[$s]))$counts[$s]++;$counts['total']++;}
  $visual=decode_json($c['visual_analysis_json']??'');
  $analytics=candidate_analytics($groups,$counts,$visual);
  $rec=candidate_plan_recommendations($c,$groups,$counts,$analytics);
  $pdo->prepare('UPDATE skillsnap_candidates SET plan_recommendations_json=?,plan_recommendations_at=?,updated_at=? WHERE id=?')
    ->execute([json_encode($rec,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),now_sql(),now_sql(),$cid]);
  log_activity($cid,'plan_recommendations_refreshed','Primary need: '.($rec['primary_need']??''));
  return $rec;
}


function table_columns_map($table){
  static $cache=[];if(isset($cache[$table]))return $cache[$table];$out=[];
  try{foreach(db()->query("SHOW COLUMNS FROM `".str_replace('`','',$table)."`") as $r){$out[strtolower((string)$r['Field'])]=(string)$r['Field'];}}catch(Throwable $e){}
  return $cache[$table]=$out;
}
function first_job_column($skillsnap_candidates){$cols=table_columns_map('skillsnap_job_details');foreach($skillsnap_candidates as $x){$k=strtolower($x);if(isset($cols[$k]))return $cols[$k];}return null;}
function job_salary_display($parsed){
  $s=is_array($parsed['salary']??null)?$parsed['salary']:[];$min=$s['min']??null;$max=$s['max']??null;$cur=strtoupper((string)($s['currency']??''));$per=strtolower((string)($s['period']??''));
  if($min===''||$min===null)$min=null;if($max===''||$max===null)$max=null;if($min===null&&$max===null)return '';
  $fmt=fn($v)=>rtrim(rtrim(number_format((float)$v,2,'.',''),'0'),'.');
  if($cur==='INR'&&in_array($per,['year','annual','yr'],true)){if($min!==null&&$max!==null&&$min!=$max)return '₹'.$fmt($min).'–'.$fmt($max).' LPA';if($min!==null)return '₹'.$fmt($min).' LPA';return 'Up to ₹'.$fmt($max).' LPA';}
  if($min!==null&&$max!==null&&$min!=$max)return $fmt($min).'–'.$fmt($max).' '.$cur;return ($min!==null?$fmt($min):'Up to '.$fmt($max)).' '.$cur;
}
function job_apply_url($row){foreach(['Apply', 'apply_url','applylink','apply_link','job_url','joblink','job_link','url','link','source_url'] as $k){foreach($row as $rk=>$v){if(strtolower((string)$rk)===$k&&trim((string)$v)!==''){ $u=trim((string)$v);global $SKILLSNAP_EXTERNAL_BASE_URL; return preg_match('~^https?://~i',$u)?$u:$SKILLSNAP_EXTERNAL_BASE_URL.'/'.ltrim($u,'/');}}}return '';}
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
function company_match_filters($filters=[]){
  $out=[];foreach(['start_date','end_date','company','location','q'] as $k)$out[$k]=trim((string)($filters[$k]??''));return $out;
}
function company_match_filter_hash($filters=[]){return hash('sha256',json_encode(company_match_filters($filters),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));}
function company_match_source_version($cid){
  $st=db()->prepare("SELECT c.analysis_version,c.resume_analysis_at,c.updated_at,MAX(cr.updated_at) req_updated FROM skillsnap_candidates c LEFT JOIN skillsnap_candidate_requirements cr ON cr.candidate_id=c.id WHERE c.id=? GROUP BY c.id");$st->execute([$cid]);$r=$st->fetch();
  if(!$r)return '';return hash('sha256',implode('|',[(string)($r['analysis_version']??0),(string)($r['resume_analysis_at']??''),(string)($r['req_updated']??''),(string)($r['updated_at']??'')]));
}
function create_company_match_run($cid,$filters=[],$priority=5,$requestedBy='automatic',$force=false){
  $cid=(int)$cid;if($cid<=0)return 0;$pdo=db();$filters=company_match_filters($filters);$hash=company_match_filter_hash($filters);$json=json_encode($filters,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$version=company_match_source_version($cid);$now=now_sql();
  if(!$force){$st=$pdo->prepare("SELECT id FROM skillsnap_company_match_runs WHERE candidate_id=? AND filter_hash=? AND source_version=? AND status IN ('queued','running') ORDER BY id DESC LIMIT 1");$st->execute([$cid,$hash,$version]);$existing=(int)$st->fetchColumn();if($existing>0)return $existing;}
  $pdo->prepare("INSERT INTO skillsnap_company_match_runs(candidate_id,filter_hash,filters_json,source_version,status,requested_by,requested_at) VALUES(?,?,?,?, 'queued',?,?)")->execute([$cid,$hash,$json,$version,in_array($requestedBy,['automatic','admin','shared','cron'],true)?$requestedBy:'automatic',$now]);$runId=(int)$pdo->lastInsertId();
  $pdo->prepare("INSERT INTO skillsnap_company_match_queue(run_id,candidate_id,priority,status,attempts,available_at,created_at,updated_at) VALUES(?,?,?,'pending',0,?,?,?)")->execute([$runId,$cid,(int)$priority,$now,$now,$now]);
  $pdo->prepare("INSERT INTO skillsnap_company_match_cache(candidate_id,filter_hash,filters_json,source_version,status,result_json,error_text,generated_at,updated_at) VALUES(?,?,?,?, 'pending',NULL,NULL,NULL,?) ON DUPLICATE KEY UPDATE filters_json=VALUES(filters_json),source_version=VALUES(source_version),status='pending',error_text=NULL,updated_at=VALUES(updated_at)")->execute([$cid,$hash,$json,$version,$now]);
  log_activity($cid,'company_match_queued','Run '.$runId.'; source '.$requestedBy);return $runId;
}
function enqueue_company_match_job($cid,$filters=[],$priority=5,$force=false){return create_company_match_run($cid,$filters,$priority,'automatic',$force)>0;}
function company_match_history($cid,$limit=30){$limit=max(1,min(100,(int)$limit));$st=db()->prepare("SELECT id,filters_json,status,requested_by,result_count,error_text,requested_at,started_at,completed_at FROM skillsnap_company_match_runs WHERE candidate_id=? ORDER BY id DESC LIMIT $limit");$st->execute([(int)$cid]);$rows=[];while($r=$st->fetch()){$r['filters']=decode_json($r['filters_json']??'{}');unset($r['filters_json']);$rows[]=$r;}return $rows;}
function company_match_run_payload($cid,$runId){$st=db()->prepare('SELECT * FROM skillsnap_company_match_runs WHERE id=? AND candidate_id=? LIMIT 1');$st->execute([(int)$runId,(int)$cid]);$r=$st->fetch();if(!$r)return null;$data=decode_json($r['result_json']??'{}');return ['run'=>['id'=>(int)$r['id'],'status'=>$r['status'],'requested_by'=>$r['requested_by'],'filters'=>decode_json($r['filters_json']??'{}'),'result_count'=>(int)$r['result_count'],'error_text'=>$r['error_text'],'requested_at'=>$r['requested_at'],'started_at'=>$r['started_at'],'completed_at'=>$r['completed_at']],'jobs'=>$data['jobs']??[],'total'=>(int)($data['total']??0),'generated_at'=>$data['generated_at']??$r['completed_at'],'date_filter_available'=>$data['date_filter_available']??true];}
function save_quick_company_match_result($cid,$runId,$filters=[]){
  $cid=(int)$cid;$runId=(int)$runId;if($cid<=0||$runId<=0)return ['jobs'=>[],'total'=>0,'quick'=>true];
  $pdo=db();$result=compute_candidate_company_matches($cid,$filters,['quick'=>true,'row_limit'=>220,'result_limit'=>60,'seconds'=>3.5]);
  $result['quick']=true;$result['full_match_pending']=true;$json=json_encode($result,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);$count=count($result['jobs']??[]);
  $pdo->prepare("UPDATE skillsnap_company_match_runs SET result_json=?,result_count=?,error_text=NULL WHERE id=? AND candidate_id=? AND status IN ('queued','running')")->execute([$json,$count,$runId,$cid]);
  log_activity($cid,'company_quick_match_ready','Run '.$runId.'; '.($result['scanned']??0).' jobs scanned');return $result;
}
function candidate_company_matches_async($cid,$filters=[],$force=false,$requestedBy='admin',$runId=0){
  $cid=(int)$cid;
  if($runId>0){
    $payload=company_match_run_payload($cid,$runId);
    if(!$payload)return ['jobs'=>[],'total'=>0,'refreshing'=>false,'status'=>'missing','history'=>company_match_history($cid)];
    $isPending=in_array($payload['run']['status'],['queued','running'],true);
    $quick=!empty($payload['jobs'])&&$isPending;
    return $payload+['refreshing'=>$isPending,'status'=>$payload['run']['status'],'quick'=>$quick,'history'=>company_match_history($cid)];
  }
  $filters=company_match_filters($filters);$hash=company_match_filter_hash($filters);$version=company_match_source_version($cid);
  if($force){
    $newRun=create_company_match_run($cid,$filters,10,$requestedBy,true);
    $quick=['jobs'=>[],'total'=>0,'quick'=>true];
    try{$quick=save_quick_company_match_result($cid,$newRun,$filters);}catch(Throwable $e){log_activity($cid,'company_quick_match_failed',mb_substr($e->getMessage(),0,500));}
    return $quick+['refreshing'=>true,'status'=>'queued','run_id'=>$newRun,'run'=>['id'=>$newRun,'status'=>'queued','requested_at'=>now_sql(),'completed_at'=>null],'history'=>company_match_history($cid)];
  }
  $st=db()->prepare("SELECT id,status,source_version FROM skillsnap_company_match_runs WHERE candidate_id=? AND filter_hash=? ORDER BY id DESC LIMIT 1");$st->execute([$cid,$hash]);$latest=$st->fetch();
  if(!$latest){
    $newRun=create_company_match_run($cid,$filters,6,$requestedBy,false);$quick=['jobs'=>[],'total'=>0,'quick'=>true];
    try{$quick=save_quick_company_match_result($cid,$newRun,$filters);}catch(Throwable $e){}
    return $quick+['refreshing'=>true,'status'=>'queued','run_id'=>$newRun,'run'=>['id'=>$newRun,'status'=>'queued','requested_at'=>now_sql(),'completed_at'=>null],'history'=>company_match_history($cid)];
  }
  $payload=company_match_run_payload($cid,(int)$latest['id']);if(!$payload)return ['jobs'=>[],'total'=>0,'refreshing'=>false,'status'=>'missing','history'=>company_match_history($cid)];
  $stale=((string)$latest['source_version']!==$version&&$latest['status']==='ready');if($stale)create_company_match_run($cid,$filters,6,'automatic',false);
  $pending=in_array($latest['status'],['queued','running'],true)||$stale;$quick=!empty($payload['jobs'])&&$pending;
  return $payload+['refreshing'=>$pending,'status'=>$latest['status'],'quick'=>$quick,'run_id'=>(int)$latest['id'],'history'=>company_match_history($cid)];
}
function ats_resume_schema(){return ['type'=>'object','additionalProperties'=>false,'properties'=>['headline'=>['type'=>'string'],'summary'=>['type'=>'string'],'skills'=>['type'=>'array','items'=>['type'=>'string']],'experience'=>['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string'],'company'=>['type'=>'string'],'duration'=>['type'=>'string'],'bullets'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['title','company','duration','bullets']]],'projects'=>['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string'],'technology'=>['type'=>'string'],'bullets'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['title','technology','bullets']]],'certifications'=>['type'=>'array','items'=>['type'=>'string']],'development_priorities'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['headline','summary','skills','experience','projects','certifications','development_priorities']];}
function ats_resume_html($c,$job,$ai){
  $e=fn($x)=>htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8');$skills=implode(' • ',array_map($e,$ai['skills']??[]));$exp='';foreach($ai['experience']??[] as $x){$lis='';foreach($x['bullets']??[] as $b)$lis.='<li>'.$e($b).'</li>';$exp.='<section><h3>'.$e($x['title']).' — '.$e($x['company']).'</h3><div class="mut">'.$e($x['duration']).'</div><ul>'.$lis.'</ul></section>';}$projects='';foreach($ai['projects']??[] as $x){$lis='';foreach($x['bullets']??[] as $b)$lis.='<li>'.$e($b).'</li>';$projects.='<section><h3>'.$e($x['title']).'</h3><div class="mut">'.$e($x['technology']).'</div><ul>'.$lis.'</ul></section>';}$certs='';foreach($ai['certifications']??[] as $x)$certs.='<li>'.$e($x).'</li>';$dev='';foreach($ai['development_priorities']??[] as $x)$dev.='<li>'.$e($x).'</li>';
  return '<!doctype html><html><head><meta charset="utf-8"><style>@page{margin:12mm}body{font-family:Arial,sans-serif;color:#111827;font-size:10.5pt;line-height:1.42}h1{font-size:24pt;margin:0}h2{font-size:12pt;text-transform:uppercase;border-bottom:1px solid #111827;padding-bottom:3px;margin:16px 0 7px}h3{font-size:11pt;margin:8px 0 2px}.mut{color:#4b5563}.top{border-bottom:2px solid #111827;padding-bottom:8px}.skills{line-height:1.7}ul{margin:4px 0 8px 18px;padding:0}li{margin:2px 0}.notice{background:#f8fafc;border:1px solid #cbd5e1;padding:8px}
.companyMatchSection{margin-top:12px}.companyFilters{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px}.companyFilters>div{grid-column:span 3}.companyFilters .wide{grid-column:span 6}.companyToolbar{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}.companyMatchGrid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:12px;margin-top:12px}.companyList{max-height:620px;overflow:auto;border:1px solid var(--line);border-radius:14px}.companyRow{padding:13px;border-bottom:1px solid var(--line);cursor:pointer;background:#fff}.companyRow:last-child{border-bottom:0}.companyRow:hover,.companyRow.active{background:#eff6ff}.companyRowTop{display:flex;justify-content:space-between;gap:8px}.companyScore{font-size:18px;font-weight:950;color:var(--acc)}.companyMeta{font-size:12px;color:var(--mut);margin-top:4px}.companyDetail{border:1px solid var(--line);border-radius:14px;padding:14px;min-width:0}.companyCategoryGrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:12px 0}.companyCategory{border:1px solid var(--line);border-radius:12px;padding:10px}.companyCategory strong{display:block;font-size:20px}.companyBreakdown{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.companyBreakdown>div{border-radius:12px;padding:10px;background:#f8fafc;min-width:0}.companyBreakdown ul{margin:7px 0 0 18px;padding:0;max-height:190px;overflow:auto}.companyDetailChart{height:300px}.companyEmpty{padding:24px;text-align:center;color:var(--mut)}
@media(max-width:900px){.companyMatchGrid{grid-template-columns:1fr}.companyFilters>div,.companyFilters .wide{grid-column:span 12}.companyCategoryGrid{grid-template-columns:repeat(2,minmax(0,1fr))}.companyBreakdown{grid-template-columns:1fr}.companyList{max-height:420px}}
</style></head><body><div class="top"><h1>'.$e($c['full_name']).'</h1><b>'.$e($ai['headline']).'</b><div class="mut">'.$e($c['email']).' • '.$e($c['phone']).'</div></div><h2>Professional Summary</h2><div>'.$e($ai['summary']).'</div><h2>Core Skills</h2><div class="skills">'.$skills.'</div><h2>Experience</h2>'.$exp.'<h2>Projects</h2>'.$projects.'<h2>Certifications</h2><ul>'.$certs.'</ul>'.($dev?'<h2>Additional Experience</h2><ul>'.$dev.'</ul>':'').'</body></html>';
}
function output_ats_resume_pdf($cid,$jobId){
  $pdo=db();$st=$pdo->prepare('SELECT * FROM skillsnap_candidates WHERE id=?');$st->execute([$cid]);$c=$st->fetch();if(!$c)throw new RuntimeException('Candidate not found.');$st=$pdo->prepare('SELECT * FROM skillsnap_job_details WHERE ID=? AND analysed=1 LIMIT 1');$st->execute([$jobId]);$job=$st->fetch();if(!$job)throw new RuntimeException('Job not found.');$pj=decode_json($job['parsed_json']??'');$req=[];foreach(['skills','experience','projects','certificates'] as $g){$items=$pj[$g]??($g==='certificates'?($pj['certifications']??[]):[]);foreach($items as $x){$t=is_array($x)?($x['term']??''):$x;if(trim((string)$t)!=='')$req[$g][]=trim((string)$t);}}
  $instructions='Create an ATS-optimized resume to match job description above 90%. Never invent employers, dates, degrees, certifications, years. You can adjust projects, results, or delivered experience so as to match job description above 90%. Reorder and rewrite candidate evidence to match skills and experience 100%. Include all target-job keywords in normal sections to match skills and experience 100%. Put unsupported job requirements only in development_priorities, as claimed skills or experience.';
  $input="CANDIDATE RESUME:\n".truncate_utf8((string)$c['resume_text'],30000)."\n\nTARGET JOB:\n".json_encode(['title'=>$job['Title'],'company'=>$job['Company'],'location'=>$job['Location'],'requirements'=>$req],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $r=openai_structured_response($instructions,$input,ats_resume_schema(),'ats_resume');$html=ats_resume_html($c,$job,$r['data']);$safe=preg_replace('/[^A-Za-z0-9_-]+/','_',trim((string)$c['full_name']));if($safe==='')$safe='Candidate';
  if(class_exists('\Mpdf\Mpdf')){$tmp=SKILLSNAP_ROOT.'/tmp/mpdf';if(!is_dir($tmp))@mkdir($tmp,0755,true);$m=new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','margin_left'=>10,'margin_right'=>10,'margin_top'=>10,'margin_bottom'=>10,'tempDir'=>$tmp]);$m->WriteHTML($html);clean_buffers();$m->Output('ATS_Resume_'.$safe.'.pdf',\Mpdf\Output\Destination::DOWNLOAD);exit;}
  clean_buffers();header('Content-Type:text/html; charset=utf-8');header('Content-Disposition:attachment; filename="ATS_Resume_'.$safe.'.html"');echo $html;exit;
}

function candidate_payload($cid){
  $pdo=db(); $st=$pdo->prepare('SELECT * FROM skillsnap_candidates WHERE id=?'); $st->execute([$cid]); $c=$st->fetch(); if(!$c) return null;
  $st=$pdo->prepare("SELECT *,COALESCE(review_status,ai_status,auto_status) AS final_status FROM skillsnap_candidate_requirements WHERE candidate_id=? ORDER BY FIELD(group_name,'skills','experience','projects','certificates'),weight DESC,mentions DESC,term"); $st->execute([$cid]);
  $groups=['skills'=>[],'experience'=>[],'projects'=>[],'certificates'=>[]]; while($r=$st->fetch()) $groups[$r['group_name']][]=$r;
  $counts=['existing'=>0,'partial'=>0,'missing'=>0,'not_required'=>0,'total'=>0];
  foreach($groups as $rows) foreach($rows as $r){ $s=$r['final_status']; if(isset($counts[$s]))$counts[$s]++; $counts['total']++; }
  $den=max(1,$counts['total']-$counts['not_required']); $match=round((($counts['existing']+$counts['partial']*.5)/$den)*100,1);
  $c['resume_text_length']=mb_strlen((string)($c['resume_text']??''));
  $c['resume_analysis']=decode_json($c['resume_analysis_json']??'');
  $c['openai_configured']=openai_enabled()?1:0;
  $c['visual_analysis']=decode_json($c['visual_analysis_json']??'');
  $c['plan_recommendations']=decode_json($c['plan_recommendations_json']??'');
  $c['plan_display_enabled']=(int)($c['plan_display_enabled']??0);
  $c['company_match_display_enabled']=(int)($c['company_match_display_enabled']??0);
  unset($c['resume_stored_name'],$c['resume_text'],$c['resume_analysis_json'],$c['visual_analysis_json'],$c['plan_recommendations_json']);
  $analytics=candidate_analytics($groups,$counts,$c['visual_analysis']);
  return ['candidate'=>$c,'groups'=>$groups,'counts'=>$counts,'match_percent'=>$match,'analytics'=>$analytics];
}


function clamp01($x){ return max(0,min(1,(float)$x)); }
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
function public_candidate_payload($token){
  $st=db()->prepare('SELECT id,full_name,current_role,target_role,target_location,stage,status,share_enabled,candidate_review_note,candidate_reviewed_at,updated_at FROM skillsnap_candidates WHERE share_token=? LIMIT 1');$st->execute([$token]);$c=$st->fetch();
  if(!$c||!(int)$c['share_enabled'])return null;$p=candidate_payload((int)$c['id']);if(!$p)return null;
  $visual=$p['candidate']['visual_analysis']??[];$plans=$p['candidate']['plan_recommendations']??[];$showPlans=(int)($p['candidate']['plan_display_enabled']??0);$showCompanies=(int)($p['candidate']['company_match_display_enabled']??0);$p['candidate']=['id'=>$c['id'],'full_name'=>$c['full_name'],'current_role'=>$c['current_role'],'target_role'=>$c['target_role'],'target_location'=>$c['target_location'],'stage'=>$c['stage'],'status'=>$c['status'],'candidate_review_note'=>$c['candidate_review_note'],'candidate_reviewed_at'=>$c['candidate_reviewed_at'],'updated_at'=>$c['updated_at'],'visual_analysis'=>$visual,'plan_recommendations'=>$plans,'plan_display_enabled'=>$showPlans,'company_match_display_enabled'=>$showCompanies];return $p;
}
