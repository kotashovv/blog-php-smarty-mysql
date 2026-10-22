{extends file="layout.tpl"}
{block name="title"}{$meta.title}{/block}
{block name="description"}{$meta.description}{/block}
{block name="content"}<div class="container category">
  {include file="partials/breadcrumbs.tpl"}
  <header class="category-hero category__hero"><h1>{$category.title}</h1><p>{$category.description}</p></header>
  <div class="listing" data-listing>
  <div class="category__toolbar"><div><strong>{$category.totalLabel}</strong><span class="label"> / {$ui.page} {$page}/{$pages}</span></div>{include file="partials/sort-switcher.tpl"}</div>
  {include file="partials/post-grid.tpl" posts=$posts gridClass='category__posts'}
  {include file="partials/pagination.tpl" page=$page pages=$pages baseUrl=$baseUrl sort=$sort dir=$dir}
  </div>
</div>{/block}
