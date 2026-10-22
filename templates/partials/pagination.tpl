{if $pages > 1}
<nav class="pagination" aria-label="{$ui.pagination}">
  <div class="pagination__links">
    {if $page > 1}<a class="pagination__link" rel="prev" href="{$baseUrl}?page={$page-1}&amp;sort={$sort|escape:'url'}">{$ui.previous}</a>{/if}
    {for $number=1 to $pages}<a class="pagination__link{if $number == $page} pagination__link--active{/if}" href="{$baseUrl}?page={$number}&amp;sort={$sort|escape:'url'}"{if $number == $page} aria-current="page"{/if}>{$number}</a>{/for}
    {if $page < $pages}<a class="pagination__link" rel="next" href="{$baseUrl}?page={$page+1}&amp;sort={$sort|escape:'url'}">{$ui.next}</a>{/if}
  </div><p>{$ui.page} {$page} / {$pages}</p>
</nav>
{/if}
