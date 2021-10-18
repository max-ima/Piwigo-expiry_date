<?php

defined('EXPIRY_DATE_PATH') or die('Hacking attempt!');

global $template, $page, $conf;

include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');

define('EXPIRY_DATE_BASE_URL', get_root_url().'admin.php?page=plugin-expiry_date');

// get current tab
$page['tab'] = isset($_GET['tab']) ? $_GET['tab'] : $page['tab'] = 'config';

//tabsheet
$tabsheet = new tabsheet();
$tabsheet->set_id('expiry_date');

$tabsheet->add('config', l10n('Configuration'), EXPIRY_DATE_ADMIN . '-config');
$tabsheet->select($page['tab']);
$tabsheet->assign();

// include page
include(EXPIRY_DATE_PATH . 'admin/' . $page['tab'] . '.php');

// send page content
$template->assign_var_from_handle('ADMIN_CONTENT', 'expiry_date_content');

?>