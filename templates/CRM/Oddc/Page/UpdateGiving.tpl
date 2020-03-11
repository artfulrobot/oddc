<div class="update-giving" >
<h1>Your Donation</h1>

{if $is_test}
  <div style="border: solid 1pc #080;background-color:#efd;padding:1rem;margin:1rem 0;">
    This is a test recurring payment.
  </div>
{/if}

<p id="donation-current"><strong>Thanks, {$who}</strong> for your regular donation of {$giving.description}.</p>

<noscript>Sorry, this page requires Javascript to work.</noscript>
<div style="display:none" id="donation-upgrade">
  <p>To increase your giving, please enter the new regular amount below.</p>

  <p><label>New amount: £
    <input type="text" name="newAmount" value="{$suggestion}" style="display:inline-block; width: 5rem;"/>
    </label>
  </p>

  <p><button id="newAmountSubmit">Update my Direct Debit</button></p>

  <p id="donation-working"></p>
  <div>
    <p>Thank you – it really makes a difference. You increased regular donation will help us to:</p>
    <ul>
      <li>Invest more in digital and and physical security training, so our team can withstand increasing attacks.  </li>
      <li>Scale up our legal defence budget, so we can stand firm in the face of expensive legal threats – from lobbyists, corporations or governments.</li>
      <li>Commission more stories from brave reporters working in the toughest environments globally.</li>
    </ul>

    <p>We believe information should be free for everyone and free from the influence of companies or governments. That’s why we don’t have paywalls or advertising. But it means we rely on people like you contributing what you can to help fund our work.</p>
  </div>

</div>
<div id="donation-success"></div>
<script>
{literal}
(function($) {$(function() {
  // Show the form as we have JS installed.
  $('#donation-upgrade').show();

  var $btn = $('#newAmountSubmit');
  var $amount = $('input[name="newAmount"]');
  var $working = $('#donation-working');
  var $success = $('#donation-success');
  var $donationCurrent = $('#donation-current');

  function ready() {
    if (parseFloat($amount.val()) > 1) {
      console.log("enabled submit");
      $btn.prop('disabled', false);
    }
    else {
      console.log("disabled submit as amount not valid", $amount.val());
      $btn.prop('disabled', true);
    }
  }
  ready();

  $amount.on('input', ready);
  $btn.on('click', function(e) {
    $btn.prop('disabled', true);
    $amount.prop('disabled', true);
    $working.text('Please wait...')
        .css({color: '#0a0'})
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
        if (!r || !r.success) {
          $working.text("Sorry, there was an error trying to update your donation. Please contact us.")
          .css({color: '#a00'});
        }
        else {
          $donationCurrent.hide();
          $('#donation-upgrade').hide();
          $success.text("Successfully updated your donation. Thanks!");
        }
      },
      function(e, r) {
        console.error(e, r);
        $working.text("Sorry, there was an error trying to update your donation.")
        .css({color: '#a00'});
      });
  });

});})(jQuery);
{/literal}
</script>
</div>
