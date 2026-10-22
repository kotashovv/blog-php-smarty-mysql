<nav class="sort" aria-label="{$ui.sortLabel}">
  {foreach $sortOptions as $option}<a class="sort__link{if $sort == $option.value} sort__link--active{/if}" href="{$baseUrl}?sort={$option.value|escape:'url'}"{if $sort == $option.value} aria-current="true"{/if}>{$option.label}</a>{/foreach}
</nav>
