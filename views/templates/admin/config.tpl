<div class="panel">
    <h3>MARCLAND Trust Signals</h3>

    <p>Module configuration</p>
    <form action="" method="post">
        <ul id="marclandtrust-items">
            {foreach from=$trust_items item=item}
                <li class="marclandtrust-item">
                    {$item.label|escape:'html':'UTF-8'}
                </li>
                {foreachelse}
                <li>No trust signals configured yet.</li>
            {/foreach}
        </ul>
        <button type="submit" name="submitMarclandTrust" value="1">Save</button>
    </form>



    <button
            type="button"
            id="marclandtrust-add-item"
            class="btn btn-primary"
    >
        <i class="icon-plus"></i>
        Add New Trust Signal
    </button>
</div>