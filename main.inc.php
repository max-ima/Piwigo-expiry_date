<?php
/*
Plugin Name: Expiry Date
Version: auto
Description: Set an expiry date on photos. This plugin adds a field to the edit photo page and allows you to set an expiry date. In the batch manager you can filter photos to see which ones are expiring soon and handle the appropriately.
Plugin URI:
Author: HWFord
Author URI: https://github.com/HWFord
Has Settings: true
*/

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

if (basename(dirname(__FILE__)) != 'expiry_date')
{
  add_event_handler('init', 'expiry_date_error');
  function expiry_date_error()
  {
    global $page;
    $page['errors'][] = 'Expiry date folder name is incorrect, uninstall the plugin and rename it to "expiry_date"';
  }
  return;
}

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+

global $prefixeTable;

define('EXPIRY_DATE_ID',      basename(dirname(__FILE__)));
define('EXPIRY_DATE_PATH' ,   PHPWG_PLUGINS_PATH . EXPIRY_DATE_ID . '/');
define('EXPIRY_DATE_DIR',     PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'expiry_date/');
define('EXPIRY_DATE_ADMIN',   get_root_url() . 'admin.php?page=plugin-expiry_date');

include_once(EXPIRY_DATE_PATH.'include/admin_events.inc.php');

// +-----------------------------------------------------------------------+
// | Add event handlers                                                    |
// +-----------------------------------------------------------------------+

// init the plugin
add_event_handler('init', 'expiry_date_init');

/**
 * plugin initialization
 *   - unserialize configuration
 *   - load language
 */
function expiry_date_init()
{
  global $conf;

  // prepare plugin configuration
  $conf['expiry_date'] = safe_unserialize($conf['expiry_date']);

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  //set time between checks for expiring photos
  $conf['expiry_date_check_period'] = 24*60*60;

  $check_expiration_date = false;
  if (!isset($conf['expd_action_taken'])){
    $check_expiration_date = true;
  }
  else
  {
    $conf['expiry_date_action_take'] = safe_unserialize($conf['expd_action_taken']);
  
    $datetime1 = new DateTime($conf['expiry_date_action_take'] );
    $datetime2 = new DateTime($dbnow);
    $interval = $datetime1->diff($datetime2);
    $interval = $interval->format("%r%a");

    echo('<pre>');print_r($interval);echo('</pre>');

    if ($interval < $conf['expiry_date_check_period'])
    {
      $check_expiration_date = true;
    }

  }

  if ($check_expiration_date){
    load_language('plugin.lang', EXPIRY_DATE_PATH);

    expiry_date_init_actions();
  }
}

/**
 * Take action on expired photos
 */
