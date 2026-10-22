{extends file="layout.tpl"}
{block name="title"}{$meta.title}{/block}
{block name="description"}{$meta.description}{/block}
{block name="content"}<article class="post">
  <header class="container post__header">{include file="partials/breadcrumbs.tpl"}<div class="post__heading"><div><span class="chip">{$post.category}</span> <span class="label">{$post.label}</span></div><h1>{$post.title}</h1><p class="post__lead">{$post.excerpt}</p><div class="post__meta"><span>{$post.author}</span> / <span>{$post.authorRole}</span> / <time datetime="{$post.datetime}">{$post.date}</time> / <span>{$post.readingLabel}</span> / <span>{$post.viewsLabel}</span></div></div></header>
  <figure class="container post__figure"><img class="post__cover" src="{$post.image.src}" alt="{$post.image.alt}" width="{$post.image.width}" height="{$post.image.height}"><figcaption>{$post.caption}</figcaption></figure>
  <div class="post__body">{$post.bodyHtml nofilter}</div>
  <footer class="post__actions"><div>{foreach $post.tags as $chip}{include file="partials/category-chip.tpl" chip=$chip}{/foreach}</div><div>{foreach $post.actions as $action}<a class="button" href="{$action.url}">{$action.label}</a>{/foreach}</div></footer>
</article>
<section class="container post-related"><header class="section-heading"><h2>{$ui.relatedPosts}</h2><span class="label">{$post.relatedLabel}</span></header><div class="bento-grid post-related__grid">{foreach $relatedPosts as $related}{assign var=toneIndex value=($related@index+1) % 4}{include file="partials/post-card.tpl" post=$related tone=$tones[$toneIndex] variant='default'}{/foreach}</div></section>
{/block}
