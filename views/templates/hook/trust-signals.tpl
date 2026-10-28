{if !empty($marcland_trust_zones)}
<ul class="marclandtrust-signals marclandtrust-signals--{$marcland_trust_placement|default:'product'|escape:'html':'UTF-8'}">
    {foreach $marcland_trust_zones as $zone}
        <li class="marclandtrust-signals__zone">
            {if $zone.icon}<img class="marclandtrust-signals__icon" src="{$zone.icon|escape:'html':'UTF-8'}" width="24" height="24" alt="" aria-hidden="true">{/if}
            {if $zone.href}
                <a class="marclandtrust-signals__text" href="{$zone.href|escape:'html':'UTF-8'}"{if !empty($zone.new_tab)} target="_blank" rel="noopener noreferrer"{/if}>{$zone.text|escape:'html':'UTF-8'}{if !empty($zone.new_tab)} <span class="marclandtrust-signals__new-window">{l s='(opens in a new tab)' mod='marclandtrust'}</span>{/if}</a>
            {else}
                <span class="marclandtrust-signals__text">{$zone.text|escape:'html':'UTF-8'}</span>
            {/if}
        </li>
    {/foreach}
</ul>
{/if}
