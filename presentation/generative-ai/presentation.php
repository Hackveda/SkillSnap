<?php
/** Generative AI Intern: structured AI analysis, vision review and ATS generation.
 * Presentation extract only. Source of truth: /index.php
 */

function openai_output_text($response){
  if(isset($response['output_text']) && is_string($response['output_text'])) return trim($response['output_text']);
  $parts=[];
  foreach(($response['output']??[]) as $item){
    foreach(($item['content']??[]) as $content){
      if(isset($content['text']) && is_string($content['text'])) $parts[]=$content['text'];
    }
  }
  return trim(implode("\n",$parts));
}

function openai_structured_response($instructions,$input,$schema,$schemaName='resume_analysis'){
  global $OPENAI_API_KEY,$OPENAI_MODEL,$OPENAI_TIMEOUT;
  if(!openai_enabled()) throw new RuntimeException('OPENAI_API_KEY is not configured.');
  if(!function_exists('curl_init')) throw new RuntimeException('PHP cURL is required for OpenAI analysis.');
  $body=[
    'model'=>$OPENAI_MODEL,
    'store'=>false,
    'instructions'=>$instructions,
    'input'=>$input,
    'max_output_tokens'=>6000,
    'text'=>['format'=>[
      'type'=>'json_schema','name'=>$schemaName,'strict'=>true,'schema'=>$schema
    ]]
  ];
  $ch=curl_init('https://api.openai.com/v1/responses');
  curl_setopt_array($ch,[
    CURLOPT_POST=>true,
    CURLOPT_RETURNTRANSFER=>true,
    CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$OPENAI_API_KEY,'Content-Type: application/json'],
    CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),
    CURLOPT_CONNECTTIMEOUT=>12,
    CURLOPT_TIMEOUT=>$OPENAI_TIMEOUT,
    CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1,
    CURLOPT_USERAGENT=>'SkillSnap/1.0'
  ]);
  $raw=curl_exec($ch); $errno=curl_errno($ch); $err=curl_error($ch); $status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE); curl_close($ch);
  if($raw===false || $errno) throw new RuntimeException('OpenAI connection failed: '.$err);
  $response=json_decode($raw,true);
  if(!is_array($response)) throw new RuntimeException('OpenAI returned an unreadable response.');
  if($status<200 || $status>=300){
    $message=$response['error']['message']??('HTTP '.$status);
    throw new RuntimeException('OpenAI analysis failed: '.$message);
  }
  $text=openai_output_text($response);
  $json=json_decode($text,true);
  if(!is_array($json)) throw new RuntimeException('OpenAI structured analysis could not be decoded.');
  return ['data'=>$json,'response_id'=>$response['id']??'','model'=>$response['model']??$OPENAI_MODEL];
}

function requirement_analysis_schema(){
  return ['type'=>'object','additionalProperties'=>false,'properties'=>[
    'results'=>['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'properties'=>[
      'id'=>['type'=>'integer'],
      'status'=>['type'=>'string','enum'=>['existing','partial','missing']],
      'confidence'=>['type'=>'number','minimum'=>0,'maximum'=>1],
      'evidence'=>['type'=>'string'],
      'reason'=>['type'=>'string']
    ],'required'=>['id','status','confidence','evidence','reason']]]
  ],'required'=>['results']];
}

