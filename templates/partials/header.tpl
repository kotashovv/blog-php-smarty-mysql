<header class="header">
  <div class="container header__inner">
    <a class="header__brand" href="{$site.homeUrl}">{$site.name}</a>
    <nav class="header__nav" aria-label="{$ui.mainNavigation}">
      {foreach $navigation as $item}<a class="header__link" href="{$item.url}"{if $item.active} aria-current="page"{/if}>{$item.label}</a>{/foreach}
      {if $navigationMore.items}<div class="header__more">
        <button class="header__link header__more-toggle" type="button" aria-haspopup="true"{if $navigationMore.active} aria-current="true"{/if}>{$navigationMore.label} <span aria-hidden="true">↓</span></button>
        <div class="header__more-menu"><div class="header__more-list" role="group" aria-label="{$ui.moreLabel}">
          {foreach $navigationMore.items as $item}<a class="header__link" href="{$item.url}"{if $item.active} aria-current="page"{/if}>{$item.label}</a>{/foreach}
        </div></div>
      </div>{/if}
    </nav>
    <button class="button header__subscribe" type="button" popovertarget="subscribe-popup">{$ui.subscribe}</button>
    <button class="header__menu" type="button" popovertarget="mobile-menu" aria-label="{$ui.openMenu}">{$ui.menu}</button>
  </div>
</header>
<nav class="header__panel" id="mobile-menu" popover aria-label="{$ui.mainNavigation}">
  {foreach $navigationAll as $item}<a class="header__link" href="{$item.url}"{if $item.active} aria-current="page"{/if}>{$item.label}</a>{/foreach}
</nav>
{include file="partials/subscribe-popup.tpl"}
