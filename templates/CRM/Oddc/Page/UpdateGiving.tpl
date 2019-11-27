<div class="update-giving" >
<h1>Your Donation</h1>

{if $is_test}
  <div style="border: solid 1pc #080;background-color:#efd;padding:1rem;margin:1rem 0;">
    This is a test recurring payment.
  </div>
{/if}

<p><strong>Thanks, {$who}</strong> for your regular donation of {$giving.description}.</p>

<noscript>Sorry, this page requires Javascript to work.</noscript>
<div style="display:none" id="donation-upgrade">
  <p>To increase your giving, please enter the new regular amount below.</p>

  <p><label>New amount: £
    <input type="text" name="newAmount" value="{$suggestion}" style="display:inline-block; width: 5rem;"/>
    </label>
  </p>

  <p><button id="newAmountSubmit">Update my Direct Debit</button></p>

  <p id="donation-working"></p>

</div>
<div id="donation-success"></div>
<script>
{literal}
(function($) {$(function() {
  // Show the form as we have JS installed.
  $('#donation-upgrade').show();

  var $btn = $('#newAmountSubmit');
  var $amount = $('#newAmount');
  var $working = $('#donation-working');
  var $success = $('#donation-success');

  function ready() {
    if (parseFloat($amount.val()) > 1) {
      $btn.prop('disabled', false);
    }
    else {
      $btn.prop('disabled', true);
    }
  }
  ready();

  $amount.on('input', ready);
  $btn.on('click', function(e) {
    $btn.prop('disabled', true);
    $amount.prop('disabled', true);
    $working.text('Please wait...')
        .style({color: '#0a0'})
        .show();
    $.ajax({
      method: 'POST',
      data: {
        cid: {/literal}{$contact_id}{literal},
        cs: '{/literal}{$checksum}{literal}',
        amount: $amount.val()
      }
    }).then(
      function(r) {
        $('#donation-upgrade').hide();
        $success.text("Successfully updated your donation. Thanks!");
      },
      function(e, r) {
        console.error(e, r);
        $working.text("Sorry, there was an error trying to update your donation.")
        .style({color: '#a00'});
      });
  });

});})(jQuery);
{/literal}
</script>
</div>
