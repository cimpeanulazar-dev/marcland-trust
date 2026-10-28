<div class="panel" id="marclandtrust-editor" data-config="{$trust_editor_data|escape:'html':'UTF-8'}">
    <h3>{l s='MARCLAND Trust Signals' mod='marclandtrust'}</h3>
    <p class="alert alert-warning">{l s='All shops share these zones. Messages are plain text, up to 500 characters per language. Empty translations are removed without a language fallback.' mod='marclandtrust'}</p>
    <noscript>{l s='JavaScript is required to edit trust signal zones.' mod='marclandtrust'}</noscript>
    <form method="post" enctype="multipart/form-data" action="{$trust_form_action|escape:'html':'UTF-8'}" id="marclandtrust-form">
        <input type="hidden" name="trust_token" value="{$trust_token|escape:'html':'UTF-8'}">
        <input type="hidden" name="trust_revision" value="{$trust_revision|escape:'html':'UTF-8'}">
        <label for="marclandtrust-language">{l s='Editing language' mod='marclandtrust'}</label>
        <select id="marclandtrust-language" class="form-control"></select>
        <p id="marclandtrust-status" aria-label="{l s='Signal count' mod='marclandtrust'}" role="status" aria-live="polite"></p>
        <div id="marclandtrust-items"></div>
        <div id="marclandtrust-serialized"></div>
        <button type="button" id="marclandtrust-add-item" class="btn btn-default">+ {l s='Add signal' mod='marclandtrust'}</button>
        <button type="submit" name="submitMarclandTrust" value="1" class="btn btn-primary">{l s='Save' mod='marclandtrust'}</button>
    </form>
    <template id="marclandtrust-zone-template">
        <fieldset class="marclandtrust-item">
            <legend>{l s='Trust Signal' mod='marclandtrust'} <span data-role="number"></span></legend>
            <div class="marclandtrust-actions">
                <button type="button" data-action="up">↑ {l s='Move up' mod='marclandtrust'}</button>
                <button type="button" data-action="down">↓ {l s='Move down' mod='marclandtrust'}</button>
                <button type="button" data-action="remove">{l s='Remove' mod='marclandtrust'}</button>
            </div>
            <label><input type="checkbox" data-field="active"> {l s='Globally active' mod='marclandtrust'}</label>
            <label>{l s='Localized message' mod='marclandtrust'} <input type="text" class="form-control" data-field="text" placeholder="{l s='No translation yet — enter a message' mod='marclandtrust'}"></label>
            <label>{l s='Icon source' mod='marclandtrust'}
                <select data-field="icon_type" class="form-control">
                    <option value="predefined">{l s='Predefined icon' mod='marclandtrust'}</option>
                    <option value="custom">{l s='Upload custom SVG' mod='marclandtrust'}</option>
                </select>
            </label>
            <div data-role="icons" class="marclandtrust-icons" role="group" aria-label="{l s='Predefined icons' mod='marclandtrust'}">
                {foreach ['truck'=>'Shipping', 'returns'=>'Returns', 'shield'=>'Warranty', 'lock'=>'Secure payment', 'support'=>'Customer support', 'verified'=>'Verified product'] as $icon => $label}
                    <button type="button" data-icon="{$icon|escape:'html':'UTF-8'}" aria-pressed="false">
                        <img width="24" height="24" alt="" data-icon-image="{$icon|escape:'html':'UTF-8'}">
                        {if $icon == 'truck'}{l s='Shipping' mod='marclandtrust'}
                        {elseif $icon == 'returns'}{l s='Returns' mod='marclandtrust'}
                        {elseif $icon == 'shield'}{l s='Warranty' mod='marclandtrust'}
                        {elseif $icon == 'lock'}{l s='Secure payment' mod='marclandtrust'}
                        {elseif $icon == 'support'}{l s='Customer support' mod='marclandtrust'}
                        {else}{l s='Verified product' mod='marclandtrust'}{/if}
                    </button>
                {/foreach}
            </div>
            <div data-role="custom" hidden>
                <label>{l s='Select SVG file' mod='marclandtrust'} <input type="file" accept=".svg,image/svg+xml" data-field="file"></label>
                <p>{l s='SVG only, maximum 100 KB. Basic shapes and paths are supported. Scripts, styles, external references and other unsupported features are rejected. Select files again after a failed submission.' mod='marclandtrust'}</p>
                <p data-role="file-status" role="status"></p>
                <img data-role="custom-preview" width="48" height="48" alt="{l s='Saved custom icon' mod='marclandtrust'}" hidden>
            </div>
            <p class="help-block">{l s='Choose display locations below. List buttons organize the editor only; each location has its own display order.' mod='marclandtrust'}</p>
            {foreach ['home', 'product', 'checkout'] as $placement}
                <details class="marclandtrust-placement" data-placement="{$placement|escape:'html':'UTF-8'}">
                    <summary>
                        {if $placement == 'home'}{l s='Homepage' mod='marclandtrust'}
                        {elseif $placement == 'product'}{l s='Product page' mod='marclandtrust'}
                        {else}{l s='Checkout' mod='marclandtrust'}{/if}
                        — <span data-p-role="status" data-enabled="{l s='Selected' mod='marclandtrust'}" data-disabled="{l s='Not selected' mod='marclandtrust'}"></span>
                    </summary>
                    <label><input type="checkbox" data-p-field="enabled"> {l s='Show at this location' mod='marclandtrust'}</label>
                    <div data-p-role="settings">
                        <label>{l s='Display order (lower numbers first)' mod='marclandtrust'} <input type="number" min="0" max="4294967295" step="1" data-p-field="position" class="form-control"></label>
                        <label>{l s='Click action' mod='marclandtrust'}
                            <select data-p-field="link_type" class="form-control">
                                <option value="none">{l s='No link' mod='marclandtrust'}</option>
                                <option value="cms">{l s='CMS page' mod='marclandtrust'}</option>
                                {if $placement != 'checkout'}<option value="anchor">{l s='Anchor on this page' mod='marclandtrust'}</option>{/if}
                            </select>
                        </label>
                        <label data-p-role="cms-label">{l s='CMS page' mod='marclandtrust'} <select data-p-field="cms" class="form-control"><option value="">{l s='Select a CMS page' mod='marclandtrust'}</option></select></label>
                        <label data-p-role="anchor-label">{l s='Anchor identifier on this page (without #)' mod='marclandtrust'} <input type="text" data-p-field="anchor" class="form-control" pattern="[A-Za-z][A-Za-z0-9_-]*" maxlength="128"></label>
                        {if $placement == 'checkout'}<p>{l s='CMS links open a new tab so checkout stays open. Anchors are not supported here.' mod='marclandtrust'}</p>
                        {else}<p>{l s='Anchors must exist in this page’s theme. They never navigate to another page.' mod='marclandtrust'}</p>{/if}
                        <p>{l s='An unavailable CMS destination is displayed as plain text.' mod='marclandtrust'}</p>
                    </div>
                </details>
            {/foreach}
        </fieldset>
    </template>
</div>
