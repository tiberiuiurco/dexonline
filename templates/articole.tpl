{extends file="layout.tpl"}

{block name=title}Articole lingvistice{/block}

{block name=content}
  <h1>Articole lingvistice</h1>

  <div id="linguisticArticles">
    {foreach from=$wikiTitles key=k item=v}
      <h3>{$k|escape:'html'}</h3>
      <ul>
        {foreach from=$v item=titlePair}
          <li>
            <a href="{$wwwRoot}articol/{$titlePair[1]}">{$titlePair[0]}</a>
          </li>
        {/foreach}
      </ul>
    {/foreach}
  </div>
{/block}
