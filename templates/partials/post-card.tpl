<article class="card card--{$tone}{if $variant == 'large'} card--large{/if}{if !empty($post.image)} card--photo{/if}" data-post-id="{$post.id}">
  {if !empty($post.image)}<a class="card__media" href="{$post.url}" aria-label="{$post.title}"><img class="card__image" src="{$post.image.src}" alt="{$post.image.alt}" loading="lazy" width="{$post.image.width}" height="{$post.image.height}"></a>{/if}
  <div class="card__body">
    <div class="card__top">{foreach $post.categories as $category}<a class="label" href="{$category.url}">{$category.label}</a>{/foreach}</div>
    <div class="card__copy"><h3 class="card__title"><a href="{$post.url}">{$post.title}</a></h3>{if !empty($post.excerpt)}<p class="card__excerpt">{$post.excerpt}</p>{/if}</div>
    <div class="card__bottom"><p><time datetime="{$post.datetime}">{$post.date}</time> / {$post.viewsLabel}</p></div>
  </div>
</article>
