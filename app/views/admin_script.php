<script>
let currentId=0,currentPayload=null,table=null,noteTimer=null,currentRequirement=null;
const chartInstances={};
const analyticsCharts={};let currentShareUrl='',evidenceScrollY=0;
const groupLabels={skills:'Skills',experience:'Experience',projects:'Projects',certificates:'Certifications'};
const groupDescriptions={
  skills:'Technical, functional and behavioural capabilities demanded by the target role.',
  experience:'Repeated delivery, leadership and domain-experience expectations.',
  projects:'Portfolio and production-grade project evidence expected by employers.',
  certificates:'Certifications and formal credentials requested in matching job descriptions.'
};
const $id=x=>document.getElementById(x);
const esc=s=>String(s??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[c]));
const finalStatus=r=>r.review_status||r.ai_status||r.auto_status;

function toast(m){
  const t=$id('toast');t.textContent=m;t.style.display='block';
  clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>t.style.display='none',3000)
}
function setModal(open){
  document.body.classList.toggle('modalOpen',open);
}
async function api(action,opt={}){
  let url='?action='+encodeURIComponent(action);
  if(opt.query)url+='&'+new URLSearchParams(opt.query);
  const controller=new AbortController();
  const timeoutMs=Number(opt.timeoutMs||120000);
  const timer=setTimeout(()=>controller.abort(),timeoutMs);
  let r;
  try{
    r=await fetch(url,{method:opt.body?'POST':'GET',body:opt.body,signal:controller.signal});
  }catch(e){
    if(e&&e.name==='AbortError')throw new Error('Resume processing timed out. The resume was saved; click Analyse to retry OpenAI marking.');
    throw new Error('Unable to reach the server. Check the Network tab or PHP error log.');
  }finally{clearTimeout(timer)}
  const raw=await r.text();
  let j;try{j=JSON.parse(raw)}catch(e){
    const hint=raw.trim().slice(0,220);
    throw new Error('Server returned an invalid response'+(hint?': '+hint:''));
  }
  if(!r.ok||j.error)throw new Error(j.message||j.error||'Request failed');
  return j;
}

async function loadCandidates(){
  const j=await api('candidate_list',{query:{q:$id('search').value,stage:$id('stageFilter').value}});
  if(table)table.destroy();
  $id('candidateTable').querySelector('tbody').innerHTML=j.rows.map(r=>`
    <tr class="candidateRow" data-id="${r.id}">
      <td><b>${esc(r.full_name)}</b><div class="small">${esc(r.email||r.phone||'')}</div></td>
      <td>${esc(r.target_role||'—')}</td><td>${esc(r.stage)}</td>
    </tr>`).join('');
  table=$('#candidateTable').DataTable({paging:true,pageLength:12,searching:false,info:false,lengthChange:false,order:[],autoWidth:false});
  $('#candidateTable tbody').off('click').on('click','tr',function(){openCandidate(Number(this.dataset.id));});
}
async function openCandidate(id){
  const j=await api('candidate_get',{query:{id}});
  currentId=id;currentPayload=j;render();
}
function render(){
  const p=currentPayload,c=p.candidate;
  $id('emptyState').classList.add('hidden');$id('workspace').classList.remove('hidden');
  $id('candidateTitle').textContent=c.full_name;
  $id('candidateSub').textContent=[c.current_role,c.email,c.phone].filter(Boolean).join(' • ');
  $id('targetRole').value=c.target_role||'';$id('targetLocation').value=c.target_location||'';
  $id('candidateStage').value=c.stage||'Lead';$id('workingNotes').value=c.notes||'';
  $id('resumeMeta').textContent=c.resume_original_name?`${c.resume_original_name} • ${Number(c.resume_text_length||0).toLocaleString()} extracted characters`:'No resume uploaded.';
  $id('downloadResume').classList.toggle('hidden',!c.resume_original_name);
  $id('downloadResume').href='?action=download_resume&id='+c.id;
  $id('matchPercent').textContent=p.match_percent+'%';$id('existingCount').textContent=p.counts.existing;
  $id('partialCount').textContent=p.counts.partial;$id('missingCount').textContent=p.counts.missing;
  renderGraphSections();renderAnalytics();renderAdminPlans();loadShareStatus();loadCompanyMatches();
}
function chartColor(status){
  return status==='existing'?'#2563eb':status==='partial'?'#f59e0b':status==='missing'?'#ef4444':'#94a3b8';
}
const labelWidthPlugin={
  id:'fixedLabelWidth',
  afterFit(scale){
    if(scale.id==='y')scale.width=Math.min(430,Math.max(260,scale.width));
  }
};
Chart.register(labelWidthPlugin);


function planCardHtml(p,index){
  const features=(p.features||[]).map(x=>`<li>${esc(x)}</li>`).join('');
  const b=p.price_breakdown||{};const breakdown=b.one_time_price?`<div class="planBreakdown">${Number(b.weekday_sessions||0)} sessions × ₹${Number(b.weekday_rate||0).toLocaleString('en-IN')} + ₹${Number(b.project_gross||0).toLocaleString('en-IN')} project cost − ${Number(b.discount_percent||0)}% duration saving</div>`:'';
  return `<article class="planRecommendation ${index===0?'best':''}"><div class="planRank">${index===0?'Best match':'Alternative'} · Rank #${Number(p.rank||index+1)}</div><div class="planName">${esc(p.name||'Preparation Plan')}</div><div class="planFit">${Math.round(Number(p.fit_score)||0)}% fit</div><div class="small">${esc(p.price_label||'')} · ${esc(p.duration||'')}</div><div class="planReason">${esc(p.reason||p.summary||'')}</div>${breakdown}<ul class="planFeatures">${features}</ul><a class="btn ${index===0?'':'ghost'}" href="${esc(p.url||'https://thetalentgrid.in/plans/')}" target="_blank" rel="noopener">View Plan</a></article>`;
}

