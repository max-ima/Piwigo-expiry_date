<?php
/*
Plugin Name: Expiry Date
Version: auto
Description: Set an expiry date on photos. Apply an automatic action when photos expire.
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

  load_language('plugin.lang', EXPIRY_DATE_PATH);

  // prepare plugin configuration
  $conf['expiry_date'] = safe_unserialize($conf['expiry_date']);

  // set time between checks for expiring photos (1 day by default)
  $conf['expiry_date_check_period'] = conf_get_param('expiry_date_check_period', 24*60*60);

  $check_expiration_date = false;
  if (isset($conf['expd_last_check']))
  {
    if (strtotime($conf['expd_last_check']) < strtotime($conf['expiry_date_check_period'].' seconds ago'))
    {
      $check_expiration_date = true;
    }
  }
  else
  {
    $check_expiration_date = true;
  }

  if ($check_expiration_date)
  {

    expiry_date_init_actions();
  
    //set time last action taken on photos
    conf_update_param('expd_last_check', date('c'));
  }
}

/**
 * Take action on expired photos
 */
function expiry_date_init_actions()
{
  global $conf, $user;

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

    if (!empty($user_ids))
    {
      $query = '
SELECT 
    '.$conf['user_fields']['id'].' AS id,
    '.$conf['user_fields']['email'].' AS email
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',',$user_ids).')
    AND `'.$conf['user_fields']['email'].'` IS NOT NULL
;';
      $email_of_user = query2array($query, 'id', 'email');

      if (count($email_of_user) > 0)
      {
        $query = '
SELECT
    user_id,
    language
  FROM '.USER_INFOS_TABLE.'
  WHERE user_id IN ('.implode(',', $user_ids).')
;';
        $language_of_user = query2array($query, 'user_id', 'language');
      }
    }
  }

  include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
  include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');

  //set email content to notify admins expiration action has taken place
  $subject = get_l10n_args("Expiry date, action has been taken");
  $keyargs_content = array(
    get_l10n_args("These images have reached expiration: %s", implode(', ',$images)),
  );

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  if ('delete' == $conf['expiry_date']['expd_action'])
  {
    array_push(
      $keyargs_content, array(
        get_l10n_args('', ''),
        get_l10n_args("Therefore these images have been deleted."),
      )
    );

    //Delete expired photos
    delete_elements($image_ids, true);

    // let's update activities to show it's a deletion on expiry date
    $query = '
SELECT
    *
  FROM '.ACTIVITY_TABLE.'
  WHERE action = \'delete\'
    AND object = \'photo\'
    AND object_id IN ('.implode(',', $image_ids).')
;';
    $activities = query2array($query);
    $updates = array();
    foreach ($activities as $activity)
    {
      $details = unserialize($activity['details']);
      $details['script'] = 'expiry_date';

      $updates[] = array(
        'activity_id' => $activity['activity_id'],
        'details' => serialize($details),
      );
    }

    if (count($updates) > 0)
    {
      mass_updates(
        ACTIVITY_TABLE,
        array(
          'primary' => array('activity_id'),
          'update' => array('details'),
        ),
        $updates
      );
    }
  }
  else if ('archive' == $conf['expiry_date']['expd_action'] and isset($conf['expiry_date']['expd_archive_album']) )
  {
    $cat_info = get_cat_info($conf['expiry_date']['expd_archive_album']);
    $cat_names = array();
    foreach ($cat_info['upper_names'] as $upper_cat)
    {
      $cat_names[] = $upper_cat['name'];
    }
    $cat_fullname = implode($conf['level_separator'], $cat_names);

    array_push(
      $keyargs_content,
      array(
        get_l10n_args('', ''),
        get_l10n_args("Therefore these images have been archived in album %s", $cat_fullname),
      )
    );

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

    $random_key = generate_key(10);

    pwg_activity('photo', $image_ids, 'move', array('expiry_date_key'=>$random_key));

    // let's update activities to show it's a deletion on expiry date
    $query = '
SELECT
    *
  FROM '.ACTIVITY_TABLE.'
  WHERE action = \'move\'
    AND object = \'photo\'
    AND object_id IN ('.implode(',', $image_ids).')
;';
    $activities = query2array($query);
    $updates = array();
    foreach ($activities as $activity)
    {
      $details = unserialize($activity['details']);

      if (!isset($details['expiry_date_key']) or $details['expiry_date_key'] != $random_key)
      {
        continue;
      }
      $details['script'] = 'expiry_date';
      $details['archived_in'] = $conf['expiry_date']['expd_archive_album'];
      unset($details['expiry_date_key']);

      $updates[] = array(
        'activity_id' => $activity['activity_id'],
        'details' => serialize($details),
      );
    }

    if (count($updates) > 0)
    {
      mass_updates(
        ACTIVITY_TABLE,
        array(
          'primary' => array('activity_id'),
          'update' => array('details'),
        ),
        $updates
      );
    }
  }
  else
  {
    array_push(
      $keyargs_content,
      array(
        get_l10n_args('', ''),
        get_l10n_args("No action was taken on these images.")
      )
    );

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

  // notify admins
  $current_user_id = $user['id'];
  $user['id'] = -1; // make sure even the current user will get notified
  pwg_mail_notification_admins($subject, $keyargs_content, false);
  $user['id'] = $current_user_id;

  if (!isset($conf['expiry_date']['expd_notify']))
  {
    return;
  }

  foreach ($user_history as $user_id => $user_image_ids)
  {
    if (!isset($email_of_user[$user_id]))
    {
      continue;
    }

    $image_info = "\n\n";
    foreach (array_keys($user_image_ids) as $user_image_id)
    {
      $image_info.= '* '.$images[$user_image_id]."\n";
    }
    $image_info .= "\n";


    $keyargs_content = array(
      get_l10n_args("You have recieved this email because you previously downloaded these photos: %s", $image_info),
      get_l10n_args("These photo have reached their expiry date."),
    );

    $recipient_language = get_default_language();
    if (isset($language_of_user[$user_id]))
    {
      $recipient_language = $language_of_user[$user_id];
    }

    switch_lang_to($recipient_language);
    $subject = l10n('You have expiring photos');
    $content = l10n_args($keyargs_content);
    switch_lang_back();

    pwg_mail(
      $email_of_user[$user_id],
      array(
        'subject' => $subject,
        'content' => $content,
        'content_format' => 'text/plain',
      )
    );
  } 
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

