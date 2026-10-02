<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>SkillSnap — Resume Gap Analysis</title>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.10/css/jquery.dataTables.min.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.10/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<style>
:root{
  --bg:#fff;--panel:#fff;--mut:#5b6472;--fg:#0b1220;--line:#e7eaf0;
  --shadow:0 6px 18px rgba(16,24,40,.08);--acc:#2563eb;--acc2:#7c3aed;
  --existing:#2563eb;--partial:#f59e0b;--missing:#ef4444;--neutral:#94a3b8;--pill:#f6f7fb;
  --sidebar-width:390px
}
*{box-sizing:border-box}
html,body{margin:0;min-height:100%;background:var(--bg);color:var(--fg);font:14px/1.55 system-ui,-apple-system,Segoe UI,Arial}
body.modalOpen{overflow:hidden}
a{color:var(--acc);text-decoration:none}
button,input,select,textarea{font:inherit}
h1,h2,h3{margin:0}
.small,.sub,label{font-size:12px;color:var(--mut)}
.hidden{display:none!important}

.stickyBar{
  position:sticky;top:0;z-index:80;background:rgba(255,255,255,.95);
  backdrop-filter:saturate(160%) blur(8px);border-bottom:1px solid var(--line)
}
.stickyInner{
  max-width:1600px;margin:auto;padding:11px 18px;display:flex;
  justify-content:space-between;align-items:center;gap:12px
}
.brand{display:flex;align-items:center;gap:10px}
.dot{width:10px;height:10px;border-radius:50%;background:var(--existing);box-shadow:0 0 0 4px rgba(37,99,235,.12)}
.pills{display:flex;gap:8px;flex-wrap:wrap;align-items:center}
.pill,.badge{background:var(--pill);border:1px solid var(--line);border-radius:999px;padding:7px 10px;font-size:12px}

.appShell{max-width:1600px;margin:auto;padding:18px;display:grid;grid-template-columns:minmax(310px,var(--sidebar-width)) minmax(0,1fr);gap:14px;align-items:start}
.sidebar{min-width:0;min-height:0}
.sidebarInner{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px;box-shadow:var(--shadow);min-height:0}
.sidebarContent{min-height:0}
.candidateTableWrap{min-height:180px;overflow:auto;overscroll-behavior:contain;scrollbar-gutter:stable;touch-action:pan-y;max-width:100%}
.candidateTableWrap .dataTables_wrapper{min-width:0}
.candidateTableWrap::-webkit-scrollbar{width:10px;height:10px}.candidateTableWrap::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:999px;border:2px solid #fff}.candidateTableWrap::-webkit-scrollbar-track{background:#f8fafc}
body.sidebarDocked .sidebar{
  position:fixed;left:18px;top:var(--sidebar-top,86px);width:var(--sidebar-width);height:calc(100dvh - var(--sidebar-top,86px) - 18px);z-index:45;min-height:320px
}
body.sidebarDocked .sidebarInner{height:100%;display:flex;flex-direction:column;overflow:hidden;min-height:0}
body.sidebarDocked .sidebarContent{display:flex;flex:1;flex-direction:column;min-height:0;overflow:hidden}
body.sidebarDocked .candidateTableWrap{flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:auto}
body.sidebarDocked .mainContent{grid-column:2}
body.sidebarCollapsed .appShell{grid-template-columns:68px minmax(0,1fr)}
body.sidebarCollapsed .sidebar{width:50px!important}
body.sidebarCollapsed .sidebarInner{padding:8px;height:auto}
body.sidebarCollapsed .sidebarContent{display:none}
body.sidebarCollapsed .dockControls{justify-content:center}
body.sidebarCollapsed .mainContent{grid-column:2}

.grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
.card,.kpi{background:#fff;border:1px solid var(--line);border-radius:16px;padding:14px;box-shadow:var(--shadow)}
.kpi .v{font-size:28px;font-weight:900}
.hr{height:1px;background:var(--line);margin:12px 0}
.sectionTitle{display:flex;justify-content:space-between;align-items:center;gap:10px}
.dockControls{display:flex;gap:7px;align-items:center}

input,select,textarea{
  width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:12px;
  background:#fff;color:var(--fg);outline:none
}
textarea{min-height:100px;resize:vertical}
input:focus,select:focus,textarea:focus{border-color:#b7c4ff;box-shadow:0 0 0 4px rgba(37,99,235,.12)}
.btn{
  background:var(--acc);border:0;color:#fff;padding:10px 14px;border-radius:12px;
  font-weight:800;cursor:pointer;white-space:nowrap
}
.btn.secondary{background:#0b1220}.btn.ghost{background:#fff;color:var(--fg);border:1px solid var(--line)}.btn.danger{background:var(--missing)}
.iconBtn{
  width:38px;height:38px;border-radius:11px;border:1px solid var(--line);
  background:#fff;color:var(--fg);cursor:pointer;font-size:17px;font-weight:900
}
.iconBtn.active{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}
.candidateRow{cursor:pointer}.candidateRow:hover{background:#f8fafc}
table.dataTable{width:100%!important}
table.dataTable thead th{background:#f8fafc;border-bottom:1px solid var(--line)}
.dataTables_wrapper .dataTables_paginate .paginate_button{border-radius:9px!important}

.legend{display:flex;gap:14px;flex-wrap:wrap;margin-top:8px}
.legendDot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:5px}
.blue{background:var(--existing)}.amber{background:var(--partial)}.red{background:var(--missing)}.grey{background:var(--neutral)}

.graphSection{
  border:1px solid var(--line);border-radius:16px;background:#fff;box-shadow:var(--shadow);
  overflow:hidden;margin-top:12px
}
.graphSection summary{
  list-style:none;cursor:pointer;padding:14px;display:flex;align-items:center;
  justify-content:space-between;gap:10px;background:#fff
}
.graphSection summary::-webkit-details-marker{display:none}
.graphSummaryLeft{display:flex;align-items:center;gap:10px;min-width:0}
.chev{width:10px;height:10px;border-right:2px solid #94a3b8;border-bottom:2px solid #94a3b8;transform:rotate(45deg);transition:.2s}
.graphSection[open] .chev{transform:rotate(-135deg)}
.graphBody{border-top:1px solid var(--line);padding:14px}
.chartWrap{
  overflow:auto;border:1px solid var(--line);border-radius:14px;background:#fff;
  max-height:720px;min-height:160px;position:relative
}
.chartCanvasBox{min-width:900px;padding:10px 14px 10px 4px}
.chartHint{margin-top:8px}
.graphMeta{font-size:12px;color:var(--mut);text-align:right}

.statusBadge{display:inline-flex;align-items:center;border-radius:999px;padding:5px 9px;font-size:12px;font-weight:800}
.statusBadge.existing{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}
.statusBadge.partial{background:#fffbeb;color:#92400e;border:1px solid #fde68a}
.statusBadge.missing{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}
.statusBadge.not_required{background:#f3f4f6;color:#4b5563;border:1px solid #e5e7eb}

.modal{
  position:fixed;inset:0;z-index:999;background:rgba(15,23,42,.52);
  display:flex;align-items:center;justify-content:center;padding:18px
}
.modalBox{
  width:min(850px,100%);max-height:92vh;overflow:auto;background:#fff;
  border-radius:18px;padding:18px;box-shadow:0 30px 70px rgba(15,23,42,.28)
}
.modalGrid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:12px}
.statusButtons{display:flex;gap:7px;flex-wrap:wrap}
.statusBtn{padding:9px 12px;border:1px solid var(--line);background:#fff;border-radius:10px;cursor:pointer;font-weight:800}
.statusBtn.active-existing{background:#dbeafe;border-color:#93c5fd;color:#1d4ed8}
.statusBtn.active-partial{background:#fef3c7;border-color:#fcd34d;color:#92400e}
.statusBtn.active-missing{background:#fee2e2;border-color:#fca5a5;color:#991b1b}
.statusBtn.active-auto{background:#f1f5f9;border-color:#cbd5e1;color:#334155}
.evidenceDescription{padding:12px;border-radius:12px;background:#f8fafc;border:1px solid var(--line);color:#334155}
.toast{
  position:fixed;right:18px;bottom:18px;z-index:2000;background:#0b1220;
  color:#fff;padding:12px 16px;border-radius:12px;display:none;max-width:min(700px,calc(100vw - 36px))
}


.perceptionSection{min-width:0}.visualGrid>*{min-width:0}.perceptionIntro{overflow:hidden}.scoreStrip{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:10px;margin-top:14px}.scoreTile{border:1px solid var(--line);border-radius:14px;padding:12px;background:#f8fafc;min-width:0}.scoreTile .scoreLabel{font-size:11px;color:var(--mut);line-height:1.3}.scoreTile .scoreValue{font-size:24px;font-weight:900;margin-top:3px}.scoreMeter{height:6px;border-radius:999px;background:#e2e8f0;overflow:hidden;margin-top:8px}.scoreMeter>span{display:block;height:100%;border-radius:inherit;background:var(--acc)}.insightCard{min-width:0;overflow:hidden}.insightHeading{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:10px}.insightTag{font-size:11px;font-weight:800;border-radius:999px;padding:5px 9px;white-space:nowrap}.acceptTag{background:#eff6ff;color:#1d4ed8}.rejectTag{background:#fef2f2;color:#b91c1c}.layoutTag{background:#fffbeb;color:#92400e}.personaTag{background:#f5f3ff;color:#6d28d9}.visualChartBox{height:390px;min-height:300px;position:relative;width:100%;overflow:hidden}.findingList{display:grid;gap:8px;margin-top:12px}.findingItem{border:1px solid var(--line);border-radius:12px;padding:11px;background:#fff}.findingTop{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.findingTitle{font-weight:850;overflow-wrap:anywhere}.findingScore{font-weight:900;white-space:nowrap}.findingDetail{color:#334155;margin-top:5px;overflow-wrap:anywhere}.findingEvidence{font-size:12px;color:var(--mut);margin-top:6px;padding-left:9px;border-left:3px solid #dbeafe;overflow-wrap:anywhere}.reportSummary{margin-top:8px;color:#334155;max-width:1100px}.personaGrid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.personaCard{border:1px solid var(--line);border-radius:14px;padding:14px;background:#fff;min-width:0}.personaHead{display:flex;justify-content:space-between;align-items:flex-start;gap:10px}.personaName{font-weight:900;font-size:16px}.personaLens{font-size:12px;color:var(--mut);margin-top:2px}.personaText{margin-top:10px;color:#334155}.probabilityRow{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}.probBox{border-radius:12px;padding:10px;background:#f8fafc}.probBox strong{display:block;font-size:20px}.probBar{height:7px;background:#e2e8f0;border-radius:999px;overflow:hidden;margin-top:6px}.probBar span{display:block;height:100%}.acceptBar{background:#2563eb}.rejectBar{background:#ef4444}.pointColumns{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:12px}.pointGroup{font-size:12px}.pointGroup b{display:block;margin-bottom:4px}.pointGroup ul{margin:0;padding-left:18px}.pointGroup li{margin:3px 0;overflow-wrap:anywhere}

@media(max-width:1100px){
  .appShell{grid-template-columns:1fr}
  .sidebar,.mainContent{grid-column:1!important}
  body.sidebarDocked .sidebar{position:static;width:auto;height:auto}
  body.sidebarDocked .sidebarInner{height:auto;overflow:visible}
  body.sidebarDocked .sidebarContent{display:block;overflow:visible}
  body.sidebarDocked .candidateTableWrap{max-height:55vh;overflow:auto}
  body.sidebarCollapsed .appShell{grid-template-columns:1fr}
  body.sidebarCollapsed .sidebar{width:auto!important}
  body.sidebarCollapsed .sidebarContent{display:none}
  .grid>*{grid-column:span 12!important}
  .scoreStrip{grid-template-columns:repeat(3,minmax(0,1fr))}
  .personaGrid{grid-template-columns:1fr}
  .visualChartBox{height:340px}
}
@media(max-width:700px){
  .stickyInner{align-items:flex-start;flex-direction:column}
  .appShell{padding:10px}
  .chartCanvasBox{min-width:760px}
  .modalGrid>*{grid-column:span 12!important}
  .scoreStrip{grid-template-columns:repeat(2,minmax(0,1fr))}
  .pointColumns,.probabilityRow{grid-template-columns:1fr}
  .visualChartBox{height:320px}
}

.companyMatchSection{margin-top:12px}.companyFilters{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px}.companyFilters>div{grid-column:span 3}.companyFilters .wide{grid-column:span 6}.companyToolbar{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}.companyMatchGrid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:12px;margin-top:12px}.companyList{max-height:620px;overflow:auto;border:1px solid var(--line);border-radius:14px}.companyRow{padding:13px;border-bottom:1px solid var(--line);cursor:pointer;background:#fff}.companyRow:last-child{border-bottom:0}.companyRow:hover,.companyRow.active{background:#eff6ff}.companyRowTop{display:flex;justify-content:space-between;gap:8px}.companyScore{font-size:18px;font-weight:950;color:var(--acc)}.companyMeta{font-size:12px;color:var(--mut);margin-top:4px}.companyDetail{border:1px solid var(--line);border-radius:14px;padding:14px;min-width:0}.companyCategoryGrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:12px 0}.companyCategory{border:1px solid var(--line);border-radius:12px;padding:10px}.companyCategory strong{display:block;font-size:20px}.companyBreakdown{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.companyBreakdown>div{border-radius:12px;padding:10px;background:#f8fafc;min-width:0}.companyBreakdown ul{margin:7px 0 0 18px;padding:0;max-height:190px;overflow:auto}.companyDetailChart{height:300px}.companyEmpty{padding:24px;text-align:center;color:var(--mut)}
@media(max-width:900px){.companyMatchGrid{grid-template-columns:1fr}.companyFilters>div,.companyFilters .wide{grid-column:span 12}.companyCategoryGrid{grid-template-columns:repeat(2,minmax(0,1fr))}.companyBreakdown{grid-template-columns:1fr}.companyList{max-height:420px}}
</style>
</head>
<body>

<div class="stickyBar">
  <div class="stickyInner">
    <div class="brand">
      <span class="dot"></span>
      <div><div style="font-weight:900">SkillSnap</div><div class="small">Resume evidence → target-role gap → shortlist and interview action.</div></div>
    </div>
    <div class="pills">
      <span class="pill" id="saveState">Ready</span>
      <a class="btn ghost" href="?action=admin_logout">Sign out</a><button class="btn" id="newCandidate">+ New Candidate</button>
    </div>
  </div>
</div>

<div class="appShell">
  <aside class="sidebar">
    <div class="sidebarInner">
      <div class="dockControls">
        <button class="iconBtn active" id="dockSidebar" title="Dock or undock candidate panel">⌖</button>
        <button class="iconBtn" id="collapseSidebar" title="Collapse or expand candidate panel">☰</button>
      </div>

      <div class="sidebarContent">
        <div class="sectionTitle" style="margin-top:10px">
          <div><h1>Candidates</h1><div class="sub">Candidate workspace and progress overview.</div></div>
        </div>
        <div class="hr"></div>
        <div class="grid">
          <div style="grid-column:span 8"><input id="search" placeholder="Search name, email, phone or role"></div>
          <div style="grid-column:span 4"><select id="stageFilter"><option value="">All stages</option><option>Lead</option><option>Assessment</option><option>Gap Analysis</option><option>Interview Prep</option><option>Interviewing</option><option>Selected</option><option>On Hold</option></select></div>
        </div>
        <div class="candidateTableWrap" style="margin-top:12px">
          <table id="candidateTable" class="display" style="width:100%">
            <thead><tr><th>Candidate</th><th>Target</th><th>Stage</th></tr></thead><tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </aside>

  <main class="mainContent">
    <div id="emptyState" class="card">
      <h2>Select or create a candidate</h2>
      <p class="sub">Upload a resume, select a target role, and compare the candidate against current market requirements.</p>
    </div>

    <div id="workspace" class="hidden">
      <div class="card">
        <div class="sectionTitle">
          <div><h1 id="candidateTitle">Candidate</h1><div class="sub" id="candidateSub"></div></div>
          <div class="pills"><button class="btn ghost" id="shareProfile">Share Profile</button><button class="btn ghost hidden" id="copyShareLink">Copy Link</button><button class="btn ghost hidden" id="disableShare">Disable Share</button><button class="btn ghost" id="editCandidate">Edit</button><button class="btn danger" id="deleteCandidate">Delete</button></div>
        </div>
        <div class="hr"></div>

        <div class="grid">
          <div style="grid-column:span 4"><label>Target role</label><input id="targetRole" list="roleList" placeholder="e.g. Generative AI Engineer"><datalist id="roleList"></datalist></div>
          <div style="grid-column:span 3"><label>Target location</label><input id="targetLocation" placeholder="e.g. Bengaluru"></div>
          <div style="grid-column:span 3"><label>Stage</label><select id="candidateStage"><option>Lead</option><option>Assessment</option><option>Gap Analysis</option><option>Interview Prep</option><option>Interviewing</option><option>Selected</option><option>On Hold</option></select></div>
          <div style="grid-column:span 2;display:flex;align-items:end"><button class="btn" id="analyzeBtn" style="width:100%">Refresh Candidate View</button></div>
        </div>

        <div class="hr"></div>
        <div class="grid">
          <div style="grid-column:span 8">
            <label>Candidate resume</label>
            <div class="pills">
              <input id="resumeFile" type="file" accept=".pdf,.docx,.txt" style="max-width:440px">
              <button class="btn secondary" id="uploadResume">Add Resume</button>
              <a class="btn ghost hidden" id="downloadResume" href="#">Download Resume</a>
            </div>
            <div class="small" id="resumeMeta">No resume uploaded.</div>
          </div>
          <div style="grid-column:span 4"><label>Working notes</label><textarea id="workingNotes" placeholder="Blockers, mock feedback and next actions"></textarea></div>
        </div>
      </div>

      <div class="card" id="planRecommendationCard" style="margin-top:12px">
        <div class="sectionTitle">
          <div><h3>Recommended Preparation Plans</h3><div class="sub">Generated from resume evidence, role gaps and outcome readiness.</div></div>
          <div class="pills"><label class="planToggle"><input type="checkbox" id="planDisplayEnabled"><span>Show on shared profile</span></label><button class="btn ghost" id="refreshPlans">Refresh Plans</button></div>
        </div>
        <div id="adminPlanBasis" class="notice hidden" style="margin-top:12px"></div>
        <div id="adminPlanCards" class="planRecommendationGrid" style="margin-top:12px"></div>
      </div>


      <section id="companyMatchSection" class="companyMatchSection">
       <div class="card"><div class="companyToolbar"><div><h3>Matching Companies &amp; Jobs</h3><div class="sub">Current jobs ranked against the candidate evidence.</div></div><label class="planToggle"><input type="checkbox" id="companyMatchDisplayEnabled"><span>Show on shared profile</span></label></div>
       <div class="companyFilters" style="margin-top:12px"><div><label>Start date</label><input type="date" id="cmStart"></div><div><label>End date</label><input type="date" id="cmEnd"></div><div><label>Company</label><input id="cmCompany" placeholder="e.g., Infosys"></div><div><label>Location</label><input id="cmLocation" placeholder="e.g., Bengaluru"></div><div class="wide"><label>Search</label><input id="cmSearch" placeholder="Title / Company / Location"></div><div style="display:flex;align-items:end"><button class="btn" id="cmRefresh">Refresh matches</button></div><div class="wide"><label>Match history</label><select id="cmHistory"><option value="">Latest match</option></select></div><div style="display:flex;align-items:end"><span class="pill" id="cmRunStatus">Saved results</span></div></div>
       <div class="companyMatchGrid"><div class="companyList" id="cmList"></div><div class="companyDetail" id="cmDetail"><div class="companyEmpty">Select a company to review its role match.</div></div></div></div>
      </section>

      <div class="grid" style="margin-top:12px">
        <div style="grid-column:span 3" class="kpi"><div class="small">Weighted match</div><div class="v" id="matchPercent">0%</div></div>
        <div style="grid-column:span 3" class="kpi"><div class="small">Existing</div><div class="v" id="existingCount">0</div></div>
        <div style="grid-column:span 3" class="kpi"><div class="small">Partial</div><div class="v" id="partialCount">0</div></div>
        <div style="grid-column:span 3" class="kpi"><div class="small">Missing</div><div class="v" id="missingCount">0</div></div>
      </div>

      <div class="card" style="margin-top:12px">
        <div class="sectionTitle">
          <div><h3>Candidate Match Graphs</h3><div class="sub">Click any bar to review evidence or override its status.</div></div>
        </div>
        <div class="legend">
          <span><i class="legendDot blue"></i>Existing</span>
          <span><i class="legendDot amber"></i>Partial</span>
          <span><i class="legendDot red"></i>Missing</span>
          <span><i class="legendDot grey"></i>Not required</span>
        </div>
      </div>

      <div id="graphSections"></div>
<div class="card" style="margin-top:12px"><div class="sectionTitle"><div><h3>Candidate Decision Insights</h3><div class="sub">Evidence, presentation and reviewer perspectives combined into one candidate view.</div></div></div></div><div class="grid" style="margin-top:12px"><div style="grid-column:span 6" class="card"><h3>Profile Evidence</h3><div style="height:320px"><canvas id="analyticsStatusChart"></canvas></div></div><div style="grid-column:span 6" class="card"><h3>Role Readiness</h3><div style="height:320px"><canvas id="analyticsCategoryChart"></canvas></div></div><div style="grid-column:span 6" class="card"><h3>Highest-Impact Gaps</h3><div style="height:380px"><canvas id="analyticsGapChart"></canvas></div></div><div style="grid-column:span 6" class="card"><h3>Resume Strength Signals</h3><div style="height:320px"><canvas id="analyticsEvidenceChart"></canvas></div></div><div style="grid-column:span 6" class="card"><h3>Estimated Outcomes</h3><div style="height:320px"><canvas id="analyticsPredictionChart"></canvas></div><div class="small" id="predictionMethod"></div></div><div style="grid-column:span 6" class="card"><h3>Priority Actions</h3><div style="height:380px"><canvas id="analyticsActionChart"></canvas></div></div></div>
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
    </div>
  </main>
</div>

<div id="candidateModal" class="modal hidden">
  <div class="modalBox">
    <div class="sectionTitle"><h2 id="modalTitle">New Candidate</h2><button class="btn ghost" id="closeModal">Close</button></div>
    <div class="hr"></div>
    <input type="hidden" id="formId">
    <div class="modalGrid">
      <div style="grid-column:span 6"><label>Full name *</label><input id="formName"></div>
      <div style="grid-column:span 6"><label>Email</label><input id="formEmail" type="email"></div>
      <div style="grid-column:span 6"><label>Phone</label><input id="formPhone"></div>
      <div style="grid-column:span 6"><label>Current role</label><input id="formCurrentRole"></div>
      <div style="grid-column:span 6"><label>Target role</label><input id="formTargetRole" list="roleList"></div>
      <div style="grid-column:span 6"><label>Target location</label><input id="formTargetLocation"></div>
      <div style="grid-column:span 4"><label>Current CTC</label><input id="formCurrentCtc"></div>
      <div style="grid-column:span 4"><label>Expected CTC</label><input id="formExpectedCtc"></div>
      <div style="grid-column:span 4"><label>Notice period</label><input id="formNotice"></div>
      <div style="grid-column:span 6"><label>Stage</label><select id="formStage"><option>Lead</option><option>Assessment</option><option>Gap Analysis</option><option>Interview Prep</option><option>Interviewing</option><option>Selected</option><option>On Hold</option></select></div>
      <div style="grid-column:span 6"><label>Status</label><select id="formStatus"><option>Active</option><option>Inactive</option><option>Converted</option><option>Closed</option></select></div>
      <div style="grid-column:span 12"><label>Notes</label><textarea id="formNotes"></textarea></div>
    </div>
    <div class="hr"></div>
    <button class="btn" id="saveCandidate">Save Candidate</button>
  </div>
</div>

<div id="evidenceModal" class="modal hidden">
  <div class="modalBox">
    <div class="sectionTitle">
      <div>
        <h2 id="evidenceTerm">Requirement Evidence</h2>
        <div class="sub" id="evidenceMeta"></div>
      </div>
      <button class="btn ghost" id="closeEvidence">Close</button>
    </div>
    <div class="hr"></div>
    <input type="hidden" id="evidenceRequirementId">
    <div id="evidenceDescription" class="evidenceDescription hidden"></div>
    <div style="margin-top:12px">
      <label>Manual status</label>
      <div class="statusButtons">
        <button class="statusBtn" data-review-status="">Use automatic</button>
        <button class="statusBtn" data-review-status="existing">Existing</button>
        <button class="statusBtn" data-review-status="partial">Partial</button>
        <button class="statusBtn" data-review-status="missing">Missing</button>
      </div>
    </div>
    <div class="modalGrid" style="margin-top:12px">
      <div style="grid-column:span 6"><label>Resume or performance evidence</label><textarea id="evidenceText" placeholder="Quote resume evidence, mock-interview proof or reviewed performance."></textarea></div>
      <div style="grid-column:span 6"><label>Reviewer note</label><textarea id="reviewerNote" placeholder="Why was this status selected? What must improve next?"></textarea></div>
    </div>
    <div class="hr"></div>
    <button class="btn" id="saveEvidence">Save Evidence & Status</button>
  </div>
</div>

<div id="shareModal" class="modal hidden"><div class="modalBox"><div class="sectionTitle"><div><h2>Share Candidate Profile</h2><div class="sub">Generate a read-only review link for the candidate.</div></div><button class="btn ghost" id="closeShare">Close</button></div><div class="hr"></div><label>Share link</label><input id="shareUrl" readonly><div class="pills" style="margin-top:10px"><button class="btn" id="copyShareModal">Copy Link</button><button class="btn ghost" id="openShareLink">Open Review Page</button><button class="btn danger" id="disableShareModal">Disable Link</button></div><div class="small" style="margin-top:10px">Anyone with this URL can view the review profile until disabled. Email, phone, CTC, private notes and resume file are not shown.</div></div></div>
<div id="toast" class="toast"></div>