function analyze_requirements_openai($resumeText,$role,$requirements){
  global $OPENAI_REQUIREMENT_CHUNK;
  if(!openai_enabled() || !$requirements) return ['results'=>[],'model'=>'','errors'=>[]];
  $all=[];$errors=[];$model='';
  foreach(array_chunk($requirements,$OPENAI_REQUIREMENT_CHUNK) as $chunkNo=>$chunk){
    $compact=[];
    foreach($chunk as $r)$compact[]=['id'=>(int)$r['id'],'group'=>$r['group'],'term'=>$r['term'],'description'=>$r['description']];
    $instructions='You are a strict hiring evidence evaluator. Compare each target-role requirement with the resume only. existing means direct credible evidence that the candidate has demonstrated the requirement. partial means related, adjacent, basic, academic, limited, or incomplete evidence. missing means no credible resume evidence. Do not mark a requirement existing merely because its words appear in a job objective, target role, skills wish list, course syllabus, or generic summary. For experience and projects, require concrete activity or responsibility. For certifications, require a named completed certification. Evidence must quote or closely reproduce a short resume excerpt; use an empty string when missing. Return exactly one result for every supplied id.';
    $input="Target role: {$role}\n\nRESUME:\n".truncate_utf8($resumeText,18000)."\n\nREQUIREMENTS JSON:\n".json_encode($compact,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
    try{
      $r=openai_structured_response($instructions,$input,requirement_analysis_schema(),'requirement_evidence');
      $model=$r['model']; foreach(($r['data']['results']??[]) as $x)$all[(int)$x['id']]=$x;
    }catch(Throwable $e){ $errors[]='Chunk '.($chunkNo+1).': '.$e->getMessage(); }
  }
  return ['results'=>$all,'model'=>$model,'errors'=>$errors];
}

function visual_analysis_schema(){
  $cause=['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string'],'detail'=>['type'=>'string'],'score'=>['type'=>'number','minimum'=>0,'maximum'=>100],'page'=>['type'=>'integer','minimum'=>0],'evidence'=>['type'=>'string']], 'required'=>['title','detail','score','page','evidence']];
  return ['type'=>'object','additionalProperties'=>false,'properties'=>[
    'summary'=>['type'=>'string'],'overall_visual_score'=>['type'=>'number','minimum'=>0,'maximum'=>100],'ats_readability_score'=>['type'=>'number','minimum'=>0,'maximum'=>100],'six_second_clarity_score'=>['type'=>'number','minimum'=>0,'maximum'=>100],'hierarchy_score'=>['type'=>'number','minimum'=>0,'maximum'=>100],'evidence_visibility_score'=>['type'=>'number','minimum'=>0,'maximum'=>100],'density_score'=>['type'=>'number','minimum'=>0,'maximum'=>100],
    'persona_reviews'=>['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'properties'=>['persona'=>['type'=>'string'],'company_lens'=>['type'=>'string'],'perception'=>['type'=>'string'],'accept_probability'=>['type'=>'number','minimum'=>0,'maximum'=>100],'reject_probability'=>['type'=>'number','minimum'=>0,'maximum'=>100],'accept_points'=>['type'=>'array','items'=>['type'=>'string']],'reject_points'=>['type'=>'array','items'=>['type'=>'string']]],'required'=>['persona','company_lens','perception','accept_probability','reject_probability','accept_points','reject_points']]],
    'acceptance_causes'=>['type'=>'array','items'=>$cause],'rejection_causes'=>['type'=>'array','items'=>$cause],'layout_gaps'=>['type'=>'array','items'=>$cause],
    'priority_fixes'=>['type'=>'array','items'=>['type'=>'object','additionalProperties'=>false,'properties'=>['title'=>['type'=>'string'],'action'=>['type'=>'string'],'impact'=>['type'=>'number','minimum'=>0,'maximum'=>100],'effort'=>['type'=>'number','minimum'=>1,'maximum'=>5]],'required'=>['title','action','impact','effort']]]
  ],'required'=>['summary','overall_visual_score','ats_readability_score','six_second_clarity_score','hierarchy_score','evidence_visibility_score','density_score','persona_reviews','acceptance_causes','rejection_causes','layout_gaps','priority_fixes']];
}

