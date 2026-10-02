<?php
/**
 * SkillSnap local configuration.
 * Copy this file to config.local.php inside public_html/skillsnap and fill in
 * values for the isolated SkillSnap test environment.
 * NEVER commit config.local.php.
 */
define('SKILLSNAP_DB_HOST', 'localhost');
define('SKILLSNAP_DB_NAME', 'YOUR_DATABASE_NAME');
define('SKILLSNAP_DB_USER', 'YOUR_DATABASE_USER');
define('SKILLSNAP_DB_PASS', 'YOUR_DATABASE_PASSWORD');

define('SKILLSNAP_ADMIN_PASSWORD_HASH', '');
define('SKILLSNAP_ADMIN_PASSWORD', 'CHANGE_ME_FOR_TESTING');

define('SKILLSNAP_OPENAI_API_KEY', '');
define('SKILLSNAP_OPENAI_MODEL', 'gpt-4.1-mini');
define('SKILLSNAP_OPENAI_TIMEOUT', '60');
define('SKILLSNAP_OPENAI_REQUIREMENT_CHUNK', '120');

define('SKILLSNAP_UPLOAD_DIR', __DIR__ . '/uploads');
define('SKILLSNAP_PLANS_URL', 'https://thetalentgrid.in/plans/');
define('SKILLSNAP_EXTERNAL_BASE_URL', 'https://www.thetalentgrid.co.in');
