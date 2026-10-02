<?php
if(!defined('SKILLSNAP_ROOT')) define('SKILLSNAP_ROOT', dirname(__DIR__));
/**
 * SkillSnap
 * Single-file PHP/MySQL application for:
 * - Candidate CRUD and search
 * - PDF/DOCX/TXT resume upload and text extraction
 * - Target-role requirement aggregation from skillsnap_job_details.parsed_json
 * - Requirement descriptions from skillsnap_job_details.skill_desc
 * - Automatic existing/missing matching with manual review overrides
 * - Real-time AJAX updates for evidence, status and notes
 *
 * Deployment note: configure SkillSnap using config.local.php or SKILLSNAP_* environment variables
 * and never commit real credentials to GitHub.
 */
ini_set('display_errors','0');
@set_time_limit(180);
ini_set('max_execution_time','180');
header('Cache-Control: no-store, max-age=0');

date_default_timezone_set('Asia/Kolkata');

/* Composer PDF parser fallback. Supports vendor in the current or parent directory. */
$composerAutoloadPaths = [
  SKILLSNAP_ROOT . '/vendor/autoload.php',
  dirname(SKILLSNAP_ROOT) . '/vendor/autoload.php'
];
foreach($composerAutoloadPaths as $composerAutoload){
  if(is_file($composerAutoload)){
    require_once $composerAutoload;
    break;
  }
}


/* SkillSnap configuration.
 * Keep real credentials in config.local.php (ignored by Git) or environment variables.
 * This application is intended to live at public_html/skillsnap.
 */
$localConfig = SKILLSNAP_ROOT . '/config.local.php';
if(is_file($localConfig)){ require_once $localConfig; }

function skillsnap_cfg($constant,$env,$default=''){
  if(defined($constant)) return constant($constant);
  $v=getenv($env);
  return ($v!==false && $v!=='') ? $v : $default;
}

$DB_HOST = skillsnap_cfg('SKILLSNAP_DB_HOST','SKILLSNAP_DB_HOST','localhost');
$DB_NAME = skillsnap_cfg('SKILLSNAP_DB_NAME','SKILLSNAP_DB_NAME','');
$DB_USER = skillsnap_cfg('SKILLSNAP_DB_USER','SKILLSNAP_DB_USER','');
$DB_PASS = skillsnap_cfg('SKILLSNAP_DB_PASS','SKILLSNAP_DB_PASS','');
$UPLOAD_DIR = skillsnap_cfg('SKILLSNAP_UPLOAD_DIR','SKILLSNAP_UPLOAD_DIR',SKILLSNAP_ROOT.'/uploads');
$MAX_UPLOAD = 10 * 1024 * 1024;

/* OpenAI configuration. Never commit an API key. */
$OPENAI_API_KEY = trim((string)skillsnap_cfg('SKILLSNAP_OPENAI_API_KEY','SKILLSNAP_OPENAI_API_KEY',''));
$ADMIN_PASSWORD_HASH = trim((string)skillsnap_cfg('SKILLSNAP_ADMIN_PASSWORD_HASH','SKILLSNAP_ADMIN_PASSWORD_HASH',''));
$ADMIN_PASSWORD = (string)skillsnap_cfg('SKILLSNAP_ADMIN_PASSWORD','SKILLSNAP_ADMIN_PASSWORD','');
$SESSION_NAME = 'skillsnap_admin';
$OPENAI_MODEL = trim((string)skillsnap_cfg('SKILLSNAP_OPENAI_MODEL','SKILLSNAP_OPENAI_MODEL','gpt-4.1-mini'));
$OPENAI_TIMEOUT = max(20, min(90, (int)skillsnap_cfg('SKILLSNAP_OPENAI_TIMEOUT','SKILLSNAP_OPENAI_TIMEOUT','60')));
$OPENAI_REQUIREMENT_CHUNK = max(20, min(150, (int)skillsnap_cfg('SKILLSNAP_OPENAI_REQUIREMENT_CHUNK','SKILLSNAP_OPENAI_REQUIREMENT_CHUNK','120')));
$SKILLSNAP_PLANS_URL = rtrim((string)skillsnap_cfg('SKILLSNAP_PLANS_URL','SKILLSNAP_PLANS_URL','https://thetalentgrid.in/plans/'),'/').'/';
$SKILLSNAP_EXTERNAL_BASE_URL = rtrim((string)skillsnap_cfg('SKILLSNAP_EXTERNAL_BASE_URL','SKILLSNAP_EXTERNAL_BASE_URL','https://www.thetalentgrid.co.in'),'/');

