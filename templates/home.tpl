{extends file="layout.tpl"}
{block name="title"}{$meta.title}{/block}
{block name="description"}{$meta.description}{/block}
{block name="content"}
<div class="container home">
  {if $hero}
  <section class="home__hero bento-grid" aria-labelledby="home-title">
    <div class="hero-photo">{if $hero.image}<img fetchpriority="high" decoding="async" src="{$hero.image.src}" alt="{$hero.image.alt}" width="{$hero.image.width}" height="{$hero.image.height}">{/if}</div>
    <div class="home__intro">
      <div class="hero-panel"><h1 id="home-title"><a href="{$hero.url}">{$hero.title}</a></h1><p>{$hero.excerpt}</p><div class="hero-panel__action"><time datetime="{$hero.datetime}">{$hero.date}</time><span class="label">{$hero.viewsLabel}</span></div></div>
      <div class="hero-directory"><div class="hero-directory__chips"><span class="label">{$ui.categories}</span>{foreach $categoryChips as $chip}{include file="partials/category-chip.tpl" chip=$chip}{/foreach}</div></div>
    </div>
  </section>
  {else}<h1>{$site.name}</h1><p>{$ui.noPosts}</p>{/if}
  {foreach $categories as $category}
  <section class="home__section" data-category-id="{$category.id}"><header class="section-heading"><h2>{$category.title}</h2><a class="button button--secondary" href="{$category.url}">{$ui.allPosts} <span aria-hidden="true">→</span></a></header>
    {include file="partials/post-grid.tpl" posts=$category.posts gridClass='home__posts'}
  </section>
  {if $category@iteration == $newsletterAfter}{include file="partials/newsletter.tpl"}{/if}
  {/foreach}
  {if !$categories}{include file="partials/newsletter.tpl"}{/if}
</div>
{/block}
