{extends file="layout.tpl"}
{block name="title"}{$meta.title}{/block}
{block name="description"}{$meta.description}{/block}
{block name="content"}<div class="container notice-page">
  <section class="notice{if !$subscribe.ok} notice--error{/if}" aria-labelledby="notice-title">
    <span class="label">{$subscribe.label}</span>
    <h1 id="notice-title">{$subscribe.title}</h1>
    <p role="status">{$subscribe.message}</p>
    <div><a class="button" href="{$subscribe.backUrl}">{$subscribe.backLabel} <span aria-hidden="true">→</span></a></div>
  </section>
</div>{/block}
