<style>{literal}
.bigstat span {
  font-size: 2rem;
}
</style>{/literal}
<h2>Subscribers</h2>
<p class="bigstat">
  <span class="bigstat__stat">{$currentTotalUniqueSubscribers}</span> people subscribed.
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

});
{/literal}</script>
