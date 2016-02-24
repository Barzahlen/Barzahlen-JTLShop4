<iframe src="{$paymentSlipLink}" width="0" height="1" frameborder="0"></iframe>
<img src="http://cdn.barzahlen.de/images/barzahlen_logo.png" height="57" width="168" alt="" style="padding:0; margin:0; margin-bottom: 10px;"/>
<hr/>

<br/>
<div style="width:100%;">
  <div style="position: relative; float: left; width: 180px; text-align: center;">
    <a href="{$paymentSlipLink}" target="_blank" style="color: #63A924; text-decoration: none; font-size: 1.2em;">
      <img src="http://cdn.barzahlen.de/images/barzahlen_checkout_success_payment_slip.png" height="192" width="126" alt="" style="margin-bottom: 5px;"/><br/>
      <strong>Download PDF</strong>
    </a>
  </div>

  <span style="font-weight: bold; color: #63A924; font-size: 1.5em;">{$oPlugin->oPluginSprachvariableAssoc_arr.success_heading}</span>
  <p>{$infotext1}</p>
  <p>{$expirationNotice}</p>
  <div style="width:100%;">
    <div style="position: relative; float: left; width: 50px;"><img src="http://cdn.barzahlen.de/images/barzahlen_mobile.png" height="52" width="41" alt="" style="float: left;"/></div>
    <p>{$infotext2}</p>
  </div>

  <br style="clear:both;" /><br/>
</div>
<hr/><br/>