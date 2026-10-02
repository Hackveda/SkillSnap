<?php
function decode_json($txt){ $j=json_decode((string)$txt,true); return is_array($j)?$j:[]; }
function group_aliases($g){
  return [
    'skills'=>['skills','Skills','skill','Skill'],
    'experience'=>['experience','Experience','experiences','Experiences'],
    'projects'=>['projects','Projects','project','Project'],
    'certificates'=>['certificates','Certificates','certificate','Certificate','certifications','Certifications']
  ][$g] ?? [$g];
}
function extract_weighted_group($json,$group){
  $j=is_array($json)?$json:decode_json($json); $items=null;
  foreach(group_aliases($group) as $k){ if(array_key_exists($k,$j)){ $items=$j[$k]; break; } }
  if($items===null || !is_array($items)) return [];
  $out=[];
  foreach($items as $it){
    if(is_string($it)){ $term=norm_space($it); $w=1; }
    elseif(is_array($it)){ $term=norm_space($it['term']??$it['name']??$it['title']??''); $w=is_numeric($it['weight']??null)?(float)$it['weight']:1; }
    else continue;
    if($term!=='') $out[]=['term'=>$term,'weight'=>$w];
  }
  return $out;
}
function extract_desc_group($json,$group){
  $j=is_array($json)?$json:decode_json($json); $items=null;
  foreach(group_aliases($group) as $k){ if(array_key_exists($k,$j)){ $items=$j[$k]; break; } }
  if(!is_array($items)) return [];
  $out=[];
  foreach($items as $it){
    if(!is_array($it)) continue;
    $term=norm_space($it['term']??''); $desc=trim((string)($it['desc']??''));
    if($term!=='') $out[norm_key($term)]=['term'=>$term,'desc'=>$desc];
  }
  return $out;
}
function role_requirements($role,$location='',$limitRows=5000,$limitEach=100){
  $role=trim($role); if($role==='') return ['groups'=>['skills'=>[],'experience'=>[],'projects'=>[],'certificates'=>[]],'jobs_scanned'=>0];
  $where=['analysed=1','(`Title` LIKE :role OR `parsed_json` LIKE :role2)'];
  $par=[':role'=>'%'.$role.'%',':role2'=>'%'.$role.'%'];
  if($location!==''){ $where[]='`Location` LIKE :loc'; $par[':loc']='%'.$location.'%'; }
  $sql='SELECT `Title`,`Company`,`Location`,`parsed_json`,`skill_desc` FROM skillsnap_job_details WHERE '.implode(' AND ',$where).' ORDER BY `ID` DESC LIMIT '.max(1,min(20000,$limitRows));
  $st=db()->prepare($sql); $st->execute($par);
  $agg=['skills'=>[],'experience'=>[],'projects'=>[],'certificates'=>[]]; $scanned=0;
  while($r=$st->fetch()){
    $scanned++; $pj=decode_json($r['parsed_json']??''); $sd=decode_json($r['skill_desc']??'');
    foreach(array_keys($agg) as $g){
      $descMap=extract_desc_group($sd,$g); $seen=[];
      foreach(extract_weighted_group($pj,$g) as $it){
        $key=norm_key($it['term']); if($key===''||isset($seen[$key])) continue; $seen[$key]=1;
        if(!isset($agg[$g][$key])) $agg[$g][$key]=['term'=>$it['term'],'description'=>'','weight'=>0.0,'mentions'=>0];
        $agg[$g][$key]['weight']+=(float)$it['weight']; $agg[$g][$key]['mentions']++;
        if($agg[$g][$key]['description']==='' && isset($descMap[$key])) $agg[$g][$key]['description']=$descMap[$key]['desc'];
      }
    }
  }
  $groups=[];
  foreach($agg as $g=>$map){
    $rows=array_values($map);
    usort($rows,function($a,$b){ $x=$b['weight']<=>$a['weight']; return $x?:($b['mentions']<=>$a['mentions']); });
    $groups[$g]=array_slice($rows,0,$limitEach);
  }
  return ['groups'=>$groups,'jobs_scanned'=>$scanned];
}
function xml_text($xml){
  $xml=preg_replace('/<w:tab\/>/','\t',$xml); $xml=preg_replace('/<w:br\/>/','\n',$xml); $xml=preg_replace('/<\/w:p>/','\n',$xml);
  return norm_space(html_entity_decode(strip_tags($xml),ENT_QUOTES|ENT_XML1,'UTF-8'));
}
function extract_resume_text($path,$ext){
  $ext=strtolower($ext);
  if($ext==='txt') return trim((string)file_get_contents($path));
  if($ext==='docx'){
    if(!class_exists('ZipArchive')) throw new RuntimeException('PHP ZipArchive is required for DOCX extraction.');
    $z=new ZipArchive(); if($z->open($path)!==true) throw new RuntimeException('Unable to open DOCX file.');
    $xml=$z->getFromName('word/document.xml'); $z->close(); if($xml===false) throw new RuntimeException('DOCX document.xml was not found.');
    return xml_text($xml);
  }
  if($ext==='pdf'){
    /* First preference: native pdftotext, when the host provides it. */
    $pdftotext = trim((string)@shell_exec('command -v pdftotext 2>/dev/null'));
    if($pdftotext !== ''){
      $cmd=escapeshellarg($pdftotext).' -layout '.escapeshellarg($path).' - 2>/dev/null';
      $out=@shell_exec($cmd);
      if(is_string($out) && trim($out)!=='') return trim($out);
    }

    /* Shared-hosting fallback: Composer package smalot/pdfparser. */
    if(class_exists('\\Smalot\\PdfParser\\Parser')){
      try{
        $parser = new \Smalot\PdfParser\Parser();
        $pdf = $parser->parseFile($path);
        $out = trim((string)$pdf->getText());
        if($out !== '') return $out;
      }catch(Throwable $e){
        throw new RuntimeException('The PDF was opened, but readable text could not be extracted: '.$e->getMessage());
      }
    }

    throw new RuntimeException(
      'Unable to extract PDF text. Install smalot/pdfparser in vendor/ or enable pdftotext. Scanned image-only PDFs require OCR.'
    );
  }
  throw new RuntimeException('Only PDF, DOCX and TXT resumes are supported.');
}
function keyword_variants($term){
  $t=norm_key($term); $v=[$t];
  $parts=preg_split('/[\(\)\[\],;\/|]+/u',$t);
  foreach($parts as $p){ $p=trim($p); if(mb_strlen($p)>=3) $v[]=$p; }
  $aliases=[
    'javascript'=>['javascript','js'],'typescript'=>['typescript','ts'],'node.js'=>['node.js','nodejs','node js'],
    'amazon web services'=>['amazon web services','aws'],'google cloud platform'=>['google cloud platform','gcp'],
    'microsoft azure'=>['microsoft azure','azure'],'continuous integration'=>['continuous integration','ci/cd','cicd'],
    'machine learning'=>['machine learning','ml'],'artificial intelligence'=>['artificial intelligence','ai'],
    'large language model'=>['large language model','large language models','llm','llms']
  ];
  foreach($aliases as $root=>$xs){ if(mb_strpos($t,$root)!==false||in_array($t,$xs,true)) $v=array_merge($v,$xs); }
  return array_values(array_unique(array_filter($v)));
}
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
function openai_enabled(){
  global $OPENAI_API_KEY;
  return $OPENAI_API_KEY!=='';
}
function truncate_utf8($text,$maxChars){
  $text=trim((string)$text);
  return mb_strlen($text,'UTF-8')>$maxChars ? mb_substr($text,0,$maxChars,'UTF-8') : $text;
}
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
function resume_inventory_schema(){
  $item=['type'=>'object','additionalProperties'=>false,'properties'=>[
    'name'=>['type'=>'string'],'evidence'=>['type'=>'string'],'confidence'=>['type'=>'number','minimum'=>0,'maximum'=>1]
  ],'required'=>['name','evidence','confidence']];
  return ['type'=>'object','additionalProperties'=>false,'properties'=>[
    'summary'=>['type'=>'string'],
    'total_experience_years'=>['type'=>['number','null']],
    'current_or_latest_role'=>['type'=>'string'],
    'skills'=>['type'=>'array','items'=>$item],
    'experience'=>['type'=>'array','items'=>$item],
    'projects'=>['type'=>'array','items'=>$item],
    'certifications'=>['type'=>'array','items'=>$item],
    'warnings'=>['type'=>'array','items'=>['type'=>'string']]
  ],'required'=>['summary','total_experience_years','current_or_latest_role','skills','experience','projects','certifications','warnings']];
}
function analyze_resume_inventory_openai($resumeText){
  $instructions='You are a strict resume evidence analyst. Extract only facts supported by the resume. Never infer a skill, project, experience, certification, employer, duration, result, or proficiency that is not explicitly evidenced. Evidence must be a short exact or near-exact resume excerpt. Keep duplicate concepts merged. Certifications must be completed credentials; courses or training should not be called certifications unless the resume says they are certified.';
  $input="Analyze this resume and return a factual inventory of skills, experience, projects and certifications.\n\nRESUME:\n".truncate_utf8($resumeText,60000);
  return openai_structured_response($instructions,$input,resume_inventory_schema(),'resume_inventory');
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


function role_company_context($role,$location=''){
  $sql="SELECT Company,COUNT(*) cnt FROM skillsnap_job_details WHERE analysed=1 AND Company<>'' AND (`Title` LIKE ? OR `parsed_json` LIKE ?)";$par=['%'.$role.'%','%'.$role.'%'];
  if(trim($location)!==''){ $sql.=" AND `Location` LIKE ?";$par[]='%'.$location.'%'; }
  $sql.=" GROUP BY Company ORDER BY cnt DESC LIMIT 8";$st=db()->prepare($sql);$st->execute($par);return $st->fetchAll();
}
function render_pdf_pages($pdfPath,$workDir,$maxPages=3){
  $files=[];$pdftoppm=trim((string)@shell_exec('command -v pdftoppm 2>/dev/null'));
  if($pdftoppm!==''){
    $prefix=$workDir.'/page';$cmd=escapeshellarg($pdftoppm).' -jpeg -r 120 -f 1 -l '.(int)$maxPages.' '.escapeshellarg($pdfPath).' '.escapeshellarg($prefix).' 2>/dev/null';@shell_exec($cmd);
    foreach(glob($prefix.'-*.jpg')?:[] as $f)$files[]=$f;
  }
  if(!$files && class_exists('Imagick')){
    try{$im=new Imagick();$im->setResolution(120,120);$im->readImage($pdfPath.'[0-'.max(0,$maxPages-1).']');$i=1;foreach($im as $page){$page->setImageFormat('jpeg');$page->setImageCompressionQuality(78);$out=$workDir.'/page-'.$i.'.jpg';$page->writeImage($out);$files[]=$out;$i++;} $im->clear();}catch(Throwable $e){}
  }
  return array_slice($files,0,$maxPages);
}
function render_text_preview($text,$workDir,$maxPages=3){
  if(!function_exists('imagecreatetruecolor')) return [];
  $lines=[];foreach(preg_split('/\R/u',(string)$text) as $line){foreach(explode("\n",wordwrap(trim($line),105,"\n",true)) as $x)$lines[]=$x;}
  $per=55;$files=[];for($p=0;$p<$maxPages;$p++){ $slice=array_slice($lines,$p*$per,$per);if(!$slice)break;$im=imagecreatetruecolor(1200,1600);$white=imagecolorallocate($im,255,255,255);$ink=imagecolorallocate($im,17,24,39);imagefill($im,0,0,$white);$y=45;foreach($slice as $line){imagestring($im,4,45,$y,$line,$ink);$y+=26;}$out=$workDir.'/text-'.($p+1).'.jpg';imagejpeg($im,$out,82);imagedestroy($im);$files[]=$out; }return $files;
}
function resume_preview_images($storedName,$resumeText){
  global $UPLOAD_DIR;
  $source=$UPLOAD_DIR.'/'.$storedName;if(!is_file($source))throw new RuntimeException('Resume file not found.');
  $work=sys_get_temp_dir().'/candidate_visual_'.bin2hex(random_bytes(6));if(!mkdir($work,0700,true))throw new RuntimeException('Unable to prepare resume preview.');
  $ext=strtolower(pathinfo($source,PATHINFO_EXTENSION));$files=[];$pdf=$source;
  if($ext==='docx'){$libre=trim((string)@shell_exec('command -v libreoffice 2>/dev/null'));if($libre!==''){@shell_exec(escapeshellarg($libre).' --headless --convert-to pdf --outdir '.escapeshellarg($work).' '.escapeshellarg($source).' 2>/dev/null');$cand=$work.'/'.pathinfo($source,PATHINFO_FILENAME).'.pdf';if(is_file($cand))$pdf=$cand;}}
  if($ext==='pdf' || ($ext==='docx' && $pdf!==$source))$files=render_pdf_pages($pdf,$work,3);
  if(!$files)$files=render_text_preview($resumeText,$work,3);
  if(!$files){@rmdir($work);throw new RuntimeException('A visual preview could not be created on this server. Enable pdftoppm, Imagick, LibreOffice, or GD.');}
  return ['dir'=>$work,'files'=>$files];
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

function analyze_candidate_openai_group($cid,$group){
  $allowed=['skills','experience','projects','certificates'];
  if(!in_array($group,$allowed,true)) throw new RuntimeException('Invalid analysis group.');
  if(!openai_enabled()) throw new RuntimeException('OPENAI_API_KEY is not configured.');
  $pdo=db();
  $st=$pdo->prepare('SELECT id,target_role,resume_text FROM skillsnap_candidates WHERE id=?');$st->execute([$cid]);$c=$st->fetch();
  if(!$c) throw new RuntimeException('Candidate not found.');
  if(trim((string)$c['target_role'])==='') throw new RuntimeException('Target role is required.');
  if(trim((string)$c['resume_text'])==='') throw new RuntimeException('Upload a readable resume first.');
  $st=$pdo->prepare('SELECT id,group_name,term,description FROM skillsnap_candidate_requirements WHERE candidate_id=? AND group_name=? ORDER BY weight DESC,mentions DESC,id LIMIT 40');
  $st->execute([$cid,$group]);$input=[];
  while($r=$st->fetch())$input[]=['id'=>(int)$r['id'],'group'=>$r['group_name'],'term'=>$r['term'],'description'=>(string)$r['description']];
  if(!$input) return ['payload'=>candidate_payload($cid),'group'=>$group,'openai_used'=>false,'message'=>'No requirements found for this category.'];
  $ai=analyze_requirements_openai((string)$c['resume_text'],(string)$c['target_role'],$input);
  if(!$ai['results']) throw new RuntimeException($ai['errors']?implode(' | ',$ai['errors']):'OpenAI returned no classifications.');
  $upd=$pdo->prepare('UPDATE skillsnap_candidate_requirements SET ai_status=?,ai_confidence=?,ai_reason=?,ai_evidence=?,ai_model=?,ai_analyzed_at=?,evidence=IF(review_status IS NULL AND ?<>\'\',?,evidence),updated_at=? WHERE id=? AND candidate_id=?');
  foreach($ai['results'] as $rid=>$x){
    $status=in_array($x['status']??'', ['existing','partial','missing'],true)?$x['status']:'missing';
    $confidence=max(0,min(1,(float)($x['confidence']??0)));$evidence=trim((string)($x['evidence']??''));$reason=trim((string)($x['reason']??''));
    $upd->execute([$status,$confidence,$reason,$evidence,$ai['model'],now_sql(),$evidence,$evidence,now_sql(),$rid,$cid]);
  }
  log_activity($cid,'openai_group_completed',$group.'; model: '.$ai['model']);
  refresh_candidate_plan_recommendations($cid);
  return ['payload'=>candidate_payload($cid),'group'=>$group,'openai_used'=>true,'model'=>$ai['model'],'classified'=>count($ai['results'])];
}

function analyze_candidate($cid,$role=null,$location=null,$useOpenAI=true){
  $pdo=db(); $st=$pdo->prepare('SELECT * FROM skillsnap_candidates WHERE id=?'); $st->execute([$cid]); $c=$st->fetch(); if(!$c) throw new RuntimeException('Candidate not found.');
  $role=$role!==null?trim($role):trim((string)$c['target_role']); $location=$location!==null?trim($location):trim((string)$c['target_location']);
  if($role==='') throw new RuntimeException('Target role is required before analysis.');
  $req=role_requirements($role,$location,5000,30); $resume=(string)($c['resume_text']??'');
  if(trim($resume)==='') throw new RuntimeException('Upload a readable resume before analysis.');
  $pdo->beginTransaction();
  try{
    $up=$pdo->prepare("INSERT INTO skillsnap_candidate_requirements(candidate_id,group_name,term,term_norm,description,weight,mentions,auto_status,evidence,updated_at)
      VALUES(?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE term=VALUES(term),description=VALUES(description),weight=VALUES(weight),mentions=VALUES(mentions),auto_status=VALUES(auto_status),evidence=IF(review_status IS NULL,VALUES(evidence),evidence),updated_at=VALUES(updated_at)");
    $active=[];
    foreach($req['groups'] as $g=>$rows){ foreach($rows as $x){ $m=match_requirement($resume,$x['term']); $key=norm_key($x['term']); $active[$g][$key]=1; $up->execute([$cid,$g,$x['term'],$key,$x['description'],$x['weight'],$x['mentions'],$m['status'],$m['evidence'],now_sql()]); } }
    foreach(['skills','experience','projects','certificates'] as $g){
      $keys=array_keys($active[$g]??[]);
      if($keys){ $marks=implode(',',array_fill(0,count($keys),'?')); $args=array_merge([$cid,$g],$keys); $pdo->prepare("DELETE FROM skillsnap_candidate_requirements WHERE candidate_id=? AND group_name=? AND term_norm NOT IN ($marks)")->execute($args); }
      else $pdo->prepare('DELETE FROM skillsnap_candidate_requirements WHERE candidate_id=? AND group_name=?')->execute([$cid,$g]);
    }
    $pdo->commit();
  }catch(Throwable $e){ if($pdo->inTransaction())$pdo->rollBack(); throw $e; }

  $aiMeta=['enabled'=>openai_enabled(),'used'=>false,'errors'=>[],'model'=>''];
  if($useOpenAI && openai_enabled()){
    $st=$pdo->prepare('SELECT id,group_name,term,description FROM skillsnap_candidate_requirements WHERE candidate_id=? ORDER BY id');$st->execute([$cid]);
    $input=[];while($r=$st->fetch())$input[]=['id'=>(int)$r['id'],'group'=>$r['group_name'],'term'=>$r['term'],'description'=>(string)$r['description']];
    $ai=analyze_requirements_openai($resume,$role,$input);$aiMeta=['enabled'=>true,'used'=>count($ai['results'])>0,'errors'=>$ai['errors'],'model'=>$ai['model']];
    if($ai['results']){
      $upd=$pdo->prepare('UPDATE skillsnap_candidate_requirements SET ai_status=?,ai_confidence=?,ai_reason=?,ai_evidence=?,ai_model=?,ai_analyzed_at=?,evidence=IF(review_status IS NULL AND ?<>\'\',?,evidence),updated_at=? WHERE id=? AND candidate_id=?');
      foreach($ai['results'] as $rid=>$x){
        $status=in_array($x['status']??'', ['existing','partial','missing'],true)?$x['status']:'missing';
        $confidence=max(0,min(1,(float)($x['confidence']??0)));$evidence=trim((string)($x['evidence']??''));$reason=trim((string)($x['reason']??''));
        $upd->execute([$status,$confidence,$reason,$evidence,$ai['model'],now_sql(),$evidence,$evidence,now_sql(),$rid,$cid]);
      }
    }
  }
  $snapshot=json_encode(['role'=>$role,'location'=>$location,'jobs_scanned'=>$req['jobs_scanned'],'generated_at'=>now_sql(),'openai'=>$aiMeta],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $pdo->prepare('UPDATE skillsnap_candidates SET target_role=?,target_location=?,requirements_json=?,analysis_version=analysis_version+1,updated_at=? WHERE id=?')->execute([$role,$location,$snapshot,now_sql(),$cid]);
  log_activity($cid,'analysis_completed',"Role: $role; jobs scanned: ".$req['jobs_scanned'].'; OpenAI used: '.($aiMeta['used']?'yes':'no').($aiMeta['errors']?'; errors: '.implode(' | ',$aiMeta['errors']):''));
  return candidate_payload($cid);
}

