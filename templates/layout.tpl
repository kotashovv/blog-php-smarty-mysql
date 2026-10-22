<!doctype html>
<html lang="{$site.language}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{block name="title"}{$meta.title}{/block}</title>
  <meta name="description" content="{block name='description'}{$meta.description}{/block}">
  <link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
  <a class="skip-link" href="#content">{$ui.skipContent}</a>
  {block name="header"}{include file="partials/header.tpl"}{/block}
  <main id="content" class="site-main">{block name="content"}{/block}</main>
  {block name="footer"}{include file="partials/footer.tpl"}{/block}
</body>
</html>
