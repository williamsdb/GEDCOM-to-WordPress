<?php
// WordPress invokes this only when the administrator deletes the plugin.
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }
delete_option('gfh_dataset');

delete_option('gfh_places');