if($DB_NAME==='' || $DB_USER===''){
  http_response_code(500);
  exit('SkillSnap is not configured. Copy config.local.example.php to config.local.php and set database credentials.');
}



function db(){
  static $pdo=null;
  global $DB_HOST,$DB_NAME,$DB_USER,$DB_PASS;
  if($pdo) return $pdo;
  $pdo=new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",$DB_USER,$DB_PASS,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
  return $pdo;
}
function clean_buffers(){ while(ob_get_level()>0){ @ob_end_clean(); } }
function json_out($x,$status=200){ clean_buffers(); http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($x,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); exit; }
function h($x){ return htmlspecialchars((string)$x,ENT_QUOTES,'UTF-8'); }
function post($k,$d=''){ return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function getv($k,$d=''){ return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function intv($x,$d=0){ return is_numeric($x)?(int)$x:$d; }
function now_sql(){ return date('Y-m-d H:i:s'); }
function norm_space($s){ return trim(preg_replace('/\s+/u',' ',(string)$s)); }
function norm_key($s){
  $s=mb_strtolower(norm_space($s),'UTF-8');
  $s=preg_replace('/[^\pL\pN+#.\/ -]+/u',' ',$s);
  return trim(preg_replace('/\s+/u',' ',$s));
}

function is_https_request(){ return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') || (($_SERVER['HTTP_X_FORWARDED_PROTO']??'')==='https'); }
function start_admin_session(){
  global $SESSION_NAME;
  if(session_status()===PHP_SESSION_ACTIVE) return;
  session_name($SESSION_NAME);
  session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>is_https_request(),'httponly'=>true,'samesite'=>'Lax']);
  session_start();
}
function admin_password_ready(){ global $ADMIN_PASSWORD_HASH,$ADMIN_PASSWORD; return $ADMIN_PASSWORD_HASH!=='' || $ADMIN_PASSWORD!==''; }
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
function render_login_page($error=''){
  http_response_code($error?401:200);
  $safe=h($error);
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>SkillSnap</title><style>body{margin:0;background:#f5f7fb;color:#0f172a;font:15px/1.5 system-ui;display:grid;min-height:100vh;place-items:center}.box{width:min(420px,calc(100% - 32px));background:#fff;border:1px solid #e5e7eb;border-radius:20px;padding:28px;box-shadow:0 20px 50px rgba(15,23,42,.12)}h1{margin:0 0 6px}.sub{color:#64748b;margin-bottom:20px}label{display:block;font-size:12px;color:#64748b;margin-bottom:6px}input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #cbd5e1;border-radius:12px;font:inherit}button{width:100%;margin-top:12px;padding:12px 14px;border:0;border-radius:12px;background:#2563eb;color:#fff;font-weight:800;cursor:pointer}.err{background:#fff1f2;color:#be123c;padding:10px 12px;border-radius:10px;margin-bottom:12px}
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

.companyMatchSection{margin-top:12px}.companyFilters{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:10px}.companyFilters>div{grid-column:span 3}.companyFilters .wide{grid-column:span 6}.companyToolbar{display:flex;justify-content:space-between;gap:10px;align-items:center;flex-wrap:wrap}.companyMatchGrid{display:grid;grid-template-columns:minmax(280px,.8fr) minmax(0,1.2fr);gap:12px;margin-top:12px}.companyList{max-height:620px;overflow:auto;border:1px solid var(--line);border-radius:14px}.companyRow{padding:13px;border-bottom:1px solid var(--line);cursor:pointer;background:#fff}.companyRow:last-child{border-bottom:0}.companyRow:hover,.companyRow.active{background:#eff6ff}.companyRowTop{display:flex;justify-content:space-between;gap:8px}.companyScore{font-size:18px;font-weight:950;color:var(--acc)}.companyMeta{font-size:12px;color:var(--mut);margin-top:4px}.companyDetail{border:1px solid var(--line);border-radius:14px;padding:14px;min-width:0}.companyCategoryGrid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:8px;margin:12px 0}.companyCategory{border:1px solid var(--line);border-radius:12px;padding:10px}.companyCategory strong{display:block;font-size:20px}.companyBreakdown{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.companyBreakdown>div{border-radius:12px;padding:10px;background:#f8fafc;min-width:0}.companyBreakdown ul{margin:7px 0 0 18px;padding:0;max-height:190px;overflow:auto}.companyDetailChart{height:300px}.companyEmpty{padding:24px;text-align:center;color:var(--mut)} .companyMatchSection button:disabled{opacity:.6;cursor:wait}.companyFilters select{width:100%;padding:10px 12px;border:1px solid var(--line);border-radius:12px;background:#fff}
@media(max-width:900px){.companyMatchGrid{grid-template-columns:1fr}.companyFilters>div,.companyFilters .wide{grid-column:span 12}.companyCategoryGrid{grid-template-columns:repeat(2,minmax(0,1fr))}.companyBreakdown{grid-template-columns:1fr}.companyList{max-height:420px}}
</style></head><body><form class="box" method="post" action="?action=admin_login"><h1>SkillSnap</h1><div class="sub">Private workspace</div>'.($safe?'<div class="err">'.$safe.'</div>':'').'<label>Password</label><input type="password" name="password" autocomplete="current-password" required autofocus><button type="submit">Continue</button></form></body></html>';exit;
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
enforce_admin_access();
/* Release the PHP session lock immediately after authentication. Long AI or matching work must never serialize other browser requests. */
if(session_status()===PHP_SESSION_ACTIVE){ @session_write_close(); }

function ensure_schema(){
  $pdo=db();
  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_candidates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(80) NULL,
    current_role VARCHAR(255) NULL,
    target_role VARCHAR(255) NULL,
    target_location VARCHAR(255) NULL,
    current_ctc VARCHAR(100) NULL,
    expected_ctc VARCHAR(100) NULL,
    notice_period VARCHAR(100) NULL,
    stage VARCHAR(80) NOT NULL DEFAULT 'Lead',
    status VARCHAR(40) NOT NULL DEFAULT 'Active',
    notes MEDIUMTEXT NULL,
    resume_original_name VARCHAR(512) NULL,
    resume_stored_name VARCHAR(512) NULL,
    resume_mime VARCHAR(120) NULL,
    resume_text LONGTEXT NULL,
    requirements_json LONGTEXT NULL,
    analysis_version INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX idx_name(full_name), INDEX idx_email(email), INDEX idx_target(target_role), INDEX idx_stage(stage)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  /* Profile sharing fields for existing installations. */
  $candidateCols=[];
  foreach($pdo->query("SHOW COLUMNS FROM skillsnap_candidates") as $col){ $candidateCols[$col['Field']]=true; }
  if(!isset($candidateCols['share_token'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN share_token VARCHAR(96) NULL AFTER analysis_version");
  if(!isset($candidateCols['share_enabled'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN share_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER share_token");
  if(!isset($candidateCols['share_created_at'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN share_created_at DATETIME NULL AFTER share_enabled");
  if(!isset($candidateCols['candidate_review_note'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN candidate_review_note MEDIUMTEXT NULL AFTER share_created_at");
  if(!isset($candidateCols['candidate_reviewed_at'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN candidate_reviewed_at DATETIME NULL AFTER candidate_review_note");
  if(!isset($candidateCols['resume_analysis_json'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN resume_analysis_json LONGTEXT NULL AFTER resume_text");
  if(!isset($candidateCols['resume_analysis_provider'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN resume_analysis_provider VARCHAR(80) NULL AFTER resume_analysis_json");
  if(!isset($candidateCols['resume_analysis_model'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN resume_analysis_model VARCHAR(120) NULL AFTER resume_analysis_provider");
  if(!isset($candidateCols['resume_analysis_at'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN resume_analysis_at DATETIME NULL AFTER resume_analysis_model");
  if(!isset($candidateCols['resume_analysis_error'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN resume_analysis_error MEDIUMTEXT NULL AFTER resume_analysis_at");
  if(!isset($candidateCols['visual_analysis_json'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN visual_analysis_json LONGTEXT NULL AFTER resume_analysis_error");
  if(!isset($candidateCols['visual_analysis_model'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN visual_analysis_model VARCHAR(120) NULL AFTER visual_analysis_json");
  if(!isset($candidateCols['visual_analysis_at'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN visual_analysis_at DATETIME NULL AFTER visual_analysis_model");
  if(!isset($candidateCols['visual_analysis_error'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN visual_analysis_error MEDIUMTEXT NULL AFTER visual_analysis_at");
  if(!isset($candidateCols['plan_recommendations_json'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN plan_recommendations_json LONGTEXT NULL AFTER visual_analysis_error");
  if(!isset($candidateCols['plan_recommendations_at'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN plan_recommendations_at DATETIME NULL AFTER plan_recommendations_json");
  if(!isset($candidateCols['plan_display_enabled'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN plan_display_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER plan_recommendations_at");
  if(!isset($candidateCols['company_match_display_enabled'])) $pdo->exec("ALTER TABLE skillsnap_candidates ADD COLUMN company_match_display_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER plan_display_enabled");
  try{ $pdo->exec("CREATE UNIQUE INDEX uq_skillsnap_candidates_share_token ON skillsnap_candidates(share_token)"); }catch(Throwable $e){}

  /* Isolated job source for intern testing. database/skillsnap_schema.sql can clone the
     exact production job_details structure and data into this table. */
  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_job_details (
    ID BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    Title VARCHAR(512) NULL,
    Company VARCHAR(255) NULL,
    Location VARCHAR(255) NULL,
    parsed_json LONGTEXT NULL,
    skill_desc LONGTEXT NULL,
    analysed TINYINT(1) NOT NULL DEFAULT 0,
    apply_url TEXT NULL,
    created_at DATETIME NULL,
    INDEX idx_ss_job_title(Title), INDEX idx_ss_job_company(Company), INDEX idx_ss_job_analysed(analysed)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_candidate_requirements (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    candidate_id BIGINT UNSIGNED NOT NULL,
    group_name ENUM('skills','experience','projects','certificates') NOT NULL,
    term VARCHAR(700) NOT NULL,
    term_norm VARCHAR(700) NOT NULL,
    description MEDIUMTEXT NULL,
    weight DECIMAL(12,4) NOT NULL DEFAULT 1,
    mentions INT NOT NULL DEFAULT 1,
    auto_status ENUM('existing','missing') NOT NULL DEFAULT 'missing',
    review_status ENUM('existing','partial','missing','not_required') NULL,
    evidence MEDIUMTEXT NULL,
    reviewer_note MEDIUMTEXT NULL,
    reviewed_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_candidate_group_term(candidate_id,group_name,term_norm),
    INDEX idx_candidate(candidate_id), INDEX idx_group(group_name),
    CONSTRAINT fk_candidate_req FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $reqCols=[];
  foreach($pdo->query("SHOW COLUMNS FROM skillsnap_candidate_requirements") as $col){ $reqCols[$col['Field']]=true; }
  if(!isset($reqCols['ai_status'])) $pdo->exec("ALTER TABLE skillsnap_candidate_requirements ADD COLUMN ai_status ENUM('existing','partial','missing') NULL AFTER auto_status");
  if(!isset($reqCols['ai_confidence'])) $pdo->exec("ALTER TABLE skillsnap_candidate_requirements ADD COLUMN ai_confidence DECIMAL(5,4) NULL AFTER ai_status");
  if(!isset($reqCols['ai_reason'])) $pdo->exec("ALTER TABLE skillsnap_candidate_requirements ADD COLUMN ai_reason MEDIUMTEXT NULL AFTER ai_confidence");
  if(!isset($reqCols['ai_evidence'])) $pdo->exec("ALTER TABLE skillsnap_candidate_requirements ADD COLUMN ai_evidence MEDIUMTEXT NULL AFTER ai_reason");
  if(!isset($reqCols['ai_model'])) $pdo->exec("ALTER TABLE skillsnap_candidate_requirements ADD COLUMN ai_model VARCHAR(120) NULL AFTER ai_evidence");
  if(!isset($reqCols['ai_analyzed_at'])) $pdo->exec("ALTER TABLE skillsnap_candidate_requirements ADD COLUMN ai_analyzed_at DATETIME NULL AFTER ai_model");

  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_company_match_cache (
    candidate_id BIGINT UNSIGNED NOT NULL,
    filter_hash CHAR(64) NOT NULL,
    filters_json TEXT NOT NULL,
    source_version VARCHAR(120) NOT NULL DEFAULT '',
    status ENUM('pending','running','ready','failed') NOT NULL DEFAULT 'pending',
    result_json LONGTEXT NULL,
    error_text MEDIUMTEXT NULL,
    generated_at DATETIME NULL,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY(candidate_id,filter_hash),
    INDEX idx_match_cache_status(status,updated_at),
    CONSTRAINT fk_match_cache_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_company_match_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    candidate_id BIGINT UNSIGNED NOT NULL,
    filter_hash CHAR(64) NOT NULL,
    filters_json TEXT NOT NULL,
    source_version VARCHAR(120) NOT NULL DEFAULT '',
    status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending',
    priority INT NOT NULL DEFAULT 5,
    attempts INT NOT NULL DEFAULT 0,
    lock_token VARCHAR(80) NULL,
    locked_at DATETIME NULL,
    error_text MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    UNIQUE KEY uq_match_job(candidate_id,filter_hash),
    INDEX idx_match_job_claim(status,priority,id),
    CONSTRAINT fk_match_job_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_company_match_runs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, candidate_id BIGINT UNSIGNED NOT NULL, filter_hash CHAR(64) NOT NULL, filters_json TEXT NOT NULL, source_version VARCHAR(120) NOT NULL DEFAULT '',
    status ENUM('queued','running','ready','failed','superseded') NOT NULL DEFAULT 'queued', requested_by ENUM('automatic','admin','shared','cron') NOT NULL DEFAULT 'automatic',
    result_json LONGTEXT NULL, result_count INT NOT NULL DEFAULT 0, error_text MEDIUMTEXT NULL, requested_at DATETIME NOT NULL, started_at DATETIME NULL, completed_at DATETIME NULL,
    INDEX idx_match_run_candidate(candidate_id,id), INDEX idx_match_run_status(status,requested_at), CONSTRAINT fk_match_run_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_company_match_queue (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, run_id BIGINT UNSIGNED NOT NULL, candidate_id BIGINT UNSIGNED NOT NULL, priority INT NOT NULL DEFAULT 5,
    status ENUM('pending','running','done','failed') NOT NULL DEFAULT 'pending', attempts INT NOT NULL DEFAULT 0, lock_token VARCHAR(80) NULL, locked_at DATETIME NULL, available_at DATETIME NOT NULL,
    error_text MEDIUMTEXT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, UNIQUE KEY uq_match_queue_run(run_id), INDEX idx_match_queue_claim(status,available_at,priority,id),
    CONSTRAINT fk_match_queue_run FOREIGN KEY(run_id) REFERENCES skillsnap_company_match_runs(id) ON DELETE CASCADE, CONSTRAINT fk_match_queue_candidate FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

  $pdo->exec("CREATE TABLE IF NOT EXISTS skillsnap_activity (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    candidate_id BIGINT UNSIGNED NOT NULL,
    action_name VARCHAR(120) NOT NULL,
    detail MEDIUMTEXT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_candidate_time(candidate_id,created_at),
    CONSTRAINT fk_skillsnap_activity FOREIGN KEY(candidate_id) REFERENCES skillsnap_candidates(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
ensure_schema();

function log_activity($cid,$action,$detail=''){
  $st=db()->prepare('INSERT INTO skillsnap_activity(candidate_id,action_name,detail,created_at) VALUES(?,?,?,?)');
  $st->execute([$cid,$action,$detail,now_sql()]);
}
