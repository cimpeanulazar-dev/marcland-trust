<div class="panel" id="marclandtrust-editor" data-config="{$trust_editor_data|escape:'html':'UTF-8'}">
    <h3>{l s='MARCLAND Trust Signals' mod='marclandtrust'}</h3>
    <p class="alert alert-warning">{l s='Preview editor: Save submits the form but does not persist changes yet. Custom SVG files are not uploaded. All shops share these zones.' mod='marclandtrust'}</p>
    <noscript>{l s='JavaScript is required to edit trust signal zones.' mod='marclandtrust'}</noscript>
    <form method="post" id="marclandtrust-form">
        <label for="marclandtrust-language">{l s='Editing language' mod='marclandtrust'}</label>
        <select id="marclandtrust-language" class="form-control"></select>
        <p id="marclandtrust-status" aria-label="{l s='Zone count' mod='marclandtrust'}" role="status" aria-live="polite"></p>
        <div id="marclandtrust-items"></div>
        <div id="marclandtrust-serialized"></div>
        <button type="button" id="marclandtrust-add-item" class="btn btn-default">+ {l s='Add zone' mod='marclandtrust'}</button>
        <button type="submit" name="submitMarclandTrust" value="1" class="btn btn-primary">{l s='Save (persistence pending)' mod='marclandtrust'}</button>
    </form>
    <template id="marclandtrust-zone-template">
        <fieldset class="marclandtrust-item">
            <legend>{l s='Trust signal zone' mod='marclandtrust'} <span data-role="number"></span></legend>
            <div class="marclandtrust-actions">
                <button type="button" data-action="up">↑ {l s='Move up' mod='marclandtrust'}</button>
                <button type="button" data-action="down">↓ {l s='Move down' mod='marclandtrust'}</button>
                <button type="button" data-action="remove">{l s='Remove' mod='marclandtrust'}</button>
            </div>
            <label><input type="checkbox" data-field="active"> {l s='Enabled' mod='marclandtrust'}</label>
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
                <p>{l s='Local selection only. Secure upload and validation will be implemented later.' mod='marclandtrust'}</p>
                <p data-role="file-status" role="status"></p>
            </div>
            <label>{l s='Link destination' mod='marclandtrust'}
                <select data-field="link_type" class="form-control">
                    <option value="none">{l s='No link' mod='marclandtrust'}</option>
                    <option value="cms">{l s='CMS page' mod='marclandtrust'}</option>
                    <option value="anchor">{l s='Internal page anchor' mod='marclandtrust'}</option>
                </select>
            </label>
            <label data-role="cms-label">{l s='CMS page' mod='marclandtrust'} <select data-field="cms" class="form-control"><option value="">{l s='Select a CMS page' mod='marclandtrust'}</option></select></label>
            <label data-role="anchor-label">{l s='Anchor identifier (without #)' mod='marclandtrust'} <input type="text" data-field="anchor" class="form-control" pattern="[A-Za-z][A-Za-z0-9_-]*" maxlength="128" placeholder="product-details"></label>
        </fieldset>
    </template>
</div>
