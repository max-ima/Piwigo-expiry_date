{combine_css  path="plugins/expiry_date/admin/config.css"}

{combine_script id='LocalStorageCache' load='footer' path='admin/themes/default/js/LocalStorageCache.js'}

{combine_script id='jquery.selectize' load='footer' path='themes/default/js/plugins/selectize.min.js'}
{combine_css id='jquery.selectize' path="themes/default/js/plugins/selectize.{$themeconf.colorscheme}.css"}

{footer_script}
jQuery(document).ready(function() {

  window.categoriesCache = new CategoriesCache({
    serverKey: '{$CACHE_KEYS.categories}',
    serverId: '{$CACHE_KEYS._hash}',
    rootUrl: '{$ROOT_URL}'
  });
  
  categoriesCache.selectize(jQuery('[data-selectize=categories]'));

  function toggleNotifyDetails() {
    jQuery('.expd_notify_checkbox_details').toggle(jQuery('#expd_notify').is(":checked"));
    jQuery('.expd_notify_admin_checkbox_details').toggle(jQuery('#expd_notify_admin').is(":checked"));
  }

  toggleNotifyDetails();
  jQuery('#expd_notify').change(function(){
    toggleNotifyDetails();
  });
  
  jQuery('#expd_notify_admin').change(function(){
    toggleNotifyDetails();
  });
});

function showDiv(divId, element){
  document.getElementById(divId).style.display = element.value == 'archive' ? 'block' : 'none';
}

{/footer_script}

<div class="titrePage">
	<h2>Expiry date</h2>
</div>

<div id="expd_config_page">

  <form method="post" action="{$F_ACTION}">

    <div id="expd_options">
      <div id="expd_action_select">
        <label for="expd_actions"><strong>{'What should piwigo do once photos expire'|translate}</strong></label>
{html_radios name=expd_actions options=$expd_actions_options selected=$selectedAction onchange="showDiv('expd_archive_album_choice', this)"}
      </div>
    </div>

    <div id="expd_archive_album_choice" {if $selectedAction != 'archive'}style="display:none;"{else}style="display:block;"{/if}>
        <label><strong>{'Where should Piwigo move archived photos'|translate}</strong></label>
        <br>
        <select data-selectize="categories" data-default="" data-value="{$selected_category|@json_encode|escape:html}" name="selected_category"  placeholder="{'Select an album... or type it!'|translate}"></select>
    </div>


    <div id="expd_notify_checkbox">
        <label>
          <input type="checkbox" id="expd_notify" name="expd_notify" value="notify" {if $notifyAction}checked{/if}>
          {'Notify downloaders of photo expiration'|translate}
        </label>

        <div class="expd_notify_checkbox_details">
          <p>
            <i>{'On the expiry date, an email will be sent to notify anyone who has downloaded the photo.'|translate}</i>
            <br><i class="icon-attention"></i>{'Piwigo knows if a user has downloaded the photo only if their visit history is saved.'|translate}
            <br>{'To change this setting go to:'|translate} {'Configuration'|translate} &raquo; {'Options'|translate} &raquo; {'General'|translate} &raquo; {'Miscellaneous'|translate} &raquo; {'Save visits in history for'|translate}
          </p>
          <div>
            <p>Notify users before the expiry date :
            <br><i>A set number of days before a email will be sent to notifiy that photos are expiring in the futur</i></p>
            {html_options name=expd_notify_before_option options=$expd_prenotification_options selected=$notifyActionBeforeOption}
          </div>
        </div>
    </div> 

    <div id="expd_notify_admin_checkbox">
        <label>
          <input type="checkbox" id="expd_notify_admin" name="expd_notify_admin" value="notify_admins" {if $notifyActionAdmin}checked{/if}>
          Notify admins of photo expiration
        </label>

        <div class="expd_notify_admin_checkbox_details">
          <i>On the expiry date, an email will be sent to all admins</i>
          <div>
            <p>Notify admins before the expiry date :
            <br><i>A set number of days before a email will be sent to notifiy that photos are expiring in the futur</i></p>
            {html_options name=expd_admin_notify_before_option options=$expd_prenotification_options selected=$notifyActionAdminBeforeOption}
          </div>
        </div>
    </div> 

    <div id="expd_save_config">
      <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
      <input class="submit" type="submit" value="{'Save Settings'|translate}" name="submit">
    </div>

  </form>

</div>