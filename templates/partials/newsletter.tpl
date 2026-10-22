{if !empty($newsletter)}<section class="newsletter" id="newsletter" aria-labelledby="newsletter-title">
  <div><span class="label">{$newsletter.label}</span><h2 id="newsletter-title">{$newsletter.title}</h2><p>{$newsletter.description}</p></div>
  <form class="newsletter__form" action="{$newsletter.action}" method="post">
    <input type="hidden" name="csrf_token" value="{$csrfToken}">
    <label for="newsletter-email">{$newsletter.emailLabel}</label><input id="newsletter-email" name="email" type="email" autocomplete="email" required placeholder="{$newsletter.placeholder}">
    <button class="button" type="submit">{$newsletter.submitLabel}</button><p>{$newsletter.consent}</p>
  </form>
</section>{/if}
