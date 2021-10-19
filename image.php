<?php

// Add an event handler for a prefilter
add_event_handler('loc_end_picture', 'expd_loc_end_picture');

/**
 * Add the prefilter to the template
 */
function expd_loc_end_picture()
{
	global $template, $picture;

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  
  if (!isset($picture['current']['expiry_date'])){
    return;
  }

  $expiry_date = $picture['current']['expiry_date'];

  $datetime1 = new DateTime($expiry_date);
  $datetime2 = new DateTime($dbnow);
  $interval = $datetime2->diff($datetime1);
  $interval = $interval->format("%r%a");

  $expiry_date = strftime('%A %d  %B %G', strtotime($picture['current']['expiry_date']));
  
  $template->assign(
    array	(
      'expiry_date' => $expiry_date,
      'days' => $interval,
    )
  );

	$template->set_prefilter('picture', 'expd_picture_prefilter');
}

/**
 * Add expiry date to picture page
 */
function expd_picture_prefilter($content)
{
  global $template, $page, $data;
  //TODO make compatible with modus, bootstrap and elegant
	// Search for image info block
	$search = '#id="info-content" class="d-flex flex-column">#';
	
  //add expiry date to image info block
	$replacement = 'id="info-content" class="d-flex flex-column">
<div id="expd_expiry_date"" class="imageInfo">
  <dl class="row mb-0">
    <dt class="col-sm-5">{`expd Expiry date`|@translate}</dt>
    <dd class="col-sm-7">{$expiry_date}, in {$days} days</dd>
  </dl>
</div>
';

	return preg_replace($search, $replacement, $content);
}
