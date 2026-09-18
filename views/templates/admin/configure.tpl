{*
 * DWC PrestaShop MCP - configuration screen.
 * @license MIT
 *}
<div class="panel">
    <h3><i class="icon icon-plug"></i> {l s='DWC PrestaShop MCP' d='Modules.Dwcprestamcp.Admin'}</h3>

    <ul class="nav nav-tabs" id="dwcTabs">
        <li class="active">
            <a href="#dwc-config" data-toggle="tab">
                <i class="icon icon-cogs"></i> {l s='Configuration' d='Modules.Dwcprestamcp.Admin'}
            </a>
        </li>
        <li>
            <a href="#dwc-manual" data-toggle="tab">
                <i class="icon icon-book"></i> {l s='Manual' d='Modules.Dwcprestamcp.Admin'}
            </a>
        </li>
    </ul>

    <div class="tab-content" style="padding-top: 20px;">
        <div class="tab-pane active" id="dwc-config">
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

        <div class="tab-pane" id="dwc-manual">
            {include file="./manual.tpl"}
        </div>
    </div>
</div>