function renderAdminPlans(){
  const c=currentPayload?.candidate||{},rec=c.plan_recommendations||{},plans=Array.isArray(rec.plans)?rec.plans:[];
  $id('planDisplayEnabled').checked=Number(c.plan_display_enabled||0)===1;
  if($id('companyMatchDisplayEnabled'))$id('companyMatchDisplayEnabled').checked=Number(c.company_match_display_enabled||0)===1;
  const basis=rec.basis||{},basisEl=$id('adminPlanBasis');
  if(plans.length){
    basisEl.classList.remove('hidden');
    basisEl.textContent=`Based on ${rec.target_role||'target-role'} readiness: skills ${Math.round(Number(basis.skills_match)||0)}%, experience ${Math.round(Number(basis.experience_match)||0)}%, projects ${Math.round(Number(basis.projects_match)||0)}%, shortlist ${Math.round(Number(basis.shortlist_probability)||0)}%.`;
    $id('adminPlanCards').innerHTML=plans.slice(0,3).map(planCardHtml).join('');
  }else{
    basisEl.classList.add('hidden');
    $id('adminPlanCards').innerHTML='<div class="small">Complete the OpenAI analysis to generate personalized plan recommendations.</div>';
  }
}
function renderGraphSections(){
  const host=$id('graphSections');
  const openState={};
  host.querySelectorAll('details[data-group]').forEach(d=>openState[d.dataset.group]=d.open);
  host.innerHTML='';

  Object.keys(groupLabels).forEach((group,index)=>{
    const rows=currentPayload.groups[group]||[];
    const existing=rows.filter(r=>finalStatus(r)==='existing').length;
    const partial=rows.filter(r=>finalStatus(r)==='partial').length;
    const missing=rows.filter(r=>finalStatus(r)==='missing').length;
    const section=document.createElement('details');
    section.className='graphSection';section.dataset.group=group;
    section.open=openState[group]!==undefined?openState[group]:(index===0);
    section.innerHTML=`
      <summary>
        <div class="graphSummaryLeft"><span class="chev"></span><div><h3>${groupLabels[group]}</h3><div class="small">${groupDescriptions[group]}</div></div></div>
        <div class="graphMeta">${rows.length} requirements • ${existing} existing • ${partial} partial • ${missing} missing</div>
      </summary>
      <div class="graphBody">
        <div class="chartWrap"><div class="chartCanvasBox"><canvas id="${group}Chart"></canvas></div></div>
        <div class="small chartHint">Click a bar to open evidence and manual status review.</div>
      </div>`;
    host.appendChild(section);
    if(section.open)requestAnimationFrame(()=>renderGroupChart(group,rows));
    section.addEventListener('toggle',()=>{
      if(section.open)requestAnimationFrame(()=>renderGroupChart(group,currentPayload.groups[group]||[]));
    });
  });
}
function renderGroupChart(group,rows){
  const canvas=$id(group+'Chart');if(!canvas)return;
  if(chartInstances[group]){chartInstances[group].destroy();delete chartInstances[group]}
  const box=canvas.parentElement,wrap=box.parentElement;
  wrap.querySelectorAll('.emptyChart').forEach(x=>x.remove());
  if(!rows.length){
    canvas.style.display='none';
    const e=document.createElement('div');e.className='small emptyChart';e.style.padding='28px';
    e.textContent='No target-role requirements found in this category.';wrap.appendChild(e);return;
  }
  canvas.style.display='block';
  const logicalHeight=Math.max(300,rows.length*32+80);
  canvas.height=logicalHeight;
  box.style.height=logicalHeight+'px';
  chartInstances[group]=new Chart(canvas,{
    type:'bar',
    data:{
      labels:rows.map(r=>r.term),
      datasets:[{
        label:'Weighted market importance',
        data:rows.map(r=>Number(r.weight)||1),
        backgroundColor:rows.map(r=>chartColor(finalStatus(r))),
        borderWidth:0,borderRadius:6,barThickness:18,maxBarThickness:20
      }]
    },
    options:{
      indexAxis:'y',responsive:true,maintainAspectRatio:false,
      animation:{duration:250},
      onHover:(event,elements)=>{event.native.target.style.cursor=elements.length?'pointer':'default'},
      onClick:(event,elements)=>{
        if(!elements.length)return;
        openEvidenceModal(group,rows[elements[0].index]);
      },
      layout:{padding:{right:24,left:8}},
      plugins:{
        legend:{display:false},
        tooltip:{callbacks:{
          title:items=>rows[items[0].dataIndex].term,
          label:item=>{
            const r=rows[item.dataIndex],s=finalStatus(r);
            return [
              `Status: ${s.replace('_',' ')}`,
              `Weighted score: ${Number(r.weight).toFixed(2)}`,
              `Job mentions: ${r.mentions}`,
              r.evidence?`Evidence: ${r.evidence}`:'Evidence: not recorded'
            ];
          }
        }}
      },
      scales:{
        x:{beginAtZero:true,grid:{color:'#eef2f7'},title:{display:true,text:'Weighted demand score'},ticks:{precision:0}},
        y:{grid:{display:false},ticks:{autoSkip:false,font:{size:12},color:'#334155',padding:8}}
      }
    }
  });
}

