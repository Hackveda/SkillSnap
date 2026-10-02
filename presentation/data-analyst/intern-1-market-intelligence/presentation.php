<?php
/** Data Analyst Intern 1: market-intelligence and requirement aggregation.
 * Presentation extract only. Source of truth: /index.php
 */

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
