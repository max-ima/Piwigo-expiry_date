<?php
defined('expiry_date_PATH') or die('Hacking attempt!');

/**
 * Add prefilter to picture modify trigger
 */
function expd_loc_end_picture_modify()
{
  global $template, $page, $data;

  //if expiry date is set display it in expiry date field
  if (isset($data['expiry_date']))
  {
    $page['image']['expiry_date'] = $data['expiry_date'];
  }
  $template->assign(array('EXPIRY_DATE'=>$page['image']['expiry_date']));

  $template->set_prefilter('picture_modify', 'expd_picture_modify_prefilter');
  $template->set_filename('expiry_date_picture_modify', realpath(expiry_date_PATH.'picture_modify.tpl'));
  $template->assign_var_from_handle('EXPD_PICTURE_MODIFY_CONTENT', 'expiry_date_picture_modify');
}

/**
 * Add date time picker input at end of photo edit page
 */
function expd_picture_modify_prefilter($content)
{
  //search for save button in edit picture page 
  $search = '<p>\s*<input type="hidden" name="pwg_token"';

  $replace = '{$EXPD_PICTURE_MODIFY_CONTENT}
  <p>
    <input type="hidden" name="pwg_token"';

  return preg_replace('/'.$search.'/s', $replace, $content);
}

/**
 * Update picture information
 */
function expd_picture_modify_before_update($data)
{
  if (!empty($_POST['expiry_date']))
  {
    $data['expiry_date'] = $_POST['expiry_date'];
  }
  else
  {
    $data['expiry_date'] = null;
  }

  return $data;
}

/**
 * Add a predefined filter to the Batch Manager
 * Add a prefilter
 */
function expd_get_batch_manager_prefilters($prefilters)
{
  array_push($prefilters, array(
    'ID' => 'expiry_date',
    'NAME' => l10n('Expiring photos')
    // 'CONTENT' => $template->parse('expd_options', true)
  ));

  return $prefilters;
}

/**
 * Add prefilter to picture modify trigger
 */
function expd_loc_end_element_set_global()
{
  global $template;
  $template->set_prefilter('batch_manager_global', 'expd_batch_manager_global');
  $template->set_filename('expiry_date_expd_options', realpath(expiry_date_PATH.'batch_manager_global_filter_options.tpl'));
  $template->assign_var_from_handle('EXPD_OPTIONS_CONTENT', 'expiry_date_expd_options');
}

function expd_batch_manager_global($content)
{
  $search = '<span id="duplicates_options"';
  $replace = '{$EXPD_OPTIONS_CONTENT}'.$search;

  return str_replace($search, $replace, $content);
}

/**
 * register batch manager filters
 */
function expd_batch_manager_register_filters($filters)
{
  if (isset($_POST['filter_prefilter_use']) and 'expiry_date' == $_POST['filter_prefilter'] and isset($_POST['filter_expd']))
  {
    check_input_parameter('filter_expd', $_POST, false, PATTERN_ID);

    $filters['expiry_date_option'] = $_POST['filter_expd'];
  }

  return $filters;
}

/**
 * perform added predefined filter
 */
function expd_batch_manager_perform_filters($filter_sets)
{
  if (isset($_SESSION['bulk_manager_filter']['expiry_date_option']))
  {
    $query = '
SELECT id 
  FROM '.IMAGES_TABLE.'
  WHERE expiry_date < ADDDATE(NOW(), INTERVAL '.$_SESSION['bulk_manager_filter']['expiry_date_option'].' DAY)
;';
    $filter_sets[] = query2array($query, null, 'id');
  }

  return $filter_sets;
}

function expd_element_set_global_action($action, $collection)
{
  if ('expiry_date' == $action and count($collection) > 0)
  {
    if (isset($_POST['remove_expiry_date']) or empty($_POST['expiry_date']))
    {
      $expiry_date = null;
    }
    else
    {
      check_input_parameter('expiry_date', $_POST, false, '/^\d\d\d\d-\d\d-\d\d \d\d:\d\d:\d\d$/');
      $expiry_date = $_POST['expiry_date'];
    }

    $datas = array();
    foreach ($collection as $image_id)
    {
      $datas[] = array(
        'id' => $image_id,
        'expiry_date' => $expiry_date
        );
    }

    mass_updates(
      IMAGES_TABLE,
      array('primary' => array('id'), 'update' => array('expiry_date')),
      $datas
      );

    pwg_activity('photo', $collection, 'edit', array('action'=>'expiry_date'));

    $_SESSION['page_infos'] = array(l10n('Information data registered in database'));
    redirect(get_root_url().'admin.php?page='.$_GET['page']);
  }
}

function expd_element_set_global_add_action()
{
  global $template, $page;
  
  $template->set_filename('expiry_date', realpath(expiry_date_PATH.'batch_manager_global_action.tpl'));

  $template->assign(
    array(
      'EXPIRY_DATE' => empty($_POST['expiry_date']) ? date('Y-m-d', strtotime('+1 year')).' 00:00:00' : $_POST['expiry_date'],
      )
    );

  $template->append(
    'element_set_global_plugins_actions',
    array(
      'ID' => 'expiry_date',
      'NAME' => l10n('Expiry date'),
      'CONTENT' => $template->parse('expiry_date', true),
      )
    );
}