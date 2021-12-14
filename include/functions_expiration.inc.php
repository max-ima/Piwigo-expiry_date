<?php

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH.'include/functions_mail.inc.php');
include_once(EXPIRY_DATE_PATH.'include/functions.inc.php');

/**
 * Notify admins of image expiration
 */
function notify_admins($images, $subject, $keyargs_content )
{
  global $conf, $user;

  if (!isset($conf['expiry_date']['expd_notify_admin']))
  {
    return;
  }

  if (empty($images))
  {
    return;
  }

  //get list of admin ids and emails for notification history
  $admin_ids = get_admins(true);
  $admin_emails = array();

  if (empty($admin_ids))
  {
    return;
  }

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
    DISTINCT(image_id)
  FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
  WHERE send_date > SUBDATE(NOW(), INTERVAL 60 DAY)
    AND image_id IN ('.implode(',', array_keys($images)).')
    AND type = \'expiration_notification_admin\'
  ;';
    
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  $email_uuid = generate_key(10);

  $notifications_sent = query2array($query, 'image_id');
  $notification_history = array();

  foreach ($images as $image_id => $image)
  {
    if (isset($notifications_sent[$image['id']]))
    {
      continue;
    }

    foreach ($admin_ids as $admin_id)
    {
      $notification_history[] = array(
        'type' => 'expiration_notification_admin',
        'user_id' =>  $admin_id,
        'image_id' => $image['id'],
        'send_date' => $dbnow,
        'email_used' => $admin_emails[$admin_id],
        'email_uuid' => $email_uuid,
      );
    }
  }

  if (count($notification_history) > 0)
  {
    // notify admins on expiration
    $current_user_id = $user['id'];
    $user['id'] = -1; // make sure even the current user will get notified

    pwg_mail_notification_admins($subject, $keyargs_content, false);
    add_notification_history($notification_history);

    $user['id'] = $current_user_id;
  }

}

/**
 * Notify users of photo expiration
 */
function notify_users($image_details, $image_ids)
{
  if (!isset($conf['expiry_date']['expd_notify']))
  {
    return;
  }

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

  if (empty($user_ids))
  {
    return;
  }
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
    return;
  }
    $query = '
SELECT
    user_id, language
  FROM '.USER_INFOS_TABLE.'
  WHERE user_id IN ('.implode(',', $user_ids).')
;';

  $language_of_user = query2array($query, 'user_id', 'language');
  
  $query = '
  SELECT
      image_id,
      user_id,
      send_date
    FROM '.EXPIRY_DATE_NOTIFICATIONS_TABLE.'
    WHERE send_date > SUBDATE(NOW(), INTERVAL 60 DAY)
      AND image_id IN ('.implode(',', array_keys($images)).')
      AND type = \'expiration_notification_user\'
  ;';
  $result = pwg_query($query);
  
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  
  $email_uuid = generate_key(10);
  $notifications_sent = array();
  $notification_history = array();

  while ($row = pwg_db_fetch_assoc($result)) {
    $notifications_sent[$row['image_id'].'_'.$row['user_id']] = $row['send_date'];
  }
  
  foreach ($user_history as $user_id => $user_image_ids)
  {
    if (!isset($email_of_user[$user_id]))
    {
      continue;
    }

    $recipient_language = get_default_language();
    if (isset($language_of_user[$user_id]))
    {
      $recipient_language = $language_of_user[$user_id];
    }

    switch_lang_to($recipient_language);

    $image_info .= "\n\n";
    foreach (array_keys($user_image_ids) as $user_image_id)
    {
      foreach ($images as $image)
      {
        if ($user_image_id = $image["id"])
        {
          $image_info .= '* '.$image["name"].' '.$image["author"].' ('.$image["file"]."), on ".strftime('%A %d %B %G', strtotime($image["expiry_date"]))."\n\n";
         
          $notification_history[] = array(
            'type' => 'expiration_notification_user',
            'user_id' =>  $user_id,
            'image_id' => $user_image_id,
            'send_date' => $dbnow,
            'email_used' => $email_of_user[$user_id],
          );
        }
      }
    }

    if (count($notification_history) > 0)
    {

      $keyargs_content = array(
        get_l10n_args("You have recieved this email because you previously downloaded these photos: %s", $image_info),
        get_l10n_args("These photo have reached their expiry date."),
      );

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

      //add notification to notification history
      add_notification_history($notification_history);
    }
  } 



}
 