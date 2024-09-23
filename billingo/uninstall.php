<?php

if (defined('WP_UNINSTALL_PLUGIN')) {
	delete_metadata('user', 0, 'billingo_notice_review_dismissed', '', true);
}
