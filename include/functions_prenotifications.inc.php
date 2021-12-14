<?php

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');
include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

/**
 * Send prenotifications to admins
 */
function send_prenotifications_admin()
{
  global $conf, $user, $prefixeTable;

  $notification_history = array();

  //Check if either admin prenotification is set
  if ('none' == $conf['expiry_date']['expd_notify_admin_before_option'])
  {
    return;
  }

  // select all images expiring in X days for admin prenotif
  $query = '
SELECT
    id,
    file,
    name,
    author,
    expiry_date
  FROM '.IMAGES_TABLE.'
  WHERE expiry_date BETWEEN ADDDATE(NOW(), INTERVAL 2 DAY) AND ADDDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_admin_before_option'] . ' DAY);
;';

// echo('<pre>');print_r($query);echo('</pre>');

  $result = pwg_query($query);

  $imagesDetails = array();
  $image_ids = array();

  while ($row = pwg_db_fetch_assoc($result))
  {
    $prenotify_before = strtotime('-'.$conf['expiry_date']['expd_notify_admin_before_option'], strtotime($row['expiry_date']));
    $row['prenotification_date'] = $prenotify_before;
    $limitprenotification = strtotime("-2days", strtotime($row['expiry_date']));
    $row['limit_prenotification'] = $limitprenotification;
    array_push($imagesDetails,$row);
    array_push($image_ids, $row['id']);
  }

  if (empty($imagesDetails))
  {
    return;
  }

  //get list of admin ids and emails for notification history
  $admin_ids = get_admins(true);

  $admin_emails = array();

    $query = '
SELECT 
  '.$conf['user_fields']['id'].' AS id,
  '.$conf['user_fields']['email'].' AS email
  FROM '.USERS_TABLE.'
  WHERE '.$conf['user_fields']['id'].' IN ('.implode(',',$admin_ids).')
    AND `'.$conf['user_fields']['email'].'` IS NOT NULL
;';

  $admin_emails = query2array($query, 'id', 'email');

  if (empty($admin_emails))
  {
    return;
  }

  $query = '
SELECT
    image_id, user_id
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  WHERE send_date > SUBDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_admin_before_option'].' DAY)
    AND image_id IN ('.implode(',', $image_ids).')
    AND type = \'prenotification_admin_'.$conf['expiry_date']['expd_notify_admin_before_option'].'\'
  ;';
  
  // echo('<pre>');print_r($query);echo('</pre>');
  
  // echo('<pre>');print_r($notifications_sent);echo('</pre>');
  
  $notifications_sent = query2array($query, "user_id", "image_id");

  if (empty($notifications_sent))
  {
    return;
  }

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  $notification_history = array();
  $image_to_notify = 0;

// echo('<pre>notification being sent :');print_r($notifications_sent);echo('</pre>');
    
  foreach ($imagesDetails as $image)
  {

    if (isset($notifications_sent[$image['id']]))
      {
        continue;
      }

    // echo('<pre>notification being sent :');print_r($notification_being_sent);echo('</pre>');

    if (isset($notifications_sent[$image['id']]) )
    {
      continue;
    }

    $image_info = "\n\n";
    $image_info .= '* '.$image["name"].' '.$image["author"].' ('.$image["file"]."), on ".strftime('%A %d %B %G', strtotime($image["expiry_date"]))."\n";
    $image_info .= "\n\n";

    foreach ($admin_ids as $admin_id)
    {
      $notification_history[] = array(
        'type' => 'prenotification_admin_'.$conf['expiry_date']['expd_notify_admin_before_option'],
        'user_id' =>  $admin_id,
        'image_id' => $image['id'],
        'send_date' => $dbnow,
        'email_used' => $admin_emails[$admin_id],
      );
      $image_to_notify++; 

    }

    $image_info .= "\n\n";
  }

  if ($image_to_notify == 0)
  {
    return;
  }

  // notify admins on expiration
  $current_user_id = $user['id'];
  $user['id'] = -1; // make sure even the current user will get notified

  $subject = l10n('Expiry date, these images will expire');
  $keyargs_content = array(
    get_l10n_args("These images will expire: %s",$image_info)
  );
  
  if ($dbnow < $imagesDetails[0]["prenotification_date"] and $dbnow < $imagesDetails[0]["limit_prenotification"])
  {
    pwg_mail_notification_admins($subject, $keyargs_content, false);
  }

  if (count($notification_history) > 0)
  {
    addNotificationHistory($notification_history);
  }
}

