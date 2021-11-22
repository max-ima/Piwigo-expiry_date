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
    global $conf, $prefixeTable;
    
    
    $result = pwg_query('SHOW COLUMNS FROM `'.IMAGES_TABLE.'` LIKE "expiry_date" ');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `'.IMAGES_TABLE.'` ADD `expiry_date` DATETIME;');
    }

    $result = pwg_query('SHOW COLUMNS FROM `'.IMAGES_TABLE.'` LIKE "expd_action_applied_on" ');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `'.IMAGES_TABLE.'` ADD `expd_action_applied_on` DATETIME;');
    }

    
    $result = pwg_query('SHOW COLUMNS FROM `'.IMAGES_TABLE.'` LIKE "expd_expired_on" ');
    if (!pwg_db_num_rows($result))
    {
      pwg_query('ALTER TABLE `'.IMAGES_TABLE.'` ADD `expd_expired_on` DATETIME;');
    }

    $this->table = $prefixeTable . 'expd_notifications';

    pwg_query('
    CREATE TABLE IF NOT EXISTS `'. $this->table .'` (
      `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
      `type` varchar(64) NOT NULL,
      `user_id` int(11) NOT NULL,
      `image_id` int(11) NOT NULL,
      `send_date` DATETIME,
      `email_used` varchar(64),
      PRIMARY KEY (`id`),
      FOREIGN KEY(image_id) REFERENCES '.IMAGES_TABLE.'(id),
      FOREIGN KEY(user_id) REFERENCES '.USERS_TABLE.'(id)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8
    ;');

    if (!isset($conf['expiry_date']))
    {
      $expiry_date_default_config = array(
        'expd_action' => 'nothing',
        'expd_archive_album' => null,
        'expd_notify' => false,
        'expd_notify_before_option' => 'none',
        'expd_notify_admin' => false,
        'expd_notify_admin_before_option' => 'none',
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
    global $prefixeTable;

    //delete table
    $query = 'DROP TABLE '.$prefixeTable.'expd_notifications;';
    pwg_query($query);

    // delete field
    pwg_query('ALTER TABLE `'. IMAGES_TABLE .'` DROP `expiry_date`, DROP `expd_action_applied_on`, DROP `expd_expired_on`;');

    conf_delete_param('expiry_date');
    conf_delete_param('expd_last_check');
  }

}
?>