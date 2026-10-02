<?php
$action=getv('action');
if($action!==''){ header('X-Content-Type-Options: nosniff'); }
try{
if($action==='candidate_list'){
  $q=getv('q'); $stage=getv('stage'); $sql='SELECT id,full_name,email,phone,current_role,target_role,target_location,stage,status,resume_original_name,analysis_version,created_at,updated_at FROM skillsnap_candidates WHERE 1'; $par=[];
  if($q!==''){ $sql.=' AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ? OR target_role LIKE ? OR current_role LIKE ?)'; $x='%'.$q.'%'; $par=[$x,$x,$x,$x,$x]; }
  if($stage!==''){ $sql.=' AND stage=?'; $par[]=$stage; }
  $sql.=' ORDER BY updated_at DESC LIMIT 500'; $st=db()->prepare($sql); $st->execute($par); json_out(['ok'=>1,'rows'=>$st->fetchAll()]);
}
if($action==='candidate_get'){ $p=candidate_payload(intv(getv('id'))); if(!$p) json_out(['error'=>'Candidate not found'],404); json_out(['ok'=>1]+$p); }
if($action==='candidate_save'){
  $id=intv(post('id')); $fields=['full_name','email','phone','current_role','target_role','target_location','current_ctc','expected_ctc','notice_period','stage','status','notes']; $v=[]; foreach($fields as $f)$v[$f]=post($f);
  if($v['full_name']==='') json_out(['error'=>'Candidate name is required.'],422);
  $pdo=db();
  if($id){ $set=implode(',',array_map(fn($f)=>"`$f`=?",$fields)); $args=array_values($v); $args[]=now_sql(); $args[]=$id; $pdo->prepare("UPDATE skillsnap_candidates SET $set,updated_at=? WHERE id=?")->execute($args); log_activity($id,'candidate_updated'); }
  else { $cols=implode(',',array_map(fn($f)=>"`$f`",$fields)); $marks=implode(',',array_fill(0,count($fields),'?')); $args=array_values($v); $args[]=now_sql(); $args[]=now_sql(); $pdo->prepare("INSERT INTO skillsnap_candidates($cols,created_at,updated_at) VALUES($marks,?,?)")->execute($args); $id=(int)$pdo->lastInsertId(); log_activity($id,'candidate_created'); }
  json_out(['ok'=>1,'id'=>$id,'payload'=>candidate_payload($id)]);
}
if($action==='candidate_delete'){
  $id=intv(post('id')); $st=db()->prepare('SELECT resume_stored_name FROM skillsnap_candidates WHERE id=?'); $st->execute([$id]); $r=$st->fetch(); if(!$r) json_out(['error'=>'Candidate not found'],404);
  global $UPLOAD_DIR; if($r['resume_stored_name']){ $f=$UPLOAD_DIR.'/'.$r['resume_stored_name']; if(is_file($f))@unlink($f); }
  db()->prepare('DELETE FROM skillsnap_candidates WHERE id=?')->execute([$id]); json_out(['ok'=>1]);
}
if($action==='resume_upload'){
  $id=intv(post('candidate_id')); if(!$id) json_out(['error'=>'Save the candidate before uploading a resume.'],422);
  if(!isset($_FILES['resume'])||$_FILES['resume']['error']!==UPLOAD_ERR_OK) json_out(['error'=>'Resume upload failed.'],422);
  global $UPLOAD_DIR,$MAX_UPLOAD; if($_FILES['resume']['size']>$MAX_UPLOAD) json_out(['error'=>'Resume exceeds the 10 MB limit.'],422);
  $orig=basename($_FILES['resume']['name']); $ext=strtolower(pathinfo($orig,PATHINFO_EXTENSION)); if(!in_array($ext,['pdf','docx','txt'],true)) json_out(['error'=>'Only PDF, DOCX and TXT files are supported.'],422);
  if(!is_dir($UPLOAD_DIR)&&!mkdir($UPLOAD_DIR,0750,true)) throw new RuntimeException('Unable to create candidate upload directory.');
  $stored='candidate_'.$id.'_'.bin2hex(random_bytes(8)).'.'.$ext; $dest=$UPLOAD_DIR.'/'.$stored; if(!move_uploaded_file($_FILES['resume']['tmp_name'],$dest)) throw new RuntimeException('Unable to store uploaded resume.');
  try{ $text=extract_resume_text($dest,$ext); }catch(Throwable $e){ @unlink($dest); throw $e; }
  $st=db()->prepare('SELECT resume_stored_name,target_role,target_location FROM skillsnap_candidates WHERE id=?'); $st->execute([$id]); $old=$st->fetch(); if(!$old){@unlink($dest);json_out(['error'=>'Candidate not found'],404);}
  if($old['resume_stored_name']){ $of=$UPLOAD_DIR.'/'.$old['resume_stored_name']; if(is_file($of))@unlink($of); }
  $mime=mime_content_type($dest)?:'application/octet-stream';
  /* Save the extracted text first. Do not make a separate inventory API call here.
     The requirement analysis below already evaluates skills, experience, projects and
     certifications, avoiding duplicate OpenAI calls and shared-hosting timeouts. */
  db()->prepare('UPDATE skillsnap_candidates SET resume_original_name=?,resume_stored_name=?,resume_mime=?,resume_text=?,resume_analysis_json=?,resume_analysis_provider=?,resume_analysis_model=?,resume_analysis_at=?,resume_analysis_error=?,updated_at=? WHERE id=?')
    ->execute([$orig,$stored,$mime,$text,'{}',openai_enabled()?'openai_pending':'keyword_fallback','',null,'',now_sql(),$id]);
  log_activity($id,'resume_uploaded',$orig);

  $payload=trim((string)$old['target_role'])!==''?analyze_candidate($id,null,null,false):candidate_payload($id);
  $aiUsed=false;$aiError='';$aiModel='';$inventory=['skills'=>[],'experience'=>[],'projects'=>[],'certifications'=>[]];
  if(trim((string)$old['target_role'])!==''){
    $stAi=db()->prepare("SELECT group_name,term,ai_status,ai_confidence,ai_evidence,ai_reason,ai_model FROM skillsnap_candidate_requirements WHERE candidate_id=? AND ai_status IS NOT NULL ORDER BY group_name,weight DESC");
    $stAi->execute([$id]);
    while($ar=$stAi->fetch()){
      $aiUsed=true;$aiModel=$aiModel?:((string)$ar['ai_model']);
      if(isset($inventory[$ar['group_name']]))$inventory[$ar['group_name']][]=[
        'name'=>$ar['term'],'status'=>$ar['ai_status'],'confidence'=>(float)$ar['ai_confidence'],
        'evidence'=>(string)$ar['ai_evidence'],'reason'=>(string)$ar['ai_reason']
      ];
    }
    $snap=decode_json($payload['candidate']['requirements_json']??'');
    $errs=$snap['openai']['errors']??[];if(is_array($errs)&&$errs)$aiError=implode(' | ',$errs);
  }
  $provider=$aiUsed?'openai':(openai_enabled()?'keyword_fallback_openai_available':'keyword_fallback');
  db()->prepare('UPDATE skillsnap_candidates SET resume_analysis_json=?,resume_analysis_provider=?,resume_analysis_model=?,resume_analysis_at=?,resume_analysis_error=?,updated_at=? WHERE id=?')
    ->execute([json_encode($inventory,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),$provider,$aiModel,$aiUsed?now_sql():null,$aiError,now_sql(),$id]);
  $payload=candidate_payload($id);enqueue_company_match_job($id,[],10,true);
  json_out(['ok'=>1,'text_length'=>mb_strlen($text),'openai_used'=>$aiUsed,'openai_error'=>$aiError,'payload'=>$payload]);
}
if($action==='analyze'){ $id=intv(post('candidate_id')); $payload=analyze_candidate($id,post('target_role'),post('target_location'),false);enqueue_company_match_job($id,[],10,true);json_out(['ok'=>1,'payload'=>$payload]); }
if($action==='analyze_openai_group'){ $id=intv(post('candidate_id')); $result=analyze_candidate_openai_group($id,post('group'));enqueue_company_match_job($id,[],10,true);json_out(['ok'=>1]+$result); }
if($action==='analyze_visual'){ $id=intv(post('candidate_id')); json_out(['ok'=>1,'payload'=>analyze_candidate_visual($id)]); }
if($action==='requirement_update'){
  $id=intv(post('id')); $status=post('review_status'); if(!in_array($status,['','existing','partial','missing','not_required'],true)) json_out(['error'=>'Invalid status'],422);
  $st=db()->prepare('SELECT candidate_id FROM skillsnap_candidate_requirements WHERE id=?'); $st->execute([$id]); $r=$st->fetch(); if(!$r)json_out(['error'=>'Requirement not found'],404);
  db()->prepare('UPDATE skillsnap_candidate_requirements SET review_status=?,evidence=?,reviewer_note=?,reviewed_at=?,updated_at=? WHERE id=?')->execute([$status===''?null:$status,post('evidence'),post('reviewer_note'),now_sql(),now_sql(),$id]);
  log_activity($r['candidate_id'],'requirement_reviewed','Requirement ID '.$id.' marked '.($status?:'automatic'));enqueue_company_match_job((int)$r['candidate_id'],[],10,true); json_out(['ok'=>1,'payload'=>candidate_payload($r['candidate_id'])]);
}
if($action==='candidate_field_update'){
  $id=intv(post('id')); $field=post('field'); $allowed=['stage','status','notes','target_role','target_location','current_ctc','expected_ctc','notice_period']; if(!in_array($field,$allowed,true))json_out(['error'=>'Field not allowed'],422);
  db()->prepare("UPDATE skillsnap_candidates SET `$field`=?,updated_at=? WHERE id=?")->execute([post('value'),now_sql(),$id]); log_activity($id,'field_updated',$field); json_out(['ok'=>1]);
}
if($action==='role_suggestions'){
  $q=getv('q'); $sql="SELECT Title,COUNT(*) cnt FROM skillsnap_job_details WHERE Title<>''"; $par=[]; if($q!==''){ $sql.=' AND Title LIKE ?'; $par[]='%'.$q.'%'; } $sql.=' GROUP BY Title ORDER BY cnt DESC LIMIT 30'; $st=db()->prepare($sql);$st->execute($par);json_out(['ok'=>1,'rows'=>$st->fetchAll()]);
}

if($action==='plan_visibility_update'){
  $id=intv(post('candidate_id'));$enabled=intv(post('enabled'))?1:0;
  $st=db()->prepare('SELECT id FROM skillsnap_candidates WHERE id=?');$st->execute([$id]);if(!$st->fetch())json_out(['error'=>'Candidate not found.'],404);
  db()->prepare('UPDATE skillsnap_candidates SET plan_display_enabled=?,updated_at=? WHERE id=?')->execute([$enabled,now_sql(),$id]);
  log_activity($id,$enabled?'plan_display_enabled':'plan_display_disabled');
  json_out(['ok'=>1,'payload'=>candidate_payload($id)]);
}
if($action==='plan_recommendations_refresh'){
  $id=intv(post('candidate_id'));$rec=refresh_candidate_plan_recommendations($id);
  json_out(['ok'=>1,'recommendations'=>$rec,'payload'=>candidate_payload($id)]);
}


if($action==='company_match_visibility_update'){$id=intv(post('candidate_id'));$enabled=intv(post('enabled'))?1:0;if($id<=0)json_out(['error'=>'Candidate is required.'],422);$st=db()->prepare('SELECT id FROM skillsnap_candidates WHERE id=? LIMIT 1');$st->execute([$id]);if(!$st->fetch())json_out(['error'=>'Candidate not found.'],404);db()->prepare('UPDATE skillsnap_candidates SET company_match_display_enabled=?,updated_at=? WHERE id=?')->execute([$enabled,now_sql(),$id]);log_activity($id,$enabled?'company_match_display_enabled':'company_match_display_disabled');json_out(['ok'=>1,'payload'=>candidate_payload($id)]);}
if($action==='company_matches'){$id=intv(getv('candidate_id'));json_out(['ok'=>1]+candidate_company_matches_async($id,['start_date'=>getv('start_date'),'end_date'=>getv('end_date'),'company'=>getv('company'),'location'=>getv('location'),'q'=>getv('q')],intv(getv('refresh'))===1,'admin',intv(getv('run_id'))));}
if($action==='public_company_matches'){$token=getv('token');$st=db()->prepare('SELECT id,company_match_display_enabled FROM skillsnap_candidates WHERE share_token=? AND share_enabled=1 LIMIT 1');$st->execute([$token]);$x=$st->fetch();if(!$x||(int)$x['company_match_display_enabled']!==1)json_out(['error'=>'Company matching is unavailable.'],404);json_out(['ok'=>1]+candidate_company_matches_async((int)$x['id'],['start_date'=>getv('start_date'),'end_date'=>getv('end_date'),'company'=>getv('company'),'location'=>getv('location'),'q'=>getv('q')],intv(getv('refresh'))===1,'shared',intv(getv('run_id'))));}
if($action==='ats_resume_pdf'){$cid=intv(getv('candidate_id'));$jobId=intv(getv('job_id'));output_ats_resume_pdf($cid,$jobId);}
if($action==='public_ats_resume_pdf'){$token=getv('token');$jobId=intv(getv('job_id'));$st=db()->prepare('SELECT id,company_match_display_enabled FROM skillsnap_candidates WHERE share_token=? AND share_enabled=1 LIMIT 1');$st->execute([$token]);$x=$st->fetch();if(!$x||(int)$x['company_match_display_enabled']!==1)throw new RuntimeException('Resume download is unavailable.');output_ats_resume_pdf((int)$x['id'],$jobId);}

if($action==='share_create'){
  $id=intv(post('candidate_id'));if(!$id)json_out(['error'=>'Candidate is required.'],422);$st=db()->prepare('SELECT share_token FROM skillsnap_candidates WHERE id=?');$st->execute([$id]);$r=$st->fetch();if(!$r)json_out(['error'=>'Candidate not found.'],404);
  $token=trim((string)$r['share_token']);if($token==='')$token=bin2hex(random_bytes(24));db()->prepare('UPDATE skillsnap_candidates SET share_token=?,share_enabled=1,share_created_at=COALESCE(share_created_at,?),updated_at=? WHERE id=?')->execute([$token,now_sql(),now_sql(),$id]);log_activity($id,'profile_share_enabled');
  $base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').strtok($_SERVER['REQUEST_URI'],'?');json_out(['ok'=>1,'share_url'=>$base.'?share='.rawurlencode($token),'payload'=>candidate_payload($id)]);
}
if($action==='share_disable'){$id=intv(post('candidate_id'));db()->prepare('UPDATE skillsnap_candidates SET share_enabled=0,updated_at=? WHERE id=?')->execute([now_sql(),$id]);log_activity($id,'profile_share_disabled');json_out(['ok'=>1,'payload'=>candidate_payload($id)]);}
if($action==='share_status'){$id=intv(getv('candidate_id'));$st=db()->prepare('SELECT share_token,share_enabled FROM skillsnap_candidates WHERE id=?');$st->execute([$id]);$r=$st->fetch();if(!$r)json_out(['error'=>'Candidate not found.'],404);$url='';if($r['share_token']){$base=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').strtok($_SERVER['REQUEST_URI'],'?');$url=$base.'?share='.rawurlencode($r['share_token']);}json_out(['ok'=>1,'enabled'=>(int)$r['share_enabled'],'share_url'=>$url]);}
if($action==='candidate_review_submit'){$token=post('token');$note=post('review_note');$st=db()->prepare('SELECT id FROM skillsnap_candidates WHERE share_token=? AND share_enabled=1');$st->execute([$token]);$r=$st->fetch();if(!$r)json_out(['error'=>'This shared profile is unavailable.'],404);db()->prepare('UPDATE skillsnap_candidates SET candidate_review_note=?,candidate_reviewed_at=?,updated_at=? WHERE id=?')->execute([$note,now_sql(),now_sql(),$r['id']]);log_activity($r['id'],'candidate_review_submitted');json_out(['ok'=>1,'message'=>'Your review was saved.']);}


if($action==='public_requirement_update'){
  $token=post('token');$id=intv(post('id'));$status=post('review_status');
  if(!in_array($status,['','existing','partial','missing','not_required'],true))json_out(['error'=>'Invalid status.'],422);
  $st=db()->prepare('SELECT cr.candidate_id FROM skillsnap_candidate_requirements cr JOIN skillsnap_candidates c ON c.id=cr.candidate_id WHERE cr.id=? AND c.share_token=? AND c.share_enabled=1');
  $st->execute([$id,$token]);$r=$st->fetch();
  if(!$r)json_out(['error'=>'This requirement is unavailable for the shared profile.'],404);
  db()->prepare('UPDATE skillsnap_candidate_requirements SET review_status=?,evidence=?,reviewer_note=?,reviewed_at=?,updated_at=? WHERE id=?')
    ->execute([$status===''?null:$status,post('evidence'),post('reviewer_note'),now_sql(),now_sql(),$id]);
  log_activity($r['candidate_id'],'candidate_requirement_updated','Shared profile requirement '.$id);
  json_out(['ok'=>1,'payload'=>public_candidate_payload($token)]);
}

if($action==='download_resume'){
  $id=intv(getv('id')); $st=db()->prepare('SELECT resume_original_name,resume_stored_name,resume_mime FROM skillsnap_candidates WHERE id=?');$st->execute([$id]);$r=$st->fetch(); if(!$r||!$r['resume_stored_name']){http_response_code(404);exit('Resume not found');}
  $path=$UPLOAD_DIR.'/'.$r['resume_stored_name']; if(!is_file($path)){http_response_code(404);exit('Resume not found');}
  clean_buffers(); header('Content-Type: '.($r['resume_mime']?:'application/octet-stream')); header('Content-Disposition: attachment; filename="'.str_replace('"','',$r['resume_original_name']).'"'); header('Content-Length: '.filesize($path)); readfile($path); exit;
}
}catch(Throwable $e){ json_out(['error'=>'server_error','message'=>$e->getMessage()],500); }
