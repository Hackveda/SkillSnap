<?php
/** Web Developer Intern: authentication, payload boundaries and public sharing.
 * Presentation extract only. Source of truth: /index.php
 */

function is_https_request(){ return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'); }

function start_admin_session(){
  global $SESSION_NAME;
  if(session_status()===PHP_SESSION_ACTIVE) return;
  session_name($SESSION_NAME);
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>is_https_request(),'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}

function admin_password_valid($password){
  global $ADMIN_PASSWORD_HASH,$ADMIN_PASSWORD;
  if($ADMIN_PASSWORD_HASH!=='') return password_verify((string)$password,$ADMIN_PASSWORD_HASH);
  return $ADMIN_PASSWORD!=='' && hash_equals($ADMIN_PASSWORD,(string)$password);
}

function admin_logged_in(){ start_admin_session(); return !empty($_SESSION['skillsnap_admin']); }

function public_request_allowed(){
  $action=trim((string)($_GET['action']??''));
  if(trim((string)($_GET['share']??''))!=='') return true;
  return in_array($action,['public_requirement_update','candidate_review_submit','public_company_matches','public_ats_resume_pdf'],true);
}

function enforce_admin_access(){
  if(public_request_allowed()) return;
  $action=trim((string)($_GET['action']??''));
  if($action==='admin_login' && $_SERVER['REQUEST_METHOD']==='POST'){
    start_admin_session();
    if(!admin_password_ready()) render_login_page('Set CANDIDATE_ADMIN_PASSWORD_HASH or CANDIDATE_ADMIN_PASSWORD on the server.');
    if(admin_password_valid($_POST['password']??'')){ session_regenerate_id(true); $_SESSION['skillsnap_admin']=1; header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit; }
    render_login_page('The password was not accepted.');
  }
  if($action==='admin_logout'){ start_admin_session(); $_SESSION=[]; if(ini_get('session.use_cookies')){ $p=session_get_cookie_params(); setcookie(session_name(),'',time()-42000,$p['path'],$p['domain']??'',(bool)$p['secure'],(bool)$p['httponly']); } session_destroy(); header('Location: '.strtok($_SERVER['REQUEST_URI'],'?')); exit; }
  if(admin_logged_in()) return;
  if($action!=='') json_out(['error'=>'authentication_required','message'=>'Please sign in again.'],401);
  render_login_page(admin_password_ready()?'':'Set CANDIDATE_ADMIN_PASSWORD_HASH or CANDIDATE_ADMIN_PASSWORD on the server.');
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

function public_candidate_payload($token){
  $st=db()->prepare('SELECT id,full_name,current_role,target_role,target_location,stage,status,share_enabled,candidate_review_note,candidate_reviewed_at,updated_at FROM skillsnap_candidates WHERE share_token=? LIMIT 1');$st->execute([$token]);$c=$st->fetch();
  if(!$c||!(int)$c['share_enabled'])return null;$p=candidate_payload((int)$c['id']);if(!$p)return null;
  $visual=$p['candidate']['visual_analysis']??[];$plans=$p['candidate']['plan_recommendations']??[];$showPlans=(int)($p['candidate']['plan_display_enabled']??0);$showCompanies=(int)($p['candidate']['company_match_display_enabled']??0);$p['candidate']=['id'=>$c['id'],'full_name'=>$c['full_name'],'current_role'=>$c['current_role'],'target_role'=>$c['target_role'],'target_location'=>$c['target_location'],'stage'=>$c['stage'],'status'=>$c['status'],'candidate_review_note'=>$c['candidate_review_note'],'candidate_reviewed_at'=>$c['candidate_reviewed_at'],'updated_at'=>$c['updated_at'],'visual_analysis'=>$visual,'plan_recommendations'=>$plans,'plan_display_enabled'=>$showPlans,'company_match_display_enabled'=>$showCompanies];return $p;
}