/**
 * Send prenotifications to users
 * $user_id user id for notification history
 * $user_email for notification history
 */

function send_prenotifications_user()
{
  global $conf, $prefixeTable;

  $notification_history = array();

  //Check if either admin or user prenotification is set
  if ('none' == $conf['expiry_date']['expd_notify_before_option'])
  {
    return;
  }

  // select all images expiring in X days for user prenotif
  $query = '
SELECT
    id,
    file,
    name,
    author,
    expiry_date
  FROM '.IMAGES_TABLE.'
  WHERE expiry_date BETWEEN ADDDATE(NOW(), INTERVAL 2 DAY) AND ADDDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_before_option'].' DAY);
;';

  $result = pwg_query($query);

  $imagesDetails = array();
  $image_ids = array();

  while ($row = pwg_db_fetch_assoc($result))
  {
    $prenotify_before = strtotime('-'.$conf['expiry_date']['expd_notify_before_option'], strtotime($row['expiry_date']));
    $row['prenotification_date'] = $prenotify_before;
    $limitprenotification = strtotime("-2days", strtotime($row['expiry_date']));
    $row['limit_prenotification'] = $limitprenotification;
    array_push($imagesDetails,$row);
    array_push($image_ids,$row['id']);
  }

  if (empty($imagesDetails))
  {
    return;
  }

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
    @$user_history[$history_line['user_id']][$history_line['image_id']]++;
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
user_id, language
FROM '.USER_INFOS_TABLE.'
WHERE user_id IN ('.implode(',', $user_ids).')
;';
      $language_of_user = query2array($query, 'user_id', 'language');
    }
  }

  $query = '
SELECT
    image_id,
    user_id,
    send_date
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  WHERE send_date > SUBDATE(NOW(), INTERVAL '.$conf['expiry_date']['expd_notify_before_option'].' DAY)
    AND image_id IN ('.implode(',', $image_ids).')
    AND type = \'prenotification_'.$conf['expiry_date']['expd_notify_before_option'].'\'
;';
  $result = pwg_query($query);
  $notifications_sent = array();
  while ($row = pwg_db_fetch_assoc($result)) {
    $notifications_sent[$row['image_id'] . '_' . $row['user_id']] = $row['send_date'];
  }
  // echo('<pre>');print_r($querey);echo('</pre>');

  // echo('<pre>');print_r($notifications_sent);echo('</pre>');

  $notification_history = array();
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

  foreach ($user_history as $user_id => $user_image_ids)
  {
    if (!isset($email_of_user[$user_id]))
    {
      continue;
    }

    $image_to_notify = 0;

    $recipient_language = get_default_language();
    if (isset($language_of_user[$user_id]))
    {
      $recipient_language = $language_of_user[$user_id];
    }

    switch_lang_to($recipient_language);

    $image_info = "\n\n";
    foreach (array_keys($user_image_ids) as $user_image_id)
    {
      if (isset($notifications_sent[$user_image_id.'_'.$user_id]))
      {
        continue;
      }

      $image_info = "\n\n";
      foreach ($imagesDetails as $image)
      {
        if ($user_image_id != $image["id"])
        {
          continue;
        } 

        $image_to_notify++;  

        $image_info.= '* '.$image["name"].' '.$image["author"].' ('.$image["file"]."), on ".strftime('%A %d %B %G', strtotime($image["expiry_date"]))."\n";
         
        $notification_history[] = array(
          'type' => 'prenotification_user_'.$conf['expiry_date']['expd_notify_before_option'],
          'user_id' =>  $user_id,
          'image_id' => $user_image_id,
          'send_date' => $dbnow,
          'email_used' => $email_of_user[$user_id],
        );
      }
      $image_info = "\n\n";
    }

    // echo ('<pre>');print_r($image_info);echo ('</pre>');
    if ($image_to_notify == 0)
    {
      continue;
    }
  
    $subject = l10n('Expiry date, These images will expire');
    $keyargs_content = array(
      get_l10n_args("These images will expire: %s",$image_info)
    );

    switch_lang_back();

    pwg_mail(
      $email_of_user[$user_id],
      array(
        'subject' => $subject,
        'content' => $keyargs_content,
        'content_format' => 'text/plain',
      )
    );
  } 

  //add notification to notification history
  if (count($notification_history) > 0)
  {
    add_notification_history($notification_history);
  }
}
