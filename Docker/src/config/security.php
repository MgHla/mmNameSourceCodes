<?php
// Admin token used to protect diagnostic tools (debug.php, clear_session.php,
// fix_permissions.php, setup_permissions.php).
//
// Access those scripts with:  /debug.php?key=<ADMIN_TOKEN>
//
// You may override this by setting the APP_ADMIN_TOKEN environment variable.
// Rotate this value by running:  openssl rand -hex 24
const ADMIN_TOKEN = 'c8c1e635a8f7bc831c129ff1d128a4d9403239f3f03a4364';
