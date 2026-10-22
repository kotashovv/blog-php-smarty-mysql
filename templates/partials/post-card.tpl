<article class="card card--{$tone}{if $variant == 'large'} card--large{/if}{if !empty($post.image)} card--photo{/if}{if !empty($editorial)} card--editorial{/if}">
  {if !empty($post.image)}<img class="card__image" src="{$post.image.src}" alt="{$post.image.alt}" loading="lazy" width="{$post.image.width}" height="{$post.image.height}">{/if}
  <div class="card__body">
    <div class="card__top"><span class="label">{$post.label}</span><span>{$post.stat}</span></div>
    <div class="card__copy"><h3 class="card__title"><a href="{$post.url}">{$post.title}</a></h3>{if !empty($post.excerpt)}<p class="card__excerpt">{$post.excerpt}</p>{/if}</div>
    <div class="card__bottom"><p>{$post.category} / <time datetime="{$post.datetime}">{$post.date}</time> / {$post.viewsLabel}</p>{if $variant == 'large'}<a class="button" href="{$post.url}">{$ui.readArticle} <span aria-hidden="true">→</span></a>{/if}</div>
  </div>
</article>
