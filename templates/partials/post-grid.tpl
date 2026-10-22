<div class="bento-grid {$gridClass}">
  {foreach $posts as $post}
    {assign var=toneIndex value=$post@index % 4}
    {include file="partials/post-card.tpl" post=$post tone=$tones[$toneIndex] variant=($post@first ? 'large' : 'default') editorial=($gridClass == 'home__posts')}
  {foreachelse}<p>{$ui.noPosts}</p>{/foreach}
</div>
