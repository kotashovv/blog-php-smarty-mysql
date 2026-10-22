{extends file="layout.tpl"}
{block name="title"}{$meta.title}{/block}
{block name="description"}{$meta.description}{/block}
{block name="content"}<article class="post" data-post-id="{$post.id}">
  <header class="container post__header">{include file="partials/breadcrumbs.tpl"}<div class="post__heading"><div>{foreach $post.categories as $chip}{include file="partials/category-chip.tpl" chip=$chip}{/foreach}</div><h1>{$post.title}</h1><p class="post__lead">{$post.excerpt}</p><div class="post__meta"><time datetime="{$post.datetime}">{$post.date}</time> / <span class="post__views">{$post.viewsLabel}</span></div></div></header>
  {if $post.image}<div class="container post__figure"><img class="post__cover" src="{$post.image.src}" alt="{$post.image.alt}" width="{$post.image.width}" height="{$post.image.height}"></div>{/if}
  <div class="post__body">{$post.bodyHtml nofilter}</div>
</article>
<section class="container post-related"><header class="section-heading"><h2>{$ui.relatedPosts}</h2></header><div class="bento-grid post-related__grid">{foreach $relatedPosts as $related}{assign var=toneIndex value=($related@index+1) % 4}{include file="partials/post-card.tpl" post=$related tone=$tones[$toneIndex] variant='default'}{/foreach}</div></section>
{/block}
