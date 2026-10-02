<?php
/** SkillSnap application entry point for public_html/skillsnap. */
require_once __DIR__ . '/app/bootstrap.php';
require_once __DIR__ . '/app/analysis.php';
require_once __DIR__ . '/app/planning_matching.php';
require_once __DIR__ . '/app/actions.php';

// Public shared profile or private admin workspace.
$shareToken=getv('share');
if($shareToken!==''){
  require __DIR__ . '/app/views/public.php';
  exit;
}
require __DIR__ . '/app/views/admin_head.php';
require __DIR__ . '/app/views/admin_script.php';
