<?php

// Add an event handler for a prefilter
add_event_handler('loc_end_picture', 'expd_loc_end_picture');

/**
 * Add the prefilter to the template
 */
function expd_loc_end_picture()
{
	global $template, $picture;

  $template->set_prefilter('picture', 'expd_picture_prefilter');

  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  
  if (!isset($picture['current']['expiry_date']) and !isset($picture['current']['expd_expired_on']))
  {
    return;
  }

  if (isset($picture['current']['expiry_date']))
  {
    $expiry_date = $picture['current']['expiry_date'];

    $datetime1 = new DateTime($expiry_date);
    $datetime2 = new DateTime($dbnow);
    $interval = $datetime2->diff($datetime1);
    $interval = $interval->format("%r%a");

    $expiry_date = strftime('%A %d  %B %G', strtotime($picture['current']['expiry_date']));
    $template->assign(
      array	(
        'expiry_date' => $expiry_date,
        'expd_days' => $interval,
      )
    );
  }

  if (isset($picture['current']['expd_expired_on']))
  {
    $expired_on_date = strftime('%A %d  %B %G', strtotime($picture['current']['expd_expired_on']));
    $template->assign(
      array	(
        'expired_on_date' => $expired_on_date,
      )
    );
  }


}

/**
 * Add expiry date to picture page
 */
function expd_picture_prefilter($content)
{
  //TODO make compatible with modus, bootstrap and elegant
	// Search for image info block
	$search = '{if $display_info.rating_score';
	
  //add expiry date to image info block or expire on date

  $replace = '
{if isset($expiry_date)}
<div id="expd_expiry_date"" class="imageInfo">
  <dl class="row mb-0">
    <dt class="col-sm-5">{\'Expiry date\'|@translate}</dt>
    <dd class="col-sm-7">{$expiry_date}, in {$expd_days} days</dd>
  </dl>
</div>
{/if}

{if isset($expired_on_date)}
<div id="expd_expiry_date"" class="imageInfo">
  <dl class="row mb-0">
    <dt class="col-sm-5">{\'expired on\'|@translate}</dt>
    <dd class="col-sm-7">{$expired_on_date}</dd>
  </dl>
</div>
{/if}
    '.$search;

	return str_replace($search, $replace, $content);
}
