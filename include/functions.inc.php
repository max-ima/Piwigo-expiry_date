<?php

/**
 * Add notification to expd_ntifications tabel
 * keep track of what notification sent to who
 */

function expd_add_notification_history($notifications)
{
  mass_inserts(
    EXPIRY_DATE_NOTIFICATIONS_TABLE,
    array_keys($notifications[0]),
    $notifications
  );
}

/**
 * Code copied from empty_lounge
 */
function expd_single_exec($token_name, $timeout = 60)
{
  global $conf, $logger;

  if (isset($conf[$token_name]))
  {
    list($running_exec_id, $running_exec_start_time) = explode('-', $conf[$token_name]);
    if (time() - $running_exec_start_time > $timeout)
    {
      $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$running_exec_id.', timeout stopped by another call to the function');
      conf_delete_param($token_name);
    }
  }

  $exec_id = generate_key(4);
  $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.', begins');

  // if lounge is already being emptied, skip
  $query = '
INSERT IGNORE
  INTO '.CONFIG_TABLE.'
  SET param="'.$token_name.'"
    , value="'.$exec_id.'-'.time().'"
;';
  pwg_query($query);

  list($token_name) = pwg_db_fetch_row(pwg_query('SELECT value FROM '.CONFIG_TABLE.' WHERE param = "'.$token_name.'"'));
  list($running_exec_id,) = explode('-', $token_name);

  if ($running_exec_id != $exec_id)
  {
    $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.', skip');
    return false;
  }
  $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.' wins the race and gets the token!');

  return $exec_id;
}


function expd_single_exec_end($token_name, $exec_id)
{
  global $logger;
  
  conf_delete_param($token_name);
  $logger->debug(__FUNCTION__.', token_name='.$token_name.', exec='.$exec_id.' releases the token');
}