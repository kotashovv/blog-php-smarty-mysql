<div class="subscribe-popup" id="subscribe-popup" popover aria-labelledby="subscribe-popup-title">
  <div class="subscribe-popup__bar">
    <span class="label">{$newsletter.label}</span>
    <button class="subscribe-popup__close" type="button" popovertarget="subscribe-popup" popovertargetaction="hide">{$ui.close} <span aria-hidden="true">×</span></button>
  </div>
  <h2 id="subscribe-popup-title">{$newsletter.title}</h2>
  <p>{$newsletter.description}</p>
  {include file="partials/subscribe-form.tpl" formId="popup"}
</div>