function openai_visual_response($instructions,$text,$imageFiles,$schema){
  global $OPENAI_API_KEY,$OPENAI_MODEL,$OPENAI_TIMEOUT;if(!openai_enabled())throw new RuntimeException('Resume review is unavailable.');
  $content=[['type'=>'input_text','text'=>$text]];foreach($imageFiles as $f){$mime=mime_content_type($f)?:'image/jpeg';$content[]=['type'=>'input_image','image_url'=>'data:'.$mime.';base64,'.base64_encode(file_get_contents($f)),'detail'=>'high'];}
  $body=['model'=>$OPENAI_MODEL,'store'=>false,'instructions'=>$instructions,'input'=>[['role'=>'user','content'=>$content]],'max_output_tokens'=>7000,'text'=>['format'=>['type'=>'json_schema','name'=>'resume_visual_review','strict'=>true,'schema'=>$schema]]];
  $ch=curl_init('https://api.openai.com/v1/responses');curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$OPENAI_API_KEY,'Content-Type: application/json'],CURLOPT_POSTFIELDS=>json_encode($body,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_TIMEOUT=>max(90,$OPENAI_TIMEOUT),CURLOPT_HTTP_VERSION=>CURL_HTTP_VERSION_1_1]);
  $raw=curl_exec($ch);$err=curl_error($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);if($raw===false)throw new RuntimeException('Resume review could not be completed: '.$err);$res=json_decode($raw,true);if($status<200||$status>=300)throw new RuntimeException($res['error']['message']??'Resume review could not be completed.');$json=json_decode(openai_output_text($res),true);if(!is_array($json))throw new RuntimeException('Resume review returned an unreadable result.');return ['data'=>$json,'model'=>$res['model']??$OPENAI_MODEL];
}

function analyze_candidate_visual($cid){
  $st=db()->prepare('SELECT id,target_role,target_location,resume_stored_name,resume_text FROM skillsnap_candidates WHERE id=?');$st->execute([$cid]);$c=$st->fetch();if(!$c)throw new RuntimeException('Candidate not found.');if(trim((string)$c['resume_stored_name'])==='')throw new RuntimeException('Add a resume first.');
  $preview=resume_preview_images($c['resume_stored_name'],$c['resume_text']);
  try{$companies=role_company_context($c['target_role'],$c['target_location']);$companyNames=array_values(array_filter(array_map(fn($x)=>$x['Company']??'', $companies)));$instructions='You are a senior resume review panel. Inspect the rendered resume pages as a recruiter, ATS/resume screener, hiring manager, technical interviewer, and senior business leader. Judge what is visible, where it is placed, reading order, hierarchy, density, whitespace, typography, section naming, evidence prominence, credibility, role positioning, and six-second scan clarity. Use only visible resume evidence and the supplied target-role context. Distinguish acceptance causes from rejection causes. Scores are decision-support estimates, not hiring guarantees. Page is 1-based; use 0 when not page-specific.';$text="Target role: {$c['target_role']}\nTarget location: {$c['target_location']}\nRepresentative employers recruiting for similar roles: ".implode(', ',$companyNames)."\n\nReview the rendered resume across these company and persona lenses. Provide precise, evidence-based findings and prioritized improvements.";$r=openai_visual_response($instructions,$text,$preview['files'],visual_analysis_schema());db()->prepare('UPDATE skillsnap_candidates SET visual_analysis_json=?,visual_analysis_model=?,visual_analysis_at=?,visual_analysis_error=?,updated_at=? WHERE id=?')->execute([json_encode($r['data'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$r['model'],now_sql(),'',now_sql(),$cid]);log_activity($cid,'visual_review_completed','model: '.$r['model']);refresh_candidate_plan_recommendations($cid);return candidate_payload($cid);
  }catch(Throwable $e){db()->prepare('UPDATE skillsnap_candidates SET visual_analysis_error=?,updated_at=? WHERE id=?')->execute([$e->getMessage(),now_sql(),$cid]);throw $e;}finally{foreach($preview['files'] as $f)@unlink($f);foreach(glob($preview['dir'].'/*')?:[] as $f)@unlink($f);@rmdir($preview['dir']);}
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
  if(class_exists('\Mpdf\Mpdf')){$tmp=__DIR__.'/tmp/mpdf';if(!is_dir($tmp))@mkdir($tmp,0755,true);$m=new \Mpdf\Mpdf(['mode'=>'utf-8','format'=>'A4','margin_left'=>10,'margin_right'=>10,'margin_top'=>10,'margin_bottom'=>10,'tempDir'=>$tmp]);$m->WriteHTML($html);clean_buffers();$m->Output('ATS_Resume_'.$safe.'.pdf',\Mpdf\Output\Destination::DOWNLOAD);exit;}
  clean_buffers();header('Content-Type:text/html; charset=utf-8');header('Content-Disposition:attachment; filename="ATS_Resume_'.$safe.'.html"');echo $html;exit;
}
