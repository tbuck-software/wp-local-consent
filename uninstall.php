<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}
delete_option('local_consent_settings');
