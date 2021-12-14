<?php

/**
 * Add notification to expd_ntifications tabel
 * keep track of what notification sent to who
 */

function add_notification_history($notifications)
{
  mass_inserts(
    EXPIRY_DATE_NOTIFICATIONS_TABLE,
    array_keys($notifications[0]),
    $notifications
  );
}

