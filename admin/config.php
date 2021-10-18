<?php
defined('EXPIRY_DATE_PATH') or die('Hacking attempt!');

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |Actions                                                                |
// +-----------------------------------------------------------------------

if (!empty($_POST))
{
  check_input_parameter('expd_actions', $_POST, false, '/^(nothing|delete|archive)$/');
  check_input_parameter('selected_category', $_POST, false, '/^[a-zA-Z\d_-]+$/');
  check_input_parameter('expd_notify', $_POST, false, '/^(notify|)$/');

  $conf['expiry_date'] = array(
    'expd_action' => $_POST['expd_actions'],
    'expd_archive_album' => $_POST['selected_category'],   
    'expd_notify' => isset($_POST["expd_notify"]) ? true : false,
  );
  
  if (null == $_POST['selected_category'] and 'archive' == $_POST['expd_actions'])
  {
    array_push($page['warnings'], l10n('You must select an album for archiving photos'));
  }
  else
  {
    conf_update_param('expiry_date',  $conf['expiry_date'], true);
    array_push($page['infos'], l10n('Expiry date configuration saved'));
  }
  
}

//Get default values for options from config
$template->assign('selectedAction', $conf['expiry_date']['expd_action']);
$template->assign('selected_category',array($conf['expiry_date']['expd_archive_album']));
$template->assign('notifyAction', $conf['expiry_date']['expd_notify']);


// +-----------------------------------------------------------------------+
// | template init                                                         |
// +-----------------------------------------------------------------------+

//assign value for select input
$template->assign(
  'expd_actions_options',
  array(
    'nothing' => 'Do nothing',
    'delete' => 'Delete expired photos',
    'archive' => 'Archive expired photos',
  ),
);

$template->assign(array(
  'CACHE_KEYS' => get_admin_client_cache_keys(array('categories')),
  ));


$template->set_filename('expiry_date_content', realpath(EXPIRY_DATE_PATH . 'admin/template/config.tpl'));
