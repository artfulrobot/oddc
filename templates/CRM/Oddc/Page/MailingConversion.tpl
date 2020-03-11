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
<h2>Definitions</h2>
<p>The <strong>Recipients</strong> is the count of the number of people to whom the email was successfully delivered. So it is basically intended recipients minus bounces.</p>
<p>A <strong>Contact Conversion</strong> means an individual who <em>was sent the mailing</em> and made one <em>or more</em> donations linked to the mailing. One contact who made several donations counts as one conversion.</p>
<p>A <strong>Contribution Conversion</strong> means a donation belonging to someone who <em>was sent the mailing</em> that is linked to the mailing. One contact who made 3 donations counts as 3 conversions. This will go up over time if they set up a recurring donation.</p>
<p>The <strong>Conversion Rate %</strong> is the number of conversions divided by the number of recipients.</p>
<p>Note: these figures may differ from those on the <a href="https://support.opendemocracy.net/civicrm/dataviz/odd" >od Donations explorer</a> in the case that someone made a payment linked to this mailing but who was not sent the mailing themself (e.g. their friend forwarded it).</p>
{/literal}