function renderAnalytics(){
  if(!currentPayload || !currentPayload.analytics) return;
  Object.values(analyticsCharts).forEach(c=>{ try{ c.destroy(); }catch(e){} });
  Object.keys(analyticsCharts).forEach(k=>delete analyticsCharts[k]);
  const a=currentPayload.analytics;
  const d=a.descriptive;
  const status=d.status_counts;
  const groups=['skills','experience','projects','certificates'];

  analyticsCharts.status=new Chart($id('analyticsStatusChart'),{
    type:'doughnut',
    data:{labels:['Existing','Partial','Missing','Not required'],datasets:[{data:[status.existing,status.partial,status.missing,status.not_required],backgroundColor:['#2563eb','#f59e0b','#ef4444','#94a3b8']}]},
    options:{responsive:true,maintainAspectRatio:false}
  });

  analyticsCharts.category=new Chart($id('analyticsCategoryChart'),{
    type:'bar',
    data:{labels:['Skills','Experience','Projects','Certifications'],datasets:[{data:groups.map(g=>(d.category_match[g]||{}).match||0),backgroundColor:'#2563eb',borderRadius:8}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}
  });

  const gaps=((a.diagnostic&&a.diagnostic.critical_missing)||[]).slice(0,10);
  analyticsCharts.gaps=new Chart($id('analyticsGapChart'),{
    type:'bar',
    data:{labels:gaps.map(x=>x.term),datasets:[{data:gaps.map(x=>x.impact),backgroundColor:'#ef4444',borderRadius:7}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true},y:{ticks:{autoSkip:false}}}}
  });

  analyticsCharts.evidence=new Chart($id('analyticsEvidenceChart'),{
    type:'bar',
    data:{labels:['Weighted match','Evidence completeness','Reviewer completeness'],datasets:[{data:[d.weighted_match,d.evidence_completeness,d.review_completeness],backgroundColor:['#2563eb','#7c3aed','#0ea5e9'],borderRadius:8}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}
  });

  analyticsCharts.prediction=new Chart($id('analyticsPredictionChart'),{
    type:'bar',
    data:{labels:['Shortlist','Interview selection'],datasets:[{data:[a.predictive?.shortlist_probability||0,a.predictive?.interview_selection_probability||0],backgroundColor:['#2563eb','#7c3aed'],borderRadius:8}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}
  });
  $id('predictionMethod').textContent=a.predictive?.method||'Prediction analytics are not available yet.';

  const actions=((a.prescriptive&&a.prescriptive.actions)||[]).slice(0,10);
  analyticsCharts.actions=new Chart($id('analyticsActionChart'),{
    type:'bar',
    data:{labels:actions.map(x=>x.term),datasets:[{data:actions.map(x=>x.priority),backgroundColor:'#f59e0b',borderRadius:7}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false},tooltip:{callbacks:{afterLabel:ctx=>actions[ctx.dataIndex].action}}},scales:{x:{beginAtZero:true},y:{ticks:{autoSkip:false}}}}
  });

  const visual=currentPayload.candidate?.visual_analysis||{};
  const acceptance=(a.diagnostic?.acceptance_causes||[]).slice(0,10),rejection=(a.diagnostic?.rejection_causes||[]).slice(0,10),layout=(a.diagnostic?.layout_gaps||[]).slice(0,10),personas=visual.persona_reviews||[];
  const wrapLabel=(value,max=24)=>{const words=String(value||'').split(/\s+/),lines=[];let line='';for(const w of words){const next=(line+' '+w).trim();if(next.length>max&&line){lines.push(line);line=w}else line=next}if(line)lines.push(line);return lines.slice(0,3)};
  const makeHorizontal=(id,key,rows,label,color)=>{const el=$id(id);if(!el)return;const box=el.parentElement;if(box)box.style.height=Math.max(300,Math.min(520,rows.length*58+110))+'px';analyticsCharts[key]=new Chart(el,{type:'bar',data:{labels:rows.map(x=>wrapLabel(x.title)),datasets:[{label,data:rows.map(x=>Number(x.score)||0),backgroundColor:color,borderRadius:8,barThickness:20,maxBarThickness:24}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,layout:{padding:{left:4,right:14,top:8,bottom:4}},plugins:{legend:{display:false},tooltip:{callbacks:{title:i=>rows[i[0].dataIndex]?.title||'',label:c=>`${label}: ${Math.round(Number(c.raw)||0)}%`,afterLabel:c=>{const x=rows[c.dataIndex]||{};return [x.detail||'',x.evidence?`Evidence: ${x.evidence}`:'',x.page?`Resume page: ${x.page}`:''].filter(Boolean)}}}},scales:{x:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'},grid:{color:'#eef2f7'}},y:{ticks:{autoSkip:false,font:{size:11},color:'#334155'},grid:{display:false}}}}});};
  const findingHtml=rows=>rows.length?rows.map(x=>`<article class="findingItem"><div class="findingTop"><div class="findingTitle">${esc(x.title||'Finding')}</div><div class="findingScore">${Math.round(Number(x.score)||0)}%</div></div>${x.detail?`<div class="findingDetail">${esc(x.detail)}</div>`:''}${x.evidence?`<div class="findingEvidence">${esc(x.evidence)}${Number(x.page)>0?` · Page ${Number(x.page)}`:''}</div>`:''}</article>`).join(''):'<div class="small">No findings are available yet.</div>';
  makeHorizontal('acceptanceChart','acceptance',acceptance,'Acceptance strength','#2563eb');makeHorizontal('rejectionChart','rejection',rejection,'Rejection risk','#ef4444');makeHorizontal('layoutGapChart','layout',layout,'Impact','#f59e0b');
  if($id('acceptanceDetails'))$id('acceptanceDetails').innerHTML=findingHtml(acceptance);if($id('rejectionDetails'))$id('rejectionDetails').innerHTML=findingHtml(rejection);if($id('layoutDetails'))$id('layoutDetails').innerHTML=findingHtml(layout);
  if($id('personaChart'))analyticsCharts.persona=new Chart($id('personaChart'),{type:'bar',data:{labels:personas.map(x=>wrapLabel(x.persona,18)),datasets:[{label:'Accept',data:personas.map(x=>pct100(x.accept_probability)),backgroundColor:'#2563eb',borderRadius:6},{label:'Reject',data:personas.map(x=>pct100(x.reject_probability)),backgroundColor:'#ef4444',borderRadius:6}]},options:{responsive:true,maintainAspectRatio:false,plugins:{tooltip:{callbacks:{title:i=>personas[i[0].dataIndex]?.persona||'',afterBody:i=>{const x=personas[i[0].dataIndex]||{};return [x.company_lens||'',x.perception||''].filter(Boolean)}}},legend:{position:'top'}},scales:{y:{beginAtZero:true,max:100,ticks:{callback:v=>v+'%'}},x:{ticks:{autoSkip:false,font:{size:11}}}}}});
  if($id('visualSummary'))$id('visualSummary').textContent=visual.summary||'Presentation review is not available yet.';
  const scoreDefs=[['Overall',visual.overall_visual_score],['ATS readability',visual.ats_readability_score],['Six-second clarity',visual.six_second_clarity_score],['Hierarchy',visual.hierarchy_score],['Evidence visibility',visual.evidence_visibility_score],['Density',visual.density_score]];
  if($id('visualScoreStrip'))$id('visualScoreStrip').innerHTML=scoreDefs.map(([label,value])=>{const n=Math.max(0,Math.min(100,Number(value)||0));return `<div class="scoreTile"><div class="scoreLabel">${esc(label)}</div><div class="scoreValue">${Math.round(n)}%</div><div class="scoreMeter"><span style="width:${n}%"></span></div></div>`}).join('');
  if($id('personaReport'))$id('personaReport').innerHTML=personas.length?personas.map(x=>{const accept=Math.max(0,Math.min(100,pct100(x.accept_probability))),reject=Math.max(0,Math.min(100,pct100(x.reject_probability)));const accepts=(x.accept_points||[]).map(v=>`<li>${esc(v)}</li>`).join(''),rejects=(x.reject_points||[]).map(v=>`<li>${esc(v)}</li>`).join('');return `<article class="personaCard"><div class="personaHead"><div><div class="personaName">${esc(x.persona||'Reviewer')}</div><div class="personaLens">${esc(x.company_lens||'')}</div></div></div><div class="personaText">${esc(x.perception||'')}</div><div class="probabilityRow"><div class="probBox"><span class="small">Acceptance</span><strong>${Math.round(accept)}%</strong><div class="probBar"><span class="acceptBar" style="width:${accept}%"></span></div></div><div class="probBox"><span class="small">Rejection</span><strong>${Math.round(reject)}%</strong><div class="probBar"><span class="rejectBar" style="width:${reject}%"></span></div></div></div><div class="pointColumns"><div class="pointGroup"><b>What works</b><ul>${accepts||'<li>No strong signal recorded.</li>'}</ul></div><div class="pointGroup"><b>What creates doubt</b><ul>${rejects||'<li>No major concern recorded.</li>'}</ul></div></div></article>`}).join(''):'<div class="small">Reviewer perspectives are not available yet.</div>';
}
async function loadShareStatus(){if(!currentId)return;try{const j=await api('share_status',{query:{candidate_id:currentId}});currentShareUrl=j.share_url||'';$id('copyShareLink').classList.toggle('hidden',!j.enabled);$id('disableShare').classList.toggle('hidden',!j.enabled);$id('shareProfile').textContent=j.enabled?'Share Settings':'Share Profile';}catch(e){}}
async function createShare(){const f=new FormData;f.append('candidate_id',currentId);try{const j=await api('share_create',{body:f});currentPayload=j.payload;currentShareUrl=j.share_url;$id('shareUrl').value=currentShareUrl;$id('shareModal').classList.remove('hidden');setModal(true);loadShareStatus();}catch(e){toast(e.message)}}
async function disableShare(){const f=new FormData;f.append('candidate_id',currentId);try{const j=await api('share_disable',{body:f});currentPayload=j.payload;currentShareUrl='';$id('shareModal').classList.add('hidden');setModal(false);loadShareStatus();toast('Share link disabled')}catch(e){toast(e.message)}}
async function copyShare(){if(!currentShareUrl)return toast('Create a share link first');try{await navigator.clipboard.writeText(currentShareUrl);toast('Share link copied')}catch(e){$id('shareUrl').value=currentShareUrl;$id('shareUrl').select();document.execCommand('copy');toast('Share link copied')}}

function openEvidenceModal(group,row){
  evidenceScrollY=window.scrollY;
  currentRequirement={group,row};
  const s=finalStatus(row);
  $id('evidenceRequirementId').value=row.id;
  $id('evidenceTerm').textContent=row.term;
  $id('evidenceMeta').textContent=`${groupLabels[group]} • Automatic: ${row.ai_status||row.auto_status}${row.ai_confidence!==null&&row.ai_confidence!==undefined?' ('+Math.round(Number(row.ai_confidence)*100)+'% confidence)':''} • Current: ${s} • Score ${Number(row.weight).toFixed(2)} • ${row.mentions} job mentions`;
  const d=$id('evidenceDescription');
  d.textContent=row.description||'';d.classList.toggle('hidden',!row.description);
  $id('evidenceText').value=row.evidence||'';
  $id('reviewerNote').value=row.reviewer_note||'';
  document.querySelectorAll('[data-review-status]').forEach(btn=>{
    const v=btn.dataset.reviewStatus;
    btn.className='statusBtn';
    if((row.review_status===null||row.review_status==='')&&v==='')btn.classList.add('active-auto');
    if(v&&v===s)btn.classList.add('active-'+v);
  });
  $id('evidenceModal').classList.remove('hidden');setModal(true);
}
function closeEvidenceModal(restore=true){
  $id('evidenceModal').classList.add('hidden');currentRequirement=null;setModal(false);
  if(restore)requestAnimationFrame(()=>window.scrollTo({top:evidenceScrollY,left:0,behavior:'auto'}));
}
document.querySelectorAll('[data-review-status]').forEach(btn=>{
  btn.onclick=()=>{
    document.querySelectorAll('[data-review-status]').forEach(x=>x.className='statusBtn');
    btn.classList.add(btn.dataset.reviewStatus?'active-'+btn.dataset.reviewStatus:'active-auto');
  };
});
async function saveEvidence(){
  if(!currentRequirement)return;
  const selected=document.querySelector('[data-review-status].active-existing,[data-review-status].active-partial,[data-review-status].active-missing,[data-review-status].active-auto');
  const fd=new FormData();
  fd.append('id',$id('evidenceRequirementId').value);
  fd.append('review_status',selected?selected.dataset.reviewStatus:'');
  fd.append('evidence',$id('evidenceText').value);
  fd.append('reviewer_note',$id('reviewerNote').value);
  $id('saveState').textContent='Saving evidence…';
  try{
    const j=await api('requirement_update',{body:fd});
    currentPayload=j.payload;closeEvidenceModal(false);render();requestAnimationFrame(()=>window.scrollTo({top:evidenceScrollY,left:0,behavior:'auto'}));toast('Evidence and status updated');
  }catch(e){toast(e.message)}
  finally{$id('saveState').textContent='Ready'}
}

async function runOpenAIStages(){
  const groups=['skills','experience','projects','certificates'];
  let completed=0,failed=[];
  for(const group of groups){
    $id('saveState').textContent='Reviewing candidate view…';
    const fd=new FormData();fd.append('candidate_id',currentId);fd.append('group',group);
    try{
      const j=await api('analyze_openai_group',{body:fd,timeoutMs:90000});
      currentPayload=j.payload;render();completed++;
    }catch(e){failed.push(`${group}: ${e.message}`)}
  }
  if(completed){
    const fd=new FormData();fd.append('candidate_id',currentId);
    try{$id('saveState').textContent='Building resume perspective…';const j=await api('analyze_visual',{body:fd,timeoutMs:150000});currentPayload=j.payload;render();}
    catch(e){toast('The presentation review needs another attempt.');}
  }
  await loadCandidates();
  if(completed)toast('Candidate view refreshed.');else toast('Resume evidence is available; the deeper review needs another attempt.');
}
async function analyze(){
  if(!currentId)return;
  const fd=new FormData();fd.append('candidate_id',currentId);fd.append('target_role',$id('targetRole').value);fd.append('target_location',$id('targetLocation').value);
  $id('saveState').textContent='Refreshing candidate view…';
  try{
    const j=await api('analyze',{body:fd,timeoutMs:60000});currentPayload=j.payload;render();
    if(currentPayload.candidate.openai_configured)await runOpenAIStages();
    else toast('Candidate view refreshed with available evidence.');
  }catch(e){toast(e.message)}
  finally{$id('saveState').textContent='Ready'}
}
async function uploadResume(){
  const f=$id('resumeFile').files[0];if(!f)return toast('Choose a PDF, DOCX or TXT resume');
  const fd=new FormData();fd.append('candidate_id',currentId);fd.append('resume',f);$id('saveState').textContent='Adding resume…';
  try{
    const j=await api('resume_upload',{body:fd,timeoutMs:60000});currentPayload=j.payload;render();loadCandidates();
    toast('Resume added. Candidate view is being refreshed.');
    if(currentPayload.candidate.openai_configured)await runOpenAIStages();
  }catch(e){toast(e.message)}
  finally{$id('saveState').textContent='Ready'}
}
function openModal(edit=false){
  const c=edit&&currentPayload?currentPayload.candidate:{};
  $id('modalTitle').textContent=edit?'Edit Candidate':'New Candidate';
  const map={formId:'id',formName:'full_name',formEmail:'email',formPhone:'phone',formCurrentRole:'current_role',formTargetRole:'target_role',formTargetLocation:'target_location',formCurrentCtc:'current_ctc',formExpectedCtc:'expected_ctc',formNotice:'notice_period',formStage:'stage',formStatus:'status',formNotes:'notes'};
  Object.entries(map).forEach(([id,k])=>$id(id).value=c[k]||'');
  $id('candidateModal').classList.remove('hidden');setModal(true);
}
function closeCandidateModal(){$id('candidateModal').classList.add('hidden');setModal(false)}
async function saveCandidate(){
  const fd=new FormData(),map={id:'formId',full_name:'formName',email:'formEmail',phone:'formPhone',current_role:'formCurrentRole',target_role:'formTargetRole',target_location:'formTargetLocation',current_ctc:'formCurrentCtc',expected_ctc:'formExpectedCtc',notice_period:'formNotice',stage:'formStage',status:'formStatus',notes:'formNotes'};
  Object.entries(map).forEach(([k,id])=>fd.append(k,$id(id).value));
  try{const j=await api('candidate_save',{body:fd});closeCandidateModal();await loadCandidates();await openCandidate(j.id);toast('Candidate saved')}
  catch(e){toast(e.message)}
}
async function updateField(field,value){
  const fd=new FormData();fd.append('id',currentId);fd.append('field',field);fd.append('value',value);$id('saveState').textContent='Saving…';
  try{await api('candidate_field_update',{body:fd});$id('saveState').textContent='Saved'}
  catch(e){toast(e.message)}
  setTimeout(()=>$id('saveState').textContent='Ready',800);
}
async function roleSuggestions(){
  try{const j=await api('role_suggestions',{query:{q:$id('targetRole').value}});$id('roleList').innerHTML=j.rows.map(x=>`<option value="${esc(x.Title)}">${x.cnt} jobs</option>`).join('')}catch(e){}
}
function syncSidebarMetrics(){
  const bar=document.querySelector('.stickyBar');
  const top=Math.max(72,Math.ceil((bar?.getBoundingClientRect().bottom||72)+12));
  document.documentElement.style.setProperty('--sidebar-top',top+'px');
  const wrap=document.querySelector('.candidateTableWrap');
  if(wrap&&document.body.classList.contains('sidebarDocked')&&window.innerWidth>1100){
    wrap.style.maxHeight='none';
    wrap.setAttribute('tabindex','0');
    wrap.setAttribute('aria-label','Scrollable candidate list');
  }
}
function applySidebarState(){
  const docked=localStorage.getItem('candidateSidebarDocked')!=='0';
  const collapsed=localStorage.getItem('candidateSidebarCollapsed')==='1';
  document.body.classList.toggle('sidebarDocked',docked);
  document.body.classList.toggle('sidebarCollapsed',collapsed);
  $id('dockSidebar').classList.toggle('active',docked);
  $id('collapseSidebar').classList.toggle('active',collapsed);
  syncSidebarMetrics();
  setTimeout(()=>{Object.values(chartInstances).forEach(c=>c.resize());syncSidebarMetrics()},150);
}
const candidateScroll=document.querySelector('.candidateTableWrap');
if(candidateScroll){
  candidateScroll.addEventListener('wheel',e=>{if(candidateScroll.scrollHeight>candidateScroll.clientHeight){candidateScroll.scrollTop+=e.deltaY;e.preventDefault()}},{passive:false});
  candidateScroll.addEventListener('keydown',e=>{const step=Math.max(60,Math.round(candidateScroll.clientHeight*.8));if(e.key==='PageDown'){candidateScroll.scrollTop+=step;e.preventDefault()}else if(e.key==='PageUp'){candidateScroll.scrollTop-=step;e.preventDefault()}else if(e.key==='Home'){candidateScroll.scrollTop=0;e.preventDefault()}else if(e.key==='End'){candidateScroll.scrollTop=candidateScroll.scrollHeight;e.preventDefault()}});
}


$id('dockSidebar').onclick=()=>{
  localStorage.setItem('candidateSidebarDocked',document.body.classList.contains('sidebarDocked')?'0':'1');
  applySidebarState();
};
$id('collapseSidebar').onclick=()=>{
  localStorage.setItem('candidateSidebarCollapsed',document.body.classList.contains('sidebarCollapsed')?'0':'1');
  applySidebarState();
};
$id('newCandidate').onclick=()=>openModal(false);
$id('editCandidate').onclick=()=>openModal(true);
$id('closeModal').onclick=closeCandidateModal;
$id('closeEvidence').onclick=closeEvidenceModal;
$id('saveCandidate').onclick=saveCandidate;
$id('saveEvidence').onclick=saveEvidence;
$id('analyzeBtn').onclick=analyze;
$id('uploadResume').onclick=uploadResume;
$id('search').oninput=()=>{clearTimeout(window.srch);window.srch=setTimeout(loadCandidates,250)};
$id('stageFilter').onchange=loadCandidates;
$id('candidateStage').onchange=e=>updateField('stage',e.target.value);
$id('workingNotes').oninput=e=>{clearTimeout(noteTimer);noteTimer=setTimeout(()=>updateField('notes',e.target.value),700)};
$id('targetRole').oninput=()=>{clearTimeout(window.roleT);window.roleT=setTimeout(roleSuggestions,250)};
$id('deleteCandidate').onclick=async()=>{
  if(!confirm('Delete this candidate and all resume analysis data?'))return;
  const fd=new FormData();fd.append('id',currentId);
  try{
    await api('candidate_delete',{body:fd});currentId=0;currentPayload=null;
    $id('workspace').classList.add('hidden');$id('emptyState').classList.remove('hidden');
    loadCandidates();toast('Candidate deleted');
  }catch(e){toast(e.message)}
};
$id('planDisplayEnabled').onchange=async e=>{
  if(!currentId)return;const fd=new FormData();fd.append('candidate_id',currentId);fd.append('enabled',e.target.checked?'1':'0');
  try{const j=await api('plan_visibility_update',{body:fd});currentPayload=j.payload;renderAdminPlans();toast(e.target.checked?'Plans shown on shared profile':'Plans hidden from shared profile')}catch(err){e.target.checked=!e.target.checked;toast(err.message)}
};
$id('refreshPlans').onclick=async()=>{
  if(!currentId)return;const fd=new FormData();fd.append('candidate_id',currentId);$id('saveState').textContent='Refreshing plans…';
  try{const j=await api('plan_recommendations_refresh',{body:fd});currentPayload=j.payload;renderAdminPlans();toast('Plan recommendations refreshed')}catch(err){toast(err.message)}finally{$id('saveState').textContent='Ready'}
};
$id('shareProfile').onclick=createShare;$id('copyShareLink').onclick=copyShare;$id('disableShare').onclick=disableShare;$id('closeShare').onclick=()=>{$id('shareModal').classList.add('hidden');setModal(false)};$id('copyShareModal').onclick=copyShare;$id('openShareLink').onclick=()=>{if(currentShareUrl)window.open(currentShareUrl,'_blank','noopener')};$id('disableShareModal').onclick=disableShare;

let companyJobs=[],companyChart=null;
function pct100(v){v=Number(v)||0;return v>0&&v<=1?v*100:Math.max(0,Math.min(100,v))}
function cmEndpoint(){return (typeof shareToken!=='undefined'&&shareToken)?`?action=public_company_matches&token=${encodeURIComponent(shareToken)}`:`?action=company_matches&candidate_id=${encodeURIComponent((typeof currentId!=='undefined'?currentId:0)||currentPayload?.candidate?.id||0)}`}
let companyMatchPoll=null;let companyHistory=[];function renderCompanyHistory(){const el=$id('cmHistory');if(!el)return;const selected=el.value;el.innerHTML='<option value="">Latest match</option>'+companyHistory.map(x=>`<option value="${Number(x.id)}">${esc((x.completed_at||x.requested_at||'').replace(' ',' · '))} · ${esc(x.status)} · ${Number(x.result_count||0)} jobs</option>`).join('');if(selected&&companyHistory.some(x=>String(x.id)===String(selected)))el.value=selected}async function loadCompanyMatches(force=false,runId=0){const sec=$id('companyMatchSection');if(!sec)return;const c=currentPayload?.candidate||{};const isPublic=typeof shareToken!=='undefined'&&shareToken;if(isPublic&&Number(c.company_match_display_enabled||0)!==1){sec.classList.add('hidden');return}sec.classList.remove('hidden');const qs=new URLSearchParams({start_date:$id('cmStart')?.value||'',end_date:$id('cmEnd')?.value||'',company:$id('cmCompany')?.value||'',location:$id('cmLocation')?.value||'',q:$id('cmSearch')?.value||''});if(force)qs.set('refresh','1');if(runId)qs.set('run_id',String(runId));try{const r=await fetch(cmEndpoint()+'&'+qs.toString(),{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});const raw=await r.text();let out=null;try{out=JSON.parse(raw)}catch(_){const isHtml=/^\s*<!doctype|^\s*<html/i.test(raw);if(r.status===401||isHtml)throw new Error('Your session expired. Refresh the page and sign in again.');throw new Error('The matching service returned an unreadable response. Please retry.')}if(!r.ok||!out.ok)throw new Error(out.message||out.error||'Unable to load matches.');companyHistory=Array.isArray(out.history)?out.history:[];renderCompanyHistory();if($id('cmRunStatus'))$id('cmRunStatus').textContent=out.refreshing?(out.quick?'Quick results · full scan running':'Matching queued'):(out.run?.completed_at?`Saved ${out.run.completed_at}`:'Saved results');companyJobs=Array.isArray(out.jobs)?out.jobs:[];if(companyJobs.length){renderCompanyList();showCompanyJob(0)}else{$id('cmList').innerHTML=`<div class="companyEmpty">${out.refreshing?'Preparing quick matches…':'No saved matches for this selection.'}</div>`;$id('cmDetail').innerHTML='<div class="companyEmpty">Quick results appear first. The complete scan continues in the background.</div>'}if(out.refreshing&&!runId){clearTimeout(companyMatchPoll);companyMatchPoll=setTimeout(()=>loadCompanyMatches(false),5000)}else clearTimeout(companyMatchPoll)}catch(e){clearTimeout(companyMatchPoll);$id('cmList').innerHTML=`<div class="companyEmpty">${esc(e.message)}</div>`;$id('cmDetail').innerHTML='<div class="companyEmpty">Previously saved history remains available.</div>'}}
function renderCompanyList(){const el=$id('cmList');if(!el)return;el.innerHTML=companyJobs.length?companyJobs.map((j,i)=>`<div class="companyRow ${i===0?'active':''}" data-cm-index="${i}"><div class="companyRowTop"><div><b>${esc(j.company||'Company')}</b><div>${esc(j.title||'Role')}</div></div><div class="companyScore">${Math.round(Number(j.overall_score)||0)}%</div></div><div class="companyMeta">${esc(j.location||'')}${j.salary?' • '+esc(j.salary):''}</div></div>`).join(''):'<div class="companyEmpty">No matching jobs found.</div>';el.querySelectorAll('.companyRow').forEach(x=>x.onclick=()=>{el.querySelectorAll('.companyRow').forEach(y=>y.classList.remove('active'));x.classList.add('active');showCompanyJob(Number(x.dataset.cmIndex))})}
function showCompanyJob(i){const j=companyJobs[i],el=$id('cmDetail');if(!j||!el)return;const gs=j.groups||{},cats=['skills','experience','projects','certificates'];const labels={skills:'Skills',experience:'Experience',projects:'Projects',certificates:'Certifications'};const action=(typeof shareToken!=='undefined'&&shareToken)?`?action=public_ats_resume_pdf&token=${encodeURIComponent(shareToken)}&job_id=${j.job_id}`:`?action=ats_resume_pdf&candidate_id=${(typeof currentId!=='undefined'?currentId:0)||currentPayload?.candidate?.id||0}&job_id=${j.job_id}`;el.innerHTML=`<div class="companyToolbar"><div><h3>${esc(j.company)} — ${esc(j.title)}</h3><div class="sub">${esc(j.location||'')}${j.date?' • '+esc(j.date):''}${j.salary?' • '+esc(j.salary):''}</div></div><div class="pills">${j.apply_url?`<a class="btn ghost" href="${esc(j.apply_url)}" target="_blank" rel="noopener">Apply</a>`:''}<a class="btn" href="${action}" target="_blank" rel="noopener">Download ATS Resume</a></div></div><div class="companyCategoryGrid">${cats.map(g=>`<div class="companyCategory"><span class="small">${labels[g]}</span><strong>${Math.round(Number(gs[g]?.score)||0)}%</strong><span class="small">${Number(gs[g]?.existing?.length||0)} existing • ${Number(gs[g]?.partial?.length||0)} partial • ${Number(gs[g]?.missing?.length||0)} missing</span></div>`).join('')}</div><div class="companyDetailChart"><canvas id="companyDetailChart"></canvas></div><div class="companyBreakdown" style="margin-top:12px"><div><b>Existing</b><ul>${cats.flatMap(g=>(gs[g]?.existing||[]).map(x=>`<li>${esc(x)}</li>`)).join('')||'<li>None</li>'}</ul></div><div><b>Partial</b><ul>${cats.flatMap(g=>(gs[g]?.partial||[]).map(x=>`<li>${esc(x)}</li>`)).join('')||'<li>None</li>'}</ul></div><div><b>Missing</b><ul>${cats.flatMap(g=>(gs[g]?.missing||[]).map(x=>`<li>${esc(x)}</li>`)).join('')||'<li>None</li>'}</ul></div></div>`;if(companyChart)companyChart.destroy();companyChart=new Chart($id('companyDetailChart'),{type:'bar',data:{labels:cats.map(g=>labels[g]),datasets:[{label:'Existing',data:cats.map(g=>gs[g]?.existing?.length||0),backgroundColor:'#2563eb'},{label:'Partial',data:cats.map(g=>gs[g]?.partial?.length||0),backgroundColor:'#f59e0b'},{label:'Missing',data:cats.map(g=>gs[g]?.missing?.length||0),backgroundColor:'#ef4444'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true,ticks:{precision:0}}}}})}
if($id('cmRefresh'))$id('cmRefresh').onclick=()=>{if($id('cmHistory'))$id('cmHistory').value='';loadCompanyMatches(true)};if($id('cmHistory'))$id('cmHistory').onchange=()=>loadCompanyMatches(false,Number($id('cmHistory').value||0));


if($id('companyMatchDisplayEnabled'))$id('companyMatchDisplayEnabled').onchange=async()=>{if(!currentId)return;try{const fd=new FormData();fd.append('candidate_id',currentId);fd.append('enabled',$id('companyMatchDisplayEnabled').checked?'1':'0');const out=await api('company_match_visibility_update',{method:'POST',body:fd});currentPayload=out.payload;toast($id('companyMatchDisplayEnabled').checked?'Company matching is visible on the shared profile.':'Company matching is hidden from the shared profile.')}catch(e){toast(e.message)}};
window.addEventListener('resize',()=>{clearTimeout(window.resizeT);window.resizeT=setTimeout(()=>Object.values(chartInstances).forEach(c=>c.resize()),150)});
applySidebarState();loadCandidates();roleSuggestions();
</script>
</body>
