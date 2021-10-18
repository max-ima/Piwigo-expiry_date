<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class expiry_date_maintain extends PluginMaintain
{
  private $installed = false;
 
  function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  /**
   * plugin installation
   */
  function install($plugin_version, &$errors=array())
  {
    global $conf;
    
    $result = pwg_query('SHOW COLUMNS FROM `'.IMAGES_TABLE.'` LIKE "expiry_date" ');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `'.IMAGES_TABLE.'` ADD `expiry_date` DATETIME;');
    }

    $result = pwg_query('SHOW COLUMNS FROM `'.IMAGES_TABLE.'` LIKE "expd_archive_date" ');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `'.IMAGES_TABLE.'` ADD `expd_archive_date` DATETIME;');
    }

    $result = pwg_query('SHOW COLUMNS FROM `'.IMAGES_TABLE.'` LIKE "expd_action_applied_on" ');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `'.IMAGES_TABLE.'` ADD `expd_action_applied_on` DATETIME;');
    }

    if (!isset($conf['expiry_date']))
    {
      $expiry_date_default_config = array(
        'expd_action' => 'nothing',
        'expd_notify' => false,
        'expd_archive_album' => null,
        );
      
      conf_update_param('expiry_date', $expiry_date_default_config, true);
    }
  }

  /**
   * Plugin activation
   */
  function activate($plugin_version, &$errors=array())
  {
  }

  /**
   * Plugin deactivation
   */
  function deactivate()
  {
  }

  /**
   * Plugin (auto)update

   */
  function update($old_version, $new_version, &$errors=array())
  {
    $this->install($new_version, $errors);
  }

  /**
   * Plugin uninstallation
   */
  function uninstall() 
  {
    // delete field
    pwg_query('ALTER TABLE `'. IMAGES_TABLE .'` DROP `expiry_date`, DROP `expd_archive_date`, DROP `expd_action_applied_on`;');

    conf_delete_param('expiry_date');
    conf_delete_param('expd_last_check');
  }

}
?>