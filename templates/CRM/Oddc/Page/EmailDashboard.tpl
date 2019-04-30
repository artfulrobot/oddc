<style>{literal}
/** Main layout grid */

.oddc-layout {
  display:grid;
  column-gap: 2rem;
  row-gap: 2rem;
  grid-template-columns: 50fr 50fr;
}
.oddc-layout__keystats {
  grid-column: 2;
  grid-row: 1;
}
.oddc-layout__lists {
  grid-column: 1;
  grid-row: 1;
}
.oddc-layout__mailings {
  grid-column: 1 / span 2;
  grid-row: 2;
}

.bigstat span {
  font-size: 3rem;
  color:#0073B9;
  display:inline-block;
  width:4em;
  text-align:right;
  margin-right:0.5rem;
}
.crm-container .dataTables_wrapper > table.compact thead th,
.crm-container .dataTables_wrapper > table.compact tbody td {
  padding-top:0.3rem;
  padding-bottom:0.3rem;
}
.dataTables_length {
  padding: 0.3rem 1rem;
}
.oddc-cellbarchart {
  position:relative;
}
.oddc-cellbarchart__bar {
  position:absolute;
  top:0;
  right:0;
  bottom:0;
  background: #BBE4FC; /* a light brand-hue blue */
}
.oddc-cellbarchart__text {
  position: relative;// to lift above absolute.
  padding:0 2px;
}
</style>{/literal}

<div class="oddc-layout">
<div class="oddc-layout__keystats">
  <h2>Subscribers</h2>

  <!-- /date range selectors -->
  <p class="bigstat">
    <span class="bigstat__stat">{$currentTotalUniqueSubscribers}</span> people subscribed.
  </p>
  <p class="bigstat">
    <span class="bigstat__stat">{$activeSubscribers}</span> active subscribers.
  </p>
  <p class="bigstat">
    <span class="bigstat__stat">{$activeSubscribersPc}%</span> active subscribers.
  </p>
</div>
<div class="oddc-layout__lists">
<h2>Mailing Groups</h2>
<!-- List of groups and counts of subscribers -->
<table id="subscribers-by-list" class="compact">
  <thead>
    <tr><th>List</th><th>Subscribers</th>
    <th>% total subscribers</th>
    </tr>
  </thead>
  <tbody>
  {foreach from=$selectedListCounts as key="group_id" item="row"}
    <tr> <td>{$row.title}</td> <td>{$row.count}</td><td>{$row.percent}</td> </tr>
  {/foreach}
  </tbody>
</table>

<!--  Form for editing selected mailing lists -->
<div style="background:white;padding:1rem;margin-bottom:1rem;">
  <a href id="toggleListEdit" >Edit mailing groups shown</a>
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
</div>
<div class="oddc-layout__mailings">
<h2>Mailing Stats</h2>
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

<!-- Main mailing stats table -->
<div>
  <table id="mailing-stats" class="compact">
  <thead>
  <tr>
    <th>Mailing</th>
    <th>Date</th>
    <th>Open Rate</th>
    <th>Donors (one off)</th>
    <th>Donors (new regular)</th>
    <th>Donors (total)</th>
    <th>£amount (one off)</th>
    <th>£amount (new regular)</th>
    <th>£amount (total)</th>
  </tr>
  </thead>
  <tbody>
    {foreach from=$mailings item="mailing" key="id"}
      <tr>
        <td>{$mailing.name}</td>
        <td>{$mailing.scheduled_date|truncate:16:"":true:true}</td>
        <td>{$mailing.opened_rate}</td>
        <td>{$mailing.one_off_people}</td>
        <td>{$mailing.regular_people}</td>
        <td>{$mailing.total_people}</td>
        <td>{$mailing.one_off_amount}</td>
        <td>{$mailing.regular_amount}</td>
        <td>{$mailing.total_amount}</td>
      </tr>
    {/foreach}
    </tbody>
  </table>
</div>
<!-- /Main mailing stats table -->
</div>
</div>

<script>{literal}
CRM.$(() => {

  const maxes = {/literal}{$mailingsMaxes}{literal};
  const maxesColMap = [
    null,
    null,
    maxes['opened_rate'],
    maxes['one_off_people'],
    maxes['regular_people'],
    maxes['total_people'],
    maxes['one_off_amount'],
    maxes['regular_amount'],
    maxes['total_amount'],
  ];

  const backgroundBarchart = function(data, type, row, meta) {

    if (type != 'display') {
      return data;
    }
    // Display case:

    const numericData = parseFloat(data.replace(/[^0-9.]+/g, ''));
    // Calculate total for this column.
    return '<div class="oddc-cellbarchart"><div class="oddc-cellbarchart__bar" style="width:'
      + (numericData*100/maxesColMap[meta.col]) + '%;"></div><div class="oddc-cellbarchart__text">' + data + '</div></div>';
  };


  CRM.$('#subscribers-by-list').dataTable({
    pageLength: 25,
    order: [[1, 'desc']],
    columnDefs: [
      { targets: 1, className: 'dt-right' },
      { targets: 2, render: backgroundBarchart }
    ]
  });

  CRM.$('#mailing-stats').dataTable({
    pageLength: 25,
    columnDefs: [
      { targets: 1, className: 'dt-right', },
      { targets: 2, width:'7%', className: 'dt-right',render: backgroundBarchart },
      { targets: 3, width:'7%', className: 'dt-right',render: backgroundBarchart },
      { targets: 4, width:'7%', className: 'dt-right',render: backgroundBarchart },
      { targets: 5, width:'7%', className: 'dt-right',render: backgroundBarchart },
      { targets: 6, width:'7%', className: 'dt-right',render: backgroundBarchart },
      { targets: 7, width:'7%', className: 'dt-right',render: backgroundBarchart },
      { targets: 8, width:'7%', className: 'dt-right',render: backgroundBarchart }
    ],
    order: [[1, 'desc']]
  });

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
