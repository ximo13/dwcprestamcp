{*
 * DWC PrestaShop MCP - configuration screen.
 * @license MIT
 *}
<div class="panel">
    <h3><i class="icon icon-plug"></i> {l s='DWC PrestaShop MCP' d='Modules.Dwcprestamcp.Admin'}</h3>
    <p>{l s='This module runs its own MCP server. Point your AI client (Claude, ChatGPT, Gemini, MCP Inspector) at the endpoint below using the Bearer token.' d='Modules.Dwcprestamcp.Admin'}</p>

    <div class="form-group">
        <label>{l s='MCP endpoint URL' d='Modules.Dwcprestamcp.Admin'}</label>
        <input type="text" class="form-control" readonly onclick="this.select()" value="{$dwc_endpoint|escape:'html':'UTF-8'}">
    </div>

    <div class="form-group">
        <label>{l s='Access token (Bearer)' d='Modules.Dwcprestamcp.Admin'}</label>
        <input type="text" class="form-control" readonly onclick="this.select()" value="{$dwc_token|escape:'html':'UTF-8'}">
        <p class="help-block">{l s='Keep this secret. Anyone with this token can call your store tools.' d='Modules.Dwcprestamcp.Admin'}</p>
    </div>

    <div class="form-group">
        <label>{l s='Client configuration (example)' d='Modules.Dwcprestamcp.Admin'}</label>
        <textarea class="form-control" rows="9" readonly onclick="this.select()">{$dwc_client_snippet|escape:'html':'UTF-8'}</textarea>
    </div>

    <form method="post" action="{$dwc_regenerate_action|escape:'html':'UTF-8'}" onsubmit="return confirm('{l s='Regenerate the token? The old token will stop working immediately.' d='Modules.Dwcprestamcp.Admin' js=1}');">
        <button type="submit" name="submitDwcRegenerateToken" class="btn btn-warning">
            <i class="icon icon-refresh"></i> {l s='Regenerate token' d='Modules.Dwcprestamcp.Admin'}
        </button>
    </form>
</div>
