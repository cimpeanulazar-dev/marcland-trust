{if !empty($marcland_trust_zones)}
<ul class="marclandtrust-signals">
    {foreach $marcland_trust_zones as $zone}
        <li class="marclandtrust-signals__zone">
            {if $zone.icon}<img class="marclandtrust-signals__icon" src="{$zone.icon|escape:'html':'UTF-8'}" width="24" height="24" alt="" aria-hidden="true">{/if}
            {if $zone.href}
                <a class="marclandtrust-signals__text" href="{$zone.href|escape:'html':'UTF-8'}">{$zone.text|escape:'html':'UTF-8'}</a>
            {else}
                <span class="marclandtrust-signals__text">{$zone.text|escape:'html':'UTF-8'}</span>
            {/if}
        </li>
    {/foreach}
</ul>
{/if}
