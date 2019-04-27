<style>{literal}
.bigstat span {
  font-size: 2rem;
}
</style>{/literal}
<h2>Subscribers</h2>
<!-- date range selectors -->
<form method="get">
  <select id="date_range_type" name="date_range_type" >
    <option value="last_6_months" {if $date_range_type == 'last_6_months'}selected{/if} >Last 6 months</option>
    <option value="last_3_months" {if $date_range_type == 'last_3_months'}selected{/if} >Last 3 months</option>
    <option value="between" {if $date_range_type == 'between'}selected{/if} >Between</option>
  </select>

  <div id="date_range_between" {if $date_range_type != 'between'} style="display:none;"{/if}>
    <span><input name="date_range_start" value="{$date_range_start}"/></span> and
    <span><input name="date_range_end" value="{$date_range_end}"/></span>
  </div>

  <input type="submit" value="Update" />
</form>

<!-- /date range selectors -->
<p class="bigstat">
  <span class="bigstat__stat">{$currentTotalUniqueSubscribers}</span> people subscribed.
</p>
<p class="bigstat">
  <span class="bigstat__stat">{$activeSubscribers}</span> active subscribers.
</p>



<table id="subscribers-by-list">
  <thead>
    <tr><th>List</th><th>Subscribers</th></tr>
  </thead>
  <tbody>
  {foreach from=$selectedListCounts as key="group_id" item="row"}
    <tr> <td>{$row.title}</td> <td>{$row.count}</td> </tr>
  {/foreach}
  </tbody>
</table>

<!--  Form for editing selected mailing lists -->
<div style="background:white;padding:1rem;">
  <a href id="toggleListEdit" >Edit lists</a>
  <span id="reloadForListChanges" style="display:none"> | <a  href='?'>Reload page to show changes</a></span>
  <form id="listEdit" style="display:none;">
  {foreach from=$allLists item="item" key="id"}
    <div>
      <label><input class="group_checkbox" data-group_id="{$id}" name="select_{$id}" type="checkbox" {if $item.selected}checked{/if} /> {$item.title}</label>
    </div>
  {/foreach}
  </form>
</div>
<!-- /Form for editing selected mailing lists -->


<p>The current time is {$currentTime}</p>

{* Example: Display a translated string -- which happens to include a variable *}
<p>{ts 1=$currentTime}(In your native language) The current time is %1.{/ts}</p>
<script>{literal}
CRM.$(() => {
  CRM.$('#subscribers-by-list').dataTable();

  const $listEdit = CRM.$('#listEdit');
  CRM.$('#toggleListEdit').on('click', e => {
    e.preventDefault();
    if ($listEdit.hasClass('shown')) {
      $listEdit.removeClass('shown').fadeOut('fast');
    }
    else {
      $listEdit.addClass('shown').fadeIn('fast');
    }
  });
  const $allListInputs = CRM.$('.group_checkbox')
    .on('input', e => {
      e.preventDefault();
      CRM.$('#reloadForListChanges').show();
      const selected = [];
      $allListInputs.each(function() {
        if (this.checked) {
          selected.push(parseInt(this.dataset.group_id));
        }
      });
      CRM.api3('Oddashboard', 'updateconfig', { mailingLists: selected})
      .done(r => {
        if (r.is_error) {
          CRM.status(r.error_message, 'error');
        }
        else {
          CRM.status('Saved');
        }
      });
  });

  const $dateRangeBetween = CRM.$('#date_range_between');
  const updateDateRangeUi = function updateDateRangeUi() {
    if ($dateRangeType.val() == 'between') {
      $dateRangeBetween.fadeIn('fast');
    }
    else {
      $dateRangeBetween.fadeOut('fast');
    }
  }
  const $dateRangeType = CRM.$('#date_range_type').on('change', updateDateRangeUi);
  updateDateRangeUi();
  CRM.$('input[name="date_range_start"], input[name="date_range_end"]').datepicker({
    dateFormat: 'd M yy'
  });

});
{/literal}</script>
