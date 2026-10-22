<form class="subscribe-form" action="{$newsletter.action}" method="post">
  <input type="hidden" name="csrf_token" value="{csrf_token}">
  <label class="label" for="subscribe-email-{$formId}">{$newsletter.emailLabel}</label>
  <div class="subscribe-form__row">
    <input id="subscribe-email-{$formId}" name="email" type="email" autocomplete="email" inputmode="email" maxlength="254" required placeholder="{$newsletter.placeholder}">
    <button class="button" type="submit">{$newsletter.submitLabel} <span aria-hidden="true">→</span></button>
  </div>
  <p class="subscribe-form__note">{$newsletter.consent}</p>
</form>
