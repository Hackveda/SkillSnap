<?php
$shareToken=getv('share');
if($shareToken!==''){
  $pp=public_candidate_payload($shareToken);
  if(!$pp){
    http_response_code(404);
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Profile unavailable</title></head><body><div style="max-width:700px;margin:50px auto;font:16px system-ui"><h1>Profile unavailable</h1><p>This link is invalid or disabled.</p></div></body></html>';
    exit;
  }

  $pc=$pp['candidate'];
  $scheme=(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http';
  $canonical=$scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').strtok($_SERVER['REQUEST_URI'],'?').'?share='.rawurlencode($shareToken);
  $metaTitle=trim((string)$pc['full_name']).' — '.trim((string)($pc['target_role']?:'Candidate Profile')).' | SkillSnap';
  $metaDescription=trim((string)$pc['full_name']).' candidate profile for '.trim((string)($pc['target_role']?:'the target role')).'. Weighted match: '.$pp['match_percent'].'%. Existing: '.$pp['counts']['existing'].', partial: '.$pp['counts']['partial'].', missing: '.$pp['counts']['missing'].'.';
  $metaImage=getenv('CANDIDATE_SHARE_IMAGE')?:($scheme.'://'.($_SERVER['HTTP_HOST']??'localhost').rtrim(dirname(strtok($_SERVER['REQUEST_URI'],'?')),'/').'/skillsnap-share.svg');
  $pj=json_encode($pp,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE);
  if($pj===false){ $pj='{}'; }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($metaTitle)?></title>
<meta name="description" content="<?=h($metaDescription)?>">
<meta name="robots" content="noindex,follow">
<link rel="canonical" href="<?=h($canonical)?>">
<meta property="og:type" content="profile">
<meta property="og:site_name" content="SkillSnap">
<meta property="og:title" content="<?=h($metaTitle)?>">
<meta property="og:description" content="<?=h($metaDescription)?>">
<meta property="og:url" content="<?=h($canonical)?>">
<meta property="og:image" content="<?=h($metaImage)?>">
<meta property="og:image:alt" content="<?=h($pc['full_name'].' candidate profile for '.$pc['target_role'])?>">
<meta property="profile:first_name" content="<?=h($pc['full_name'])?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?=h($metaTitle)?>">
<meta name="twitter:description" content="<?=h($metaDescription)?>">
<meta name="twitter:image" content="<?=h($metaImage)?>">
<meta name="theme-color" content="#2563eb">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<style>
:root{
  --bg:#fff;--panel:#fff;--mut:#5b6472;--fg:#0b1220;--line:#e7eaf0;
  --shadow:0 6px 18px rgba(16,24,40,.08);--acc:#2563eb;--acc2:#7c3aed;
  --existing:#2563eb;--partial:#f59e0b;--missing:#ef4444;--neutral:#94a3b8;--pill:#f6f7fb
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;background:var(--bg);color:var(--fg);font:14px/1.55 system-ui,-apple-system,Segoe UI,Arial}
body.modalOpen{overflow:hidden}
button,input,select,textarea{font:inherit}
h1,h2,h3{margin:0}
.small,.sub,label{font-size:12px;color:var(--mut)}
.hidden{display:none!important}
.stickyBar{position:sticky;top:0;z-index:80;background:rgba(255,255,255,.95);backdrop-filter:saturate(160%) blur(8px);border-bottom:1px solid var(--line)}
.stickyInner{max-width:1450px;margin:auto;padding:11px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.brand{display:flex;align-items:center;gap:10px}.dot{width:10px;height:10px;border-radius:50%;background:var(--existing);box-shadow:0 0 0 4px rgba(37,99,235,.12)}
.pills{display:flex;gap:8px;flex-wrap:wrap;align-items:center}.pill{background:var(--pill);border:1px solid var(--line);border-radius:999px;padding:7px 10px;font-size:12px}
.workspaceShell{max-width:1450px;margin:auto;padding:18px}
.grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
.card,.kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px;box-shadow:var(--shadow)}
.kpi .v{font-size:28px;font-weight:900}.hr{height:1px;background:var(--line);margin:12px 0}
.sectionTitle{display:flex;justify-content:space-between;align-items:center;gap:10px}
input,select,textarea{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fff;color:var(--fg);outline:none}
textarea{min-height:100px;resize:vertical}
input:focus,select:focus,textarea:focus{border-color:#b7c4ff;box-shadow:0 0 0 4px rgba(37,99,235,.12)}
.btn{background:var(--acc);border:0;color:#fff;padding:10px 14px;border-radius:12px;font-weight:800;cursor:pointer;white-space:nowrap}
.btn.ghost{background:#fff;color:var(--fg);border:1px solid var(--line)}
.legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}.legendDot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px}
.blue{background:var(--existing)}.amber{background:var(--partial)}.red{background:var(--missing)}.grey{background:var(--neutral)}
.graphSection{border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow);overflow:hidden;margin-top:12px}
.graphSection summary{list-style:none;cursor:pointer;padding:14px;display:flex;align-items:center;justify-content:space-between;gap:10px;background:#fff}
.graphSection summary::-webkit-details-marker{display:none}
.graphSummaryLeft{display:flex;align-items:center;gap:10px;min-width:0}
.chev{width:10px;height:10px;border-right:2px solid #94a3b8;border-bottom:2px solid #94a3b8;transform:rotate(45deg);transition:.2s}
.graphSection[open] .chev{transform:rotate(-135deg)}
.graphBody{border-top:1px solid var(--line);padding:14px}
.chartWrap{overflow:auto;border:1px solid var(--line);border-radius:14px;background:#fff;max-height:720px;min-height:160px;position:relative}
.chartCanvasBox{min-width:900px;padding:10px 14px 10px 4px}.chartHint{margin-top:8px}.graphMeta{font-size:12px;color:var(--mut);text-align:right}
.modal{position:fixed;inset:0;z-index:999;background:rgba(15,23,42,.52);display:flex;align-items:center;justify-content:center;padding:18px}
.modalBox{width:min(850px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:18px;padding:18px;box-shadow:0 30px 70px rgba(15,23,42,.28)}
.modalGrid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
.statusButtons{display:flex;gap:7px;flex-wrap:wrap}
.statusBtn{padding:9px 12px;border:1px solid var(--line);background:#fff;border-radius:10px;cursor:pointer;font-weight:800}
.statusBtn.active-existing{background:#dbeafe;border-color:#93c5fd;color:#1d4ed8}
.statusBtn.active-partial{background:#fef3c7;border-color:#fcd34d;color:#92400e}
.statusBtn.active-missing{background:#fee2e2;border-color:#fca5a5;color:#991b1b}
.statusBtn.active-not_required{background:#e2e8f0;border-color:#cbd5e1;color:#475569}
.statusBtn.active-auto{background:#f1f5f9;border-color:#cbd5e1;color:#334155}
.evidenceDescription{padding:12px;border-radius:12px;background:#f8fafc;border:1px solid var(--line);color:#334155}
.notice{padding:12px;border-radius:12px;background:#eff6ff;border:1px solid #bfdbfe;color:#1d4ed8}
.toast{position:fixed;right:18px;bottom:18px;z-index:2000;background:#0b1220;color:#fff;padding:12px 16px;border-radius:12px;display:none;max-width:min(700px,calc(100vw - 36px))}

.perceptionSection{min-width:0}.visualGrid>*{min-width:0}.perceptionIntro{overflow:hidden}.scoreStrip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:14px}.scoreTile{border:1px solid var(--line);border-radius:14px;padding:12px;background:#f8fafc;min-width:0}.scoreTile .scoreLabel{font-size:11px;color:var(--mut);line-height:1.3}.scoreTile .scoreValue{font-size:24px;font-weight:900;margin-top:3px}.scoreMeter{height:6px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin-top:8px}.scoreMeter>span{display:block;height:100%;border-radius:inherit;background:var(--acc)}.insightCard{min-width:0;overflow:hidden}.insightHeading{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px}.insightTag{font-size:11px;font-weight:800;border-radius:999px;padding:5px 9px;white-space:nowrap}.acceptTag{background:#eff6ff;color:#1d4ed8}.rejectTag{background:#fef2f2;color:#b91c1c}.layoutTag{background:#fffbeb;color:#92400e}.personaTag{background:#f5f3ff;color:#6d28d9}.visualChartBox{height:390px;min-height:300px;position:relative;width:100%;overflow:hidden}.findingList{display:grid;gap:8px;margin-top:12px}.findingItem{border:1px solid var(--line);border-radius:12px;padding:11px;background:#fff}.findingTop{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.findingTitle{font-weight:850;overflow-wrap:anywhere}.findingScore{font-weight:900;white-space:nowrap}.findingDetail{color:#334155;margin-top:5px;overflow-wrap:anywhere}.findingEvidence{font-size:12px;color:var(--mut);margin-top:6px;padding-left:9px;border-left:3px solid #dbeafe;overflow-wrap:anywhere}.reportSummary{margin-top:8px;color:#334155;max-width:1100px}.personaGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.personaCard{border:1px solid var(--line);border-radius:14px;padding:14px;background:#fff;min-width:0}.personaHead{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}.personaName{font-weight:900;font-size:16px}.personaLens{font-size:12px;color:var(--mut);margin-top:2px}.personaText{margin-top:10px;color:#334155}.probabilityRow{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}.probBox{border-radius:12px;padding:10px;background:#f8fafc}.probBox strong{display:block;font-size:20px}.probBar{height:7px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:6px}.probBar span{display:block;height:100%}.acceptBar{background:#2563eb}.rejectBar{background:#ef4444}.pointColumns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}.pointGroup{font-size:12px}.pointGroup b{display:block;margin-bottom:4px}.pointGroup ul{margin:0;padding-left:18px}.pointGroup li{margin:3px 0;overflow-wrap:anywhere}
@media(max-width:900px){.grid>*{grid-column:span 12!important}.chartCanvasBox{min-width:760px}.stickyInner{align-items:flex-start;flex-direction:column}.workspaceShell{padding:10px}.modalGrid>*{grid-column:span 12!important}.scoreStrip{grid-template-columns:repeat(3,minmax(0,1fr))}.personaGrid{grid-template-columns:1fr}.visualChartBox{height:340px}}
@media(max-width:560px){.scoreStrip{grid-template-columns:repeat(2,minmax(0,1fr))}.pointColumns,.probabilityRow{grid-template-columns:1fr}.visualChartBox{height:320px}.graphSection summary{align-items:flex-start;flex-direction:column}.graphMeta{text-align:left}}

.planRecommendationGrid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.planRecommendation{border:1px solid var(--line);border-radius:16px;padding:16px;background:#fff;display:flex;flex-direction:column;min-width:0}
.planRecommendation.best{border:2px solid var(--acc);box-shadow:0 12px 28px rgba(37,99,235,.10)}
.planRank{font-size:11px;font-weight:900;color:var(--acc);text-transform:uppercase;letter-spacing:.06em}
.planName{font-size:18px;font-weight:900;margin-top:5px;color:var(--fg)}
.planFit{font-size:28px;font-weight:950;margin:8px 0 2px}
.planReason{color:var(--mut);font-size:13px;margin-top:8px}
.planFeatures{margin:10px 0 14px;padding-left:18px;color:#334155;font-size:13px}
.planFeatures li{margin:5px 0}
.planRecommendation .btn{margin-top:auto;width:100%}
.planToggle{display:inline-flex;align-items:center;gap:8px;padding:8px 11px;border:1px solid var(--line);border-radius:999px;background:#fff;color:var(--fg);font-size:12px;font-weight:800}
.planToggle input{width:auto}
@media(max-width:980px){.planRecommendationGrid{grid-template-columns:1fr}}

.companyMatchSection{margin-top:12px}.companyFilters{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px}.companyFilters>div{grid-column:span 3}.companyFilters .wide{grid-column:span 6}.companyToolbar{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}.companyMatchGrid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:12px;margin-top:12px}.companyList{max-height:620px;overflow:auto;border:1px solid var(--line);border-radius:14px}.companyRow{padding:13px;border-bottom:1px solid var(--line);cursor:pointer;background:#fff}.companyRow:last-child{border-bottom:0}.companyRow:hover,.companyRow.active{background:#eff6ff}.companyRowTop{display:flex;justify-content:space-between;gap:8px}.companyScore{font-size:18px;font-weight:950;color:var(--acc)}.companyMeta{font-size:12px;color:var(--mut);margin-top:4px}.companyDetail{border:1px solid var(--line);border-radius:14px;padding:14px;min-width:0}.companyCategoryGrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:12px 0}.companyCategory{border:1px solid var(--line);border-radius:12px;padding:10px}.companyCategory strong{display:block;font-size:20px}.companyBreakdown{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.companyBreakdown>div{border-radius:12px;padding:10px;background:#f8fafc;min-width:0}.companyBreakdown ul{margin:7px 0 0 18px;padding:0;max-height:190px;overflow:auto}.companyDetailChart{height:300px}.companyEmpty{padding:24px;text-align:center;color:var(--mut)}
@media(max-width:900px){.companyMatchGrid{grid-template-columns:1fr}.companyFilters>div,.companyFilters .wide{grid-column:span 12}.companyCategoryGrid{grid-template-columns:repeat(2,minmax(0,1fr))}.companyBreakdown{grid-template-columns:1fr}.companyList{max-height:420px}}
</style>
</head>
<body>
<div class="stickyBar">
  <div class="stickyInner">
    <div class="brand"><span class="dot"></span><div><div style="font-weight:900">SkillSnap</div><div class="small">Resume evidence → target-role gap → shortlist and interview action.</div></div></div>
    <div class="pills"><span class="pill" id="saveState">Candidate Review</span></div>
  </div>
</div>

<main class="workspaceShell">
  <div class="card">
    <div class="sectionTitle">
      <div><h1 id="candidateTitle"></h1><div class="sub" id="candidateSub"></div></div>
      <span class="pill">Shared Profile</span>
    </div>
    <div class="hr"></div>
    <div class="grid">
      <div style="grid-column:span 4"><label>Target role</label><input id="targetRole" readonly></div>
      <div style="grid-column:span 3"><label>Target location</label><input id="targetLocation" readonly></div>
      <div style="grid-column:span 3"><label>Stage</label><input id="candidateStage" readonly></div>
      <div style="grid-column:span 2"><label>Status</label><input id="candidateStatus" readonly></div>
    </div>
    <div class="notice" style="margin-top:12px">Click any graph bar to update its status, evidence, or note. Your changes are saved directly to this profile.</div>
  </div>

  <div class="grid" style="margin-top:12px">
    <div style="grid-column:span 3" class="kpi"><div class="small">Weighted match</div><div class="v" id="matchPercent">0%</div></div>
    <div style="grid-column:span 3" class="kpi"><div class="small">Existing</div><div class="v" id="existingCount">0</div></div>
    <div style="grid-column:span 3" class="kpi"><div class="small">Partial</div><div class="v" id="partialCount">0</div></div>
    <div style="grid-column:span 3" class="kpi"><div class="small">Missing</div><div class="v" id="missingCount">0</div></div>
  </div>

  <div class="card" style="margin-top:12px">
    <div class="sectionTitle"><div><h3>Candidate Match Graphs</h3><div class="sub">Click any bar to review evidence or override its status.</div></div></div>
    <div class="legend"><span><i class="legendDot blue"></i>Existing</span><span><i class="legendDot amber"></i>Partial</span><span><i class="legendDot red"></i>Missing</span><span><i class="legendDot grey"></i>Not required</span></div>
  </div>

  <div id="graphSections"></div>

  <div class="card" style="margin-top:12px"><div class="sectionTitle"><div><h3>Candidate Decision Insights</h3><div class="sub">Evidence, presentation and reviewer perspectives combined into one candidate view.</div></div></div></div>
  <div class="grid" style="margin-top:12px">
    <div style="grid-column:span 6" class="card"><h3>Profile Evidence</h3><div style="height:320px"><canvas id="analyticsStatusChart"></canvas></div></div>
    <div style="grid-column:span 6" class="card"><h3>Role Readiness</h3><div style="height:320px"><canvas id="analyticsCategoryChart"></canvas></div></div>
    <div style="grid-column:span 6" class="card"><h3>Highest-Impact Gaps</h3><div style="height:380px"><canvas id="analyticsGapChart"></canvas></div></div>
    <div style="grid-column:span 6" class="card"><h3>Resume Strength Signals</h3><div style="height:320px"><canvas id="analyticsEvidenceChart"></canvas></div></div>
    <div style="grid-column:span 6" class="card"><h3>Estimated Outcomes</h3><div style="height:320px"><canvas id="analyticsPredictionChart"></canvas></div><div class="small" id="predictionMethod"></div></div>
    <div style="grid-column:span 6" class="card"><h3>Priority Actions</h3><div style="height:380px"><canvas id="analyticsActionChart"></canvas></div></div>
  </div>

  <section class="perceptionSection" style="margin-top:12px">
  <div class="card perceptionIntro">
    <div class="sectionTitle"><div><h3>Resume Perception Review</h3><div class="sub">What strengthens confidence, what creates doubt, and how each reviewer may respond.</div></div></div>
    <div id="visualScoreStrip" class="scoreStrip" aria-live="polite"></div>
  </div>
  <div class="grid visualGrid" style="margin-top:12px">
    <div style="grid-column:span 6" class="card insightCard"><div class="insightHeading"><div><h3>Acceptance Causes</h3><div class="sub">Signals that support progression.</div></div><span class="insightTag acceptTag">Strengths</span></div><div class="visualChartBox"><canvas id="acceptanceChart"></canvas></div><div id="acceptanceDetails" class="findingList"></div></div>
    <div style="grid-column:span 6" class="card insightCard"><div class="insightHeading"><div><h3>Rejection Causes</h3><div class="sub">Signals that may stop progression.</div></div><span class="insightTag rejectTag">Risks</span></div><div class="visualChartBox"><canvas id="rejectionChart"></canvas></div><div id="rejectionDetails" class="findingList"></div></div>
    <div style="grid-column:span 6" class="card insightCard"><div class="insightHeading"><div><h3>Layout &amp; Positioning Gaps</h3><div class="sub">Visibility, order, spacing, and scanability.</div></div><span class="insightTag layoutTag">Presentation</span></div><div class="visualChartBox"><canvas id="layoutGapChart"></canvas></div><div id="layoutDetails" class="findingList"></div></div>
    <div style="grid-column:span 6" class="card insightCard"><div class="insightHeading"><div><h3>Reviewer Perspectives</h3><div class="sub">How different decision-makers may respond.</div></div><span class="insightTag personaTag">Personas</span></div><div class="visualChartBox"><canvas id="personaChart"></canvas></div></div>
  </div>
  <div class="card" id="visualSummaryCard" style="margin-top:12px"><h3>Detailed Resume Gap Report</h3><div class="reportSummary" id="visualSummary">Add a resume and refresh the candidate view to build this report.</div><div id="personaReport" class="personaGrid" style="margin-top:14px"></div></div>
</section>

<section id="companyMatchSection" class="companyMatchSection hidden">
 <div class="card"><div class="companyToolbar"><div><h3>Matching Companies &amp; Jobs</h3><div class="sub">Roles ranked against this candidate’s skills, experience, projects and certifications.</div></div></div>
 <div class="companyFilters" style="margin-top:12px"><div><label>Start date</label><input type="date" id="cmStart"></div><div><label>End date</label><input type="date" id="cmEnd"></div><div><label>Company</label><input id="cmCompany" placeholder="e.g., Infosys"></div><div><label>Location</label><input id="cmLocation" placeholder="e.g., Bengaluru"></div><div class="wide"><label>Search</label><input id="cmSearch" placeholder="Title / Company / Location"></div><div style="display:flex;align-items:end"><button class="btn" id="cmRefresh">Refresh matches</button></div><div class="wide"><label>Match history</label><select id="cmHistory"><option value="">Latest match</option></select></div><div style="display:flex;align-items:end"><span class="pill" id="cmRunStatus">Saved results</span></div></div>
 <div class="companyMatchGrid"><div class="companyList" id="cmList"></div><div class="companyDetail" id="cmDetail"><div class="companyEmpty">Select a company to review its role match.</div></div></div></div>
</section>
<section id="sharedPlansSection" class="hidden" style="margin-top:12px">
  <div class="card">
    <div class="sectionTitle"><div><h3>Recommended Preparation Plans</h3><div class="sub">Selected from the resume evidence, role gaps and interview-readiness analysis.</div></div><span class="pill">Personalized</span></div>
    <div id="sharedPlanBasis" class="notice" style="margin-top:12px"></div>
    <div id="sharedPlanCards" class="planRecommendationGrid" style="margin-top:12px"></div>
  </div>
</section>
<div class="card" style="margin-top:12px">
    <h3>Your Overall Review</h3>
    <div class="sub">Confirm what is correct and note anything that needs correction or additional evidence.</div>
    <textarea id="overallReview" style="margin-top:10px"></textarea>
    <button class="btn" id="saveOverallReview" style="margin-top:10px">Save Overall Review</button>
  </div>
</main>

<div id="evidenceModal" class="modal hidden">
  <div class="modalBox">
    <div class="sectionTitle"><div><h2 id="evidenceTerm">Requirement Evidence</h2><div class="sub" id="evidenceMeta"></div></div><button class="btn ghost" id="closeEvidence">Close</button></div>
    <div class="hr"></div>
    <input type="hidden" id="evidenceRequirementId">
    <div id="evidenceDescription" class="evidenceDescription hidden"></div>
    <div style="margin-top:12px"><label>Profile status</label><div class="statusButtons">
      <button class="statusBtn" data-review-status="">Use automatic</button>
      <button class="statusBtn" data-review-status="existing">Existing</button>
      <button class="statusBtn" data-review-status="partial">Partial</button>
      <button class="statusBtn" data-review-status="missing">Missing</button>
      <button class="statusBtn" data-review-status="not_required">Not required</button>
    </div></div>
    <div class="modalGrid" style="margin-top:12px">
      <div style="grid-column:span 6"><label>Your evidence</label><textarea id="evidenceText" placeholder="Add resume evidence, project proof or performance evidence."></textarea></div>
      <div style="grid-column:span 6"><label>Your note</label><textarea id="reviewerNote" placeholder="Explain the correction or what needs further review."></textarea></div>
    </div>
    <div class="hr"></div>
    <button class="btn" id="saveEvidence">Save Evidence & Status</button>
  </div>
</div>
<div id="toast" class="toast"></div>
<script type="application/json" id="sharePayload"><?=$pj?></script>

<script>
let currentPayload=null,currentRequirement=null,evidenceScrollY=0;
const shareToken=<?=json_encode($shareToken)?>;
const chartInstances={},analyticsCharts={};
const groupLabels={skills:'Skills',experience:'Experience',projects:'Projects',certificates:'Certifications'};
const groupDescriptions={
  skills:'Technical, functional and behavioural capabilities demanded by the target role.',
  experience:'Repeated delivery, leadership and domain-experience expectations.',
  projects:'Portfolio and production-grade project evidence expected by employers.',
  certificates:'Certifications and formal credentials requested in matching job descriptions.'
};
const $id=x=>document.getElementById(x);
const esc=s=>String(s??'').replace(/[&<>'\"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','\"':'&quot;'}[c]));
const finalStatus=r=>r.review_status||r.ai_status||r.auto_status;
function toast(m){const t=$id('toast');t.textContent=m;t.style.display='block';clearTimeout(window.toastTimer);window.toastTimer=setTimeout(()=>t.style.display='none',3000)}
function setModal(open){document.body.classList.toggle('modalOpen',open)}
function chartColor(s){return s==='existing'?'#2563eb':s==='partial'?'#f59e0b':s==='missing'?'#ef4444':'#94a3b8'}
function render(){
  const p=currentPayload,c=p.candidate;
  $id('candidateTitle').textContent=c.full_name;
  $id('candidateSub').textContent=[c.current_role,c.target_role].filter(Boolean).join(' → ');
  $id('targetRole').value=c.target_role||'';
  $id('targetLocation').value=c.target_location||'';
  $id('candidateStage').value=c.stage||'';
  $id('candidateStatus').value=c.status||'';
  $id('overallReview').value=c.candidate_review_note||'';
  $id('matchPercent').textContent=p.match_percent+'%';
  $id('existingCount').textContent=p.counts.existing;
  $id('partialCount').textContent=p.counts.partial;
  $id('missingCount').textContent=p.counts.missing;
  renderGraphSections();renderAnalytics();renderSharedPlans();loadCompanyMatches();
}

function planCardHtml(p,index){
  const features=(p.features||[]).map(x=>`<li>${esc(x)}</li>`).join('');
  const b=p.price_breakdown||{};const breakdown=b.one_time_price?`<div class="planBreakdown">${Number(b.weekday_sessions||0)} sessions × ₹${Number(b.weekday_rate||0).toLocaleString('en-IN')} + ₹${Number(b.project_gross||0).toLocaleString('en-IN')} project cost − ${Number(b.discount_percent||0)}% duration saving</div>`:'';
  return `<article class="planRecommendation ${index===0?'best':''}"><div class="planRank">${index===0?'Best match':'Alternative'} · Rank #${Number(p.rank||index+1)}</div><div class="planName">${esc(p.name||'Preparation Plan')}</div><div class="planFit">${Math.round(Number(p.fit_score)||0)}% fit</div><div class="small">${esc(p.price_label||'')} · ${esc(p.duration||'')}</div><div class="planReason">${esc(p.reason||p.summary||'')}</div>${breakdown}<ul class="planFeatures">${features}</ul><a class="btn ${index===0?'':'ghost'}" href="${esc(p.url||'https://thetalentgrid.in/plans/')}" target="_blank" rel="noopener">View This Plan</a></article>`;
}
function renderSharedPlans(){
  const c=currentPayload?.candidate||{},rec=c.plan_recommendations||{},plans=Array.isArray(rec.plans)?rec.plans:[];
  const visible=Number(c.plan_display_enabled||0)===1&&plans.length>0;
  const section=$id('sharedPlansSection');if(!section)return;section.classList.toggle('hidden',!visible);if(!visible)return;
  const b=rec.basis||{};$id('sharedPlanBasis').textContent=`Best fit for ${rec.target_role||'the target role'} • Skills ${Math.round(Number(b.skills_match)||0)}% • Experience ${Math.round(Number(b.experience_match)||0)}% • Projects ${Math.round(Number(b.projects_match)||0)}% • Selection readiness ${Math.round(Number(b.selection_probability)||0)}%`;
  $id('sharedPlanCards').innerHTML=plans.slice(0,3).map(planCardHtml).join('');
}
function renderGraphSections(){
  const host=$id('graphSections'),openState={};
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
    section.innerHTML=`<summary><div class="graphSummaryLeft"><span class="chev"></span><div><h3>${groupLabels[group]}</h3><div class="small">${groupDescriptions[group]}</div></div></div><div class="graphMeta">${rows.length} requirements • ${existing} existing • ${partial} partial • ${missing} missing</div></summary><div class="graphBody"><div class="chartWrap"><div class="chartCanvasBox"><canvas id="${group}Chart"></canvas></div></div><div class="small chartHint">Click a bar to open evidence and status review.</div></div>`;
    host.appendChild(section);
    if(section.open)requestAnimationFrame(()=>renderGroupChart(group,rows));
    section.addEventListener('toggle',()=>{if(section.open)requestAnimationFrame(()=>renderGroupChart(group,currentPayload.groups[group]||[]))});
  });
}
function renderGroupChart(group,rows){
  const canvas=$id(group+'Chart');if(!canvas)return;
  if(chartInstances[group]){chartInstances[group].destroy();delete chartInstances[group]}
  const box=canvas.parentElement,wrap=box.parentElement;
  wrap.querySelectorAll('.emptyChart').forEach(x=>x.remove());
  if(!rows.length){canvas.style.display='none';const e=document.createElement('div');e.className='small emptyChart';e.style.padding='28px';e.textContent='No target-role requirements found in this category.';wrap.appendChild(e);return}
  canvas.style.display='block';const h=Math.max(300,rows.length*32+80);canvas.height=h;box.style.height=h+'px';
  chartInstances[group]=new Chart(canvas,{type:'bar',data:{labels:rows.map(r=>r.term),datasets:[{data:rows.map(r=>Number(r.weight)||1),backgroundColor:rows.map(r=>chartColor(finalStatus(r))),borderWidth:0,borderRadius:6,barThickness:18,maxBarThickness:20}]},options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,onHover:(e,els)=>e.native.target.style.cursor=els.length?'pointer':'default',onClick:(e,els)=>{if(els.length)openEvidenceModal(group,rows[els[0].index])},plugins:{legend:{display:false},tooltip:{callbacks:{title:i=>rows[i[0].dataIndex].term,label:x=>{const r=rows[x.dataIndex];return [`Status: ${finalStatus(r)}`,`Weighted score: ${Number(r.weight).toFixed(2)}`,`Job mentions: ${r.mentions}`,r.evidence?`Evidence: ${r.evidence}`:'Evidence: not recorded']}}}},scales:{x:{beginAtZero:true,title:{display:true,text:'Weighted demand score'}},y:{ticks:{autoSkip:false,font:{size:12}}}}}});
}
function openEvidenceModal(group,row){
  evidenceScrollY=window.scrollY;currentRequirement={group,row};const s=finalStatus(row);
  $id('evidenceRequirementId').value=row.id;$id('evidenceTerm').textContent=row.term;
  $id('evidenceMeta').textContent=`${groupLabels[group]} • Automatic: ${row.ai_status||row.auto_status}${row.ai_confidence!==null&&row.ai_confidence!==undefined?' ('+Math.round(Number(row.ai_confidence)*100)+'% confidence)':''} • Current: ${s} • Score ${Number(row.weight).toFixed(2)} • ${row.mentions} job mentions`;
  const d=$id('evidenceDescription');d.textContent=row.description||'';d.classList.toggle('hidden',!row.description);
  $id('evidenceText').value=row.evidence||'';$id('reviewerNote').value=row.reviewer_note||'';
  document.querySelectorAll('[data-review-status]').forEach(btn=>{const v=btn.dataset.reviewStatus;btn.className='statusBtn';if((row.review_status===null||row.review_status==='')&&v==='')btn.classList.add('active-auto');if(v&&v===s)btn.classList.add('active-'+v)});
  $id('evidenceModal').classList.remove('hidden');setModal(true);
}
function closeEvidenceModal(restore=true){$id('evidenceModal').classList.add('hidden');currentRequirement=null;setModal(false);if(restore)requestAnimationFrame(()=>window.scrollTo({top:evidenceScrollY,left:0,behavior:'auto'}))}
document.querySelectorAll('[data-review-status]').forEach(btn=>btn.onclick=()=>{document.querySelectorAll('[data-review-status]').forEach(x=>x.className='statusBtn');btn.classList.add(btn.dataset.reviewStatus?'active-'+btn.dataset.reviewStatus:'active-auto')});
$id('saveEvidence').onclick=async()=>{
  const selected=document.querySelector('[data-review-status].active-existing,[data-review-status].active-partial,[data-review-status].active-missing,[data-review-status].active-not_required,[data-review-status].active-auto');
  const fd=new FormData();fd.append('token',shareToken);fd.append('id',$id('evidenceRequirementId').value);fd.append('review_status',selected?selected.dataset.reviewStatus:'');fd.append('evidence',$id('evidenceText').value);fd.append('reviewer_note',$id('reviewerNote').value);
  $id('saveState').textContent='Saving…';
  try{const r=await fetch('?action=public_requirement_update',{method:'POST',body:fd});const j=await r.json();if(!r.ok||j.error)throw new Error(j.message||j.error||'Unable to save');currentPayload=j.payload;closeEvidenceModal(false);render();requestAnimationFrame(()=>window.scrollTo({top:evidenceScrollY,left:0,behavior:'auto'}));toast('Profile updated')}
  catch(e){toast(e.message)}
  finally{$id('saveState').textContent='Candidate Review'}
};
$id('closeEvidence').onclick=()=>closeEvidenceModal(true);
function destroyAnalytics(){Object.values(analyticsCharts).forEach(c=>{try{c.destroy()}catch(e){}});Object.keys(analyticsCharts).forEach(k=>delete analyticsCharts[k])}
function renderAnalytics(){
  if(!currentPayload.analytics||!currentPayload.analytics.descriptive)return;
  destroyAnalytics();
  const a=currentPayload.analytics;
  const d=a.descriptive;
  const s=d.status_counts||{existing:0,partial:0,missing:0,not_required:0};
  const groups=['skills','experience','projects','certificates'];

  analyticsCharts.status=new Chart($id('analyticsStatusChart'),{
    type:'doughnut',
    data:{
      labels:['Existing','Partial','Missing','Not required'],
      datasets:[{data:[s.existing,s.partial,s.missing,s.not_required],backgroundColor:['#2563eb','#f59e0b','#ef4444','#94a3b8']}]
    },
    options:{responsive:true,maintainAspectRatio:false}
  });

  analyticsCharts.category=new Chart($id('analyticsCategoryChart'),{
    type:'bar',
    data:{
      labels:['Skills','Experience','Projects','Certifications'],
      datasets:[{data:groups.map(g=>d.category_match?.[g]?.match||0),backgroundColor:'#2563eb',borderRadius:8}]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}
  });

  const gaps=((a.diagnostic&&a.diagnostic.critical_missing)||[]).slice(0,10);
  analyticsCharts.gap=new Chart($id('analyticsGapChart'),{
    type:'bar',
    data:{labels:gaps.map(x=>x.term),datasets:[{data:gaps.map(x=>x.impact),backgroundColor:'#ef4444',borderRadius:7}]},
    options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{beginAtZero:true},y:{ticks:{autoSkip:false}}}}
  });

  analyticsCharts.evidence=new Chart($id('analyticsEvidenceChart'),{
    type:'bar',
    data:{
      labels:['Weighted match','Evidence completeness','Review completeness'],
      datasets:[{data:[d.weighted_match||0,d.evidence_completeness||0,d.review_completeness||0],backgroundColor:['#2563eb','#7c3aed','#0ea5e9'],borderRadius:8}]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}
  });

  analyticsCharts.prediction=new Chart($id('analyticsPredictionChart'),{
    type:'bar',
    data:{
      labels:['Shortlist','Interview selection'],
      datasets:[{data:[a.predictive?.shortlist_probability||0,a.predictive?.interview_selection_probability||0],backgroundColor:['#2563eb','#7c3aed'],borderRadius:8}]
    },
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,max:100}}}
  });
  $id('predictionMethod').textContent=a.predictive?.method||'Prediction analytics are not available yet.';

  const actions=((a.prescriptive&&a.prescriptive.actions)||[]).slice(0,10);
  analyticsCharts.action=new Chart($id('analyticsActionChart'),{
    type:'bar',
    data:{labels:actions.map(x=>x.term),datasets:[{data:actions.map(x=>x.priority),backgroundColor:'#f59e0b',borderRadius:7}]},
    options:{
      indexAxis:'y',responsive:true,maintainAspectRatio:false,
      plugins:{legend:{display:false},tooltip:{callbacks:{afterLabel:x=>actions[x.dataIndex]?.action||''}}},
      scales:{x:{beginAtZero:true},y:{ticks:{autoSkip:false}}}
    }
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
$id('saveOverallReview').onclick=async()=>{
  const fd=new FormData();fd.append('token',shareToken);fd.append('review_note',$id('overallReview').value);$id('saveState').textContent='Saving…';
  try{const r=await fetch('?action=candidate_review_submit',{method:'POST',body:fd});const j=await r.json();if(!r.ok||j.error)throw new Error(j.message||j.error||'Unable to save');toast(j.message||'Review saved')}
  catch(e){toast(e.message)}
  finally{$id('saveState').textContent='Candidate Review'}
};

let companyJobs=[],companyChart=null;
function pct100(v){v=Number(v)||0;return v>0&&v<=1?v*100:Math.max(0,Math.min(100,v))}
function cmEndpoint(){return (typeof shareToken!=='undefined'&&shareToken)?`?action=public_company_matches&token=${encodeURIComponent(shareToken)}`:`?action=company_matches&candidate_id=${encodeURIComponent((typeof currentId!=='undefined'?currentId:0)||currentPayload?.candidate?.id||0)}`}
let companyMatchPoll=null;let companyHistory=[];function renderCompanyHistory(){const el=$id('cmHistory');if(!el)return;const selected=el.value;el.innerHTML='<option value="">Latest match</option>'+companyHistory.map(x=>`<option value="${Number(x.id)}">${esc((x.completed_at||x.requested_at||'').replace(' ',' · '))} · ${esc(x.status)} · ${Number(x.result_count||0)} jobs</option>`).join('');if(selected&&companyHistory.some(x=>String(x.id)===String(selected)))el.value=selected}async function loadCompanyMatches(force=false,runId=0){const sec=$id('companyMatchSection');if(!sec)return;const c=currentPayload?.candidate||{};const isPublic=typeof shareToken!=='undefined'&&shareToken;if(isPublic&&Number(c.company_match_display_enabled||0)!==1){sec.classList.add('hidden');return}sec.classList.remove('hidden');const qs=new URLSearchParams({start_date:$id('cmStart')?.value||'',end_date:$id('cmEnd')?.value||'',company:$id('cmCompany')?.value||'',location:$id('cmLocation')?.value||'',q:$id('cmSearch')?.value||''});if(force)qs.set('refresh','1');if(runId)qs.set('run_id',String(runId));try{const r=await fetch(cmEndpoint()+'&'+qs.toString(),{credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}});const raw=await r.text();let out=null;try{out=JSON.parse(raw)}catch(_){const isHtml=/^\s*<!doctype|^\s*<html/i.test(raw);if(r.status===401||isHtml)throw new Error('Your session expired. Refresh the page and sign in again.');throw new Error('The matching service returned an unreadable response. Please retry.')}if(!r.ok||!out.ok)throw new Error(out.message||out.error||'Unable to load matches.');companyHistory=Array.isArray(out.history)?out.history:[];renderCompanyHistory();if($id('cmRunStatus'))$id('cmRunStatus').textContent=out.refreshing?(out.quick?'Quick results · full scan running':'Matching queued'):(out.run?.completed_at?`Saved ${out.run.completed_at}`:'Saved results');companyJobs=Array.isArray(out.jobs)?out.jobs:[];if(companyJobs.length){renderCompanyList();showCompanyJob(0)}else{$id('cmList').innerHTML=`<div class="companyEmpty">${out.refreshing?'Preparing quick matches…':'No saved matches for this selection.'}</div>`;$id('cmDetail').innerHTML='<div class="companyEmpty">Quick results appear first. The complete scan continues in the background.</div>'}if(out.refreshing&&!runId){clearTimeout(companyMatchPoll);companyMatchPoll=setTimeout(()=>loadCompanyMatches(false),5000)}else clearTimeout(companyMatchPoll)}catch(e){clearTimeout(companyMatchPoll);$id('cmList').innerHTML=`<div class="companyEmpty">${esc(e.message)}</div>`;$id('cmDetail').innerHTML='<div class="companyEmpty">Previously saved history remains available.</div>'}}
function renderCompanyList(){const el=$id('cmList');if(!el)return;el.innerHTML=companyJobs.length?companyJobs.map((j,i)=>`<div class="companyRow ${i===0?'active':''}" data-cm-index="${i}"><div class="companyRowTop"><div><b>${esc(j.company||'Company')}</b><div>${esc(j.title||'Role')}</div></div><div class="companyScore">${Math.round(Number(j.overall_score)||0)}%</div></div><div class="companyMeta">${esc(j.location||'')}${j.salary?' • '+esc(j.salary):''}</div></div>`).join(''):'<div class="companyEmpty">No matching jobs found.</div>';el.querySelectorAll('.companyRow').forEach(x=>x.onclick=()=>{el.querySelectorAll('.companyRow').forEach(y=>y.classList.remove('active'));x.classList.add('active');showCompanyJob(Number(x.dataset.cmIndex))})}
function showCompanyJob(i){const j=companyJobs[i],el=$id('cmDetail');if(!j||!el)return;const gs=j.groups||{},cats=['skills','experience','projects','certificates'];const labels={skills:'Skills',experience:'Experience',projects:'Projects',certificates:'Certifications'};const action=(typeof shareToken!=='undefined'&&shareToken)?`?action=public_ats_resume_pdf&token=${encodeURIComponent(shareToken)}&job_id=${j.job_id}`:`?action=ats_resume_pdf&candidate_id=${(typeof currentId!=='undefined'?currentId:0)||currentPayload?.candidate?.id||0}&job_id=${j.job_id}`;el.innerHTML=`<div class="companyToolbar"><div><h3>${esc(j.company)} — ${esc(j.title)}</h3><div class="sub">${esc(j.location||'')}${j.date?' • '+esc(j.date):''}${j.salary?' • '+esc(j.salary):''}</div></div><div class="pills">${j.apply_url?`<a class="btn ghost" href="${esc(j.apply_url)}" target="_blank" rel="noopener">Apply</a>`:''}<a class="btn" href="${action}" target="_blank" rel="noopener">Download ATS Resume</a></div></div><div class="companyCategoryGrid">${cats.map(g=>`<div class="companyCategory"><span class="small">${labels[g]}</span><strong>${Math.round(Number(gs[g]?.score)||0)}%</strong><span class="small">${Number(gs[g]?.existing?.length||0)} existing • ${Number(gs[g]?.partial?.length||0)} partial • ${Number(gs[g]?.missing?.length||0)} missing</span></div>`).join('')}</div><div class="companyDetailChart"><canvas id="companyDetailChart"></canvas></div><div class="companyBreakdown" style="margin-top:12px"><div><b>Existing</b><ul>${cats.flatMap(g=>(gs[g]?.existing||[]).map(x=>`<li>${esc(x)}</li>`)).join('')||'<li>None</li>'}</ul></div><div><b>Partial</b><ul>${cats.flatMap(g=>(gs[g]?.partial||[]).map(x=>`<li>${esc(x)}</li>`)).join('')||'<li>None</li>'}</ul></div><div><b>Missing</b><ul>${cats.flatMap(g=>(gs[g]?.missing||[]).map(x=>`<li>${esc(x)}</li>`)).join('')||'<li>None</li>'}</ul></div></div>`;if(companyChart)companyChart.destroy();companyChart=new Chart($id('companyDetailChart'),{type:'bar',data:{labels:cats.map(g=>labels[g]),datasets:[{label:'Existing',data:cats.map(g=>gs[g]?.existing?.length||0),backgroundColor:'#2563eb'},{label:'Partial',data:cats.map(g=>gs[g]?.partial?.length||0),backgroundColor:'#f59e0b'},{label:'Missing',data:cats.map(g=>gs[g]?.missing?.length||0),backgroundColor:'#ef4444'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'top'}},scales:{x:{stacked:true},y:{stacked:true,beginAtZero:true,ticks:{precision:0}}}}})}
if($id('cmRefresh'))$id('cmRefresh').onclick=()=>{if($id('cmHistory'))$id('cmHistory').value='';loadCompanyMatches(true)};if($id('cmHistory'))$id('cmHistory').onchange=()=>loadCompanyMatches(false,Number($id('cmHistory').value||0));

window.addEventListener('resize',()=>{clearTimeout(window.resizeT);window.resizeT=setTimeout(()=>{Object.values(chartInstances).forEach(c=>c.resize());Object.values(analyticsCharts).forEach(c=>c.resize());if(typeof syncSidebarMetrics==='function')syncSidebarMetrics()},150)});
function showInitializationError(message){
  const main=document.querySelector('.workspaceShell');
  if(!main)return;
  main.innerHTML=`<div class="card"><h2>Unable to load candidate profile</h2><p class="sub">${String(message||'The shared profile data could not be initialized.')}</p><p class="small">Refresh the page once. When the problem continues, regenerate the share link from SkillSnap.</p></div>`;
}
function initializeSharedWorkspace(){
  try{
    const node=$id('sharePayload');
    if(!node)throw new Error('Shared profile payload was not found.');
    currentPayload=JSON.parse(node.textContent||'{}');
    if(!currentPayload||!currentPayload.candidate||!currentPayload.groups||!currentPayload.counts){
      throw new Error('Shared profile payload is incomplete.');
    }
    render();
  }catch(e){
    console.error('SkillSnap share initialization failed:',e);
    showInitializationError(e.message);
  }
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initializeSharedWorkspace);else initializeSharedWorkspace();
</script>
</body>
</html>
<?php exit; } ?>
