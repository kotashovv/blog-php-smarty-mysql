<footer class="footer"><div class="container footer__inner">
  <a class="footer__brand" href="{$site.homeUrl}">{$site.name}</a><p>{$site.copyright}</p>
  <nav class="footer__nav" aria-label="{$ui.footerNavigation}">{foreach $footerLinks as $link}<a href="{$link.url}">{$link.label}</a>{/foreach}</nav>
</div></footer>