function expiry_date_init_actions()
{
  global $conf;

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  //Select all images with an expiry date before now (=expired)
  $query = '
SELECT id, file
  FROM '.IMAGES_TABLE.'
  WHERE expiry_date <= NOW()
;';

  $images = query2array($query, 'id', 'file');
  $image_ids = array_keys($images);

  //check if there is expiring photos
  if (empty($image_ids))
  {
    return;
  }

  if (isset($conf['expiry_date']['expd_notify']))
  {
    //see what users downloaded which photo
    $query = '
SELECT user_id, image_id
  FROM '.HISTORY_TABLE.'
  WHERE image_id IN ('.implode(',',$image_ids).')
    AND image_type = \'high\'
;';
  
    $history_lines = query2array($query);
    $user_history = array();
  
    foreach ($history_lines as $history_line)
    {
      @$user_history[ $history_line['user_id'] ][ $history_line['image_id'] ]++;
    }
  
    $user_ids = array_keys($user_history);
  
    $query = '
SELECT 
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['email'].' AS email
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',',$user_ids).')
;';
  
    $users = query2array($query, 'id', 'email');
  }

  include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
  include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

  //set email content to notify admins expiriation action has taken place
  $subject = "Expiry date, action has been taken";
  $content = "These images have reached expiration: ".implode(', ',$images);
  

  if ('delete' == $conf['expiry_date']['expd_action'])
  {
    $content.= " "." Therefore these images have been deleted";
    //notify admins that expired photos have been deleted
    pwg_mail_notification_admins($subject, $content, false);

    //Delete expired photos
    delete_elements($image_ids, true);
  }
  else if ('archive' == $conf['expiry_date']['expd_action'] and isset($conf['expiry_date']['expd_archive_album']) )
  {
    $content.= " "." Therefore these images have been archived in album #".$conf['expiry_date']['expd_archive_album'];
    //notify admins that expired photos have been moved
    pwg_mail_notification_admins($subject, $content, false);

    //Move expired images
    move_images_to_categories($image_ids, array($conf['expiry_date']['expd_archive_album']));
    
    //remove expiry date so action is not done again, addd archive date
    $datas = array();

    foreach ($image_ids as $image_id)
    {
      $datas[] = array(
        'id' => $image_id,
        'expiry_date' => null,
        'expd_archive_date' => $dbnow,
        );
    }

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update' => array('expiry_date', 'expd_archive_date'),
      ),
      $datas
    );
  }
  else
  {
    $content.= " "." No action was taken on these images.";
    //notify admins that expired photos have been deleted
    pwg_mail_notification_admins($subject, $content, false);

    //remove expiry date so action is not done again, addd archive date
    $datas = array();

    foreach ($image_ids as $image_id)
    {
      $datas[] = array(
        'id' => $image_id,
        'expiry_date' => null,
        'expd_action_applied_on' => $dbnow,
        );
    }

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update' => array('expiry_date', 'expd_action_applied_on'),
      ),
      $datas
    );
  }

  if (!isset($conf['expiry_date']['expd_notify']))
  {
    return;
  }

  foreach ($user_history as $user_id => $user_image_ids)
  {
    if (!isset($users[$user_id]))
    {
      continue;
    }

    $image_info = "\n\n";
    foreach (array_keys($user_image_ids) as $user_image_id)
    {
      $image_info.= '* '.$images[$user_image_id]."\n";
    }
    $image_info .= "\n";

    pwg_mail(
      $users[$user_id],
      array(
        'subject' => 'You have expiring photos',
        'content' => 'You have recieved this email because you previously downloaded these photos: '.$image_info.'These photo have reached their expiry date.',
        'content_format' => 'text/plain',
      )
    );  
  } 
  
  //set time last action taken on photos
  $conf['expd_action_taken'] = array(
    'datetime' => $dbnow,
  );
  conf_update_param('expd_action_taken',  $conf['expd_action_taken'], true);
}

/*
 * event functions in admin
 */
if (defined('IN_ADMIN'))
{
  // file containing all admin handlers functions
  $admin_file = EXPIRY_DATE_PATH . 'include/admin_events.inc.php';
  
  //edit photo page
  add_event_handler('loc_end_picture_modify', 'expd_loc_end_picture_modify', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  //update photo information
  add_event_handler('picture_modify_before_update', 'expd_picture_modify_before_update', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // add predefined in Batch Manager
  add_event_handler('get_batch_manager_prefilters', 'expd_get_batch_manager_prefilters', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // add html for different options
  add_event_handler('loc_end_element_set_global', 'expd_loc_end_element_set_global', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

   //register predefined filter options
  add_event_handler('batch_manager_register_filters', 'expd_batch_manager_register_filters', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  //perform predefined filter
  add_event_handler('batch_manager_perform_filters', 'expd_batch_manager_perform_filters', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file);

  // batch manager apply action
  add_event_handler('element_set_global_action', 'expd_element_set_global_action', 50, $admin_file);

  // batch manager display action
  add_event_handler('loc_begin_element_set_global', 'expd_element_set_global_add_action', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file); 

  //watch delete categories to check if archive album is deleted
  add_event_handler('delete_categories', 'expd_delete_categories', EVENT_HANDLER_PRIORITY_NEUTRAL, $admin_file); 

}

/**
 * Add expiry date to image page in gallery
 */

// Add information to the picture's description (The copyright's name)
include_once(dirname(__FILE__).'/image.php');

