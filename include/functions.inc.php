<?php

/**
 * Add notification to expd_ntifications tabel
 * keep track of what notification sent to who
 */

function addNotificationHistory($notifications)
{
  global $prefixeTable;
  // echo('<pre>');print_r($notifications);echo('</pre>');

  mass_inserts(
    $prefixeTable.'expd_notifications',
    array_keys($notifications[0]),
    $notifications
  );
}

