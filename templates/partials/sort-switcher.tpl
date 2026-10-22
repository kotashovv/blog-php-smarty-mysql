<nav class="sort" aria-label="{$ui.sortLabel}">
  {foreach $sortLinks as $link}<a class="sort__link{if $link.active} sort__link--active{/if}" href="{$link.url}" title="{$link.title}"{if $link.active} aria-current="true"{/if}>{$link.label} <span class="sort__arrow" aria-hidden="true">{$link.arrow}</span></a>{/foreach}
</nav>
