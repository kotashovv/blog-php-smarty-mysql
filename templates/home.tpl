{extends file="layout.tpl"}
{block name="title"}{$meta.title}{/block}
{block name="description"}{$meta.description}{/block}
{block name="content"}
<div class="container home">
  <section class="home__hero bento-grid" aria-labelledby="home-title">
    <div class="hero-photo"><img src="{$hero.image.src}" alt="{$hero.image.alt}" width="{$hero.image.width}" height="{$hero.image.height}"><div class="hero-photo__top"><span class="label">{$hero.issue}</span><span class="label label--dark">{$hero.imageLabel}</span></div><div class="hero-photo__caption"><p class="label">{$hero.captionLabel}</p><h2>{$hero.caption}</h2></div></div>
    <div class="home__intro"><div class="hero-panel"><p class="label">{$hero.eyebrow}</p><h1 id="home-title">{$hero.title}</h1><p>{$hero.description}</p><div class="hero-panel__action"><a class="button" href="{$hero.actionUrl}">{$hero.actionLabel} <span aria-hidden="true">→</span></a><span class="label">{$hero.updatedLabel}</span></div></div>
    <div class="hero-directory"><div class="hero-directory__chips"><span class="label">{$ui.categories}</span>{foreach $categoryChips as $chip}{include file="partials/category-chip.tpl" chip=$chip}{/foreach}</div><div class="hero-directory__meta"><strong>{$hero.summary}</strong><span class="label">{$hero.archive}</span></div></div></div>
  </section>
  {foreach $categories as $category}
  <section class="home__section"><header class="section-heading"><div><span class="label">{$category.eyebrow}</span><h2>{$category.title}</h2></div><a class="button button--secondary" href="{$category.url}">{$ui.allPosts} <span aria-hidden="true">→</span></a></header>
    {include file="partials/post-grid.tpl" posts=$category.posts gridClass='home__posts'}
  </section>
  {/foreach}
  {include file="partials/newsletter.tpl"}
</div>
{/block}
