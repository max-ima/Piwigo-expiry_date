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
  
  var associated_categories = {$associated_categories|@json_encode};

  categoriesCache.selectize(jQuery('[data-selectize=categories]'), {
    {* filter: function(categories, options) {
      if (this.name == 'dissociate') {
        var filtered = jQuery.grep(categories, function(cat) {
          return !!associated_categories[cat.id];
        });

        if (filtered.length > 0) {
          options.default = filtered[0].id;
        }

        return filtered;
      }
      else {
        return categories;
      }
    } *}
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
        <label for="expd_actions"><strong>What should piwigo do once photos expire?</strong></label>
{html_radios name=expd_actions options=$expd_actions_options selected=$selectedAction onchange="showDiv('expd_archive_album_choice', this)"}
      </div>
    </div>

    <div id="expd_archive_album_choice" {if $selectedAction != 'archive'}style="display:none;"{else}style="display:block;"{/if}>
        <label><strong>Where should Piwigo move archived photos?</strong></label>
        <br>
        <select data-selectize="categories" data-default="" data-value="{$selected_category|@json_encode|escape:html}" name="selected_category"  placeholder="{'Select an album... or type it!'|@translate}"></select>
    </div>


    <div id="expd_notify_checkbox">
        <label><strong>Should users be notified of expiring photos?</strong></label><br>
        <input type="checkbox" id="expd_notify" name="expd_notify" value="notify"  {if $notifyAction}checked{/if}>
        <label for="expd_notify" class="tiptip" title="">Notify users of photo expiration. <br><i>This will send a email, on the expiry date, to notify anyone who has downloaded the photo that it is expiring.</i></label>
        <p><i class="icon-attention"></i> A user will be notified of an expiring photo only if their visit history is saved.
        <br>To change this setting go to: Configuration - Options - Miscellaneous - Save visits in history for</p>
    </div> 

    <div id="expd_save_config">
      <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
      <input class="submit" type="submit" value="{'Save Settings'|@translate}" name="submit">
    </div>

  </form>

</div>