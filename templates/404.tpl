{extends file="layout.tpl"}
{block name="title"}{$meta.title}{/block}
{block name="description"}{$meta.description}{/block}
{block name="header"}{/block}
{block name="footer"}{/block}
{block name="content"}<div class="error-page">
  <div class="error-page__strip label"><span>{$error.systemLabel}</span><span>{$error.archiveLabel}</span></div>
  <section class="error-page__panel"><div class="error-page__strip"><span class="label">{$error.label}</span><span class="label">{$error.codeLabel}</span></div><h1>{$error.code}</h1><h2>{$error.title}</h2><p>{$error.description}</p><div class="error-page__action"><a class="button" href="{$site.homeUrl}">{$error.homeLabel} /&gt;</a><span class="label">{$error.statusLabel}</span></div></section>
  <div class="error-page__strip label"><span>{$error.navigationLabel}</span><span>{$error.categoriesLabel}</span></div>
  <nav class="error-page__links" aria-label="{$ui.mainNavigation}">{foreach $error.categories as $chip}{assign var=toneIndex value=$chip@index % 4}<a class="error-page__link error-page__link--{$tones[$toneIndex]}" href="{$chip.url}"><span class="label">{$chip.index} /</span><strong>{$chip.label}</strong></a>{/foreach}</nav>
  <footer class="error-page__strip label"><span>{$error.footerLabel}</span><span>{$error.footerCode}</span></footer>
</div>{/block}
