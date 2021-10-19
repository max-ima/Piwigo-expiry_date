<p>
  <strong>{"expd Expiry date"|translate}</strong>
  <br>
  <input type="hidden" name="expiry_date" value="{$EXPIRY_DATE}">
  <label class="date-input">
    <i class="icon-calendar"></i>
    <input type="text" data-datepicker="expiry_date" data-datepicker-unset="expiry_date_unset" readonly>
  </label>
  <a href="#" class="icon-cancel-circled" id="expiry_date_unset">{"unset"|translate}</a>
</p>