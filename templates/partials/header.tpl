<header class="header">
  <div class="container header__inner">
    <a class="header__brand" href="{$site.homeUrl}">{$site.name}</a>
    <nav class="header__nav" aria-label="{$ui.mainNavigation}">
      {foreach $navigation as $item}<a class="header__link" href="{$item.url}"{if $item.active} aria-current="page"{/if}>{$item.label}</a>{/foreach}
    </nav>
    <details class="header__mobile">
      <summary>{$ui.menu}</summary>
      <nav aria-label="{$ui.mainNavigation}">{foreach $navigation as $item}<a class="header__link" href="{$item.url}"{if $item.active} aria-current="page"{/if}>{$item.label}</a>{/foreach}</nav>
    </details>
  </div>
</header>
