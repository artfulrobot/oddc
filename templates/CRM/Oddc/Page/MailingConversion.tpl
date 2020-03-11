<p>Showing mailings since {$fromHuman}.

 <a href="?from=today%20-%206%20month" >Last month</a>
 | <a href="?from=today%20-%203%20month" >Last 3 months</a>
 | <a href="?from=today%20-%206%20months" >Last 6 months</a>
 | <a href="?from=today%20-%201%20year" >Last year</a>
 | <a href="?from=today%20-%202%20year" >Last 2 years</a>
 | <a href="{$downloadUrl}">Download CSV</a>
</p>

<table id="odmailingconversionstats"></table>
{literal}
<script >
document.addEventListener('DOMContentLoaded', () => {
  CRM.$('#odmailingconversionstats').dataTable({
    data: {/literal}{$statsJson}{literal},
    order: [[0, 'desc']],
    searching: true,
    columns: [
      { data: 'mailingDate', visible: false},
      { data: 'dateHuman', title: 'Date', orderData: [0]},
      { data: 'mailingName', title: 'Mailing'},
      { data: 'recipients', title: 'Recipients', className: 'right'},
      { data: 'contactConversions', title: "Contact Conv", className: 'right'},
      { data: 'contactConversionRate', title: "Contact CR%", className: 'right'},
      { data: 'contributionConversions', title: "Contribution Conv", className: 'right'},
      { data: 'contributionConversionRate', title: "Contribution CR%", className: 'right'},
      { data: 'totalRaised', title: "Total £", className: 'right'},
    ],
  });
});

</script>
{/literal}
