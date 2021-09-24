<?php
/*
Plugin Name: Expiry Date
Version: 1.0
Description: Set an expiry date on photos. This plugin adds a field to the edit photo page and allows you to set an expiry date. Int the batch manager you can filter photos to see which ones are expiring soon and handle the appropriately.
Plugin URI:
Author: HWFord
Author URI: https://github.com/HWFord
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

define('expiry_date_ID',      basename(dirname(__FILE__)));
define('expiry_date_PATH' ,   PHPWG_PLUGINS_PATH . expiry_date_ID . '/');
define('expiry_date_DIR',     PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'expiry_date/');

include_once(expiry_date_PATH.'include/admin_events.inc.php');

// +-----------------------------------------------------------------------+
// | Add event handlers                                                    |
// +-----------------------------------------------------------------------+

// init the plugin
add_event_handler('init', 'expiry_date_init');

/**
 * plugin initialization
 *   - check for upgrades
 *   - unserialize configuration
 *   - load language
 */
function expiry_date_init()
{
  load_language('plugin.lang', expiry_date_PATH);
}

/*
 * event functions in admin
 */
if (defined('IN_ADMIN'))
{
  // file containing all admin handlers functions
  $admin_file = expiry_date_PATH . 'include/admin_events.inc.php';
  
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
}

