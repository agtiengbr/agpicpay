<form name='agpicpay' class="form-horizontal" method="post"  method="post" enctype='multipart/form-data'>
    <ps-tabs position="top">
        <ps-tab label="Configurações" id="tabConfig" icon="icon-cogs" fa="cogs" active="true">
            <ps-input-text name="picpayCode" value="{$picpayCode}" label="Código de Vendedor PicPay">
            </ps-input-text>

            <ps-input-text name="picpayDiscount" value="{$picpayDiscount}" label="Desconto (%)">
            </ps-input-text>

            <ps-panel-footer>
                <ps-panel-footer-submit direction="left" title="Cancelar" icon='process-icon-cancel'></ps-panel-footer-submit>
                <ps-panel-footer-submit direction="right" title="Salvar" icon='process-icon-save' name="agpicpay-save"></ps-panel-footer-submit>
            </ps-panel-footer>
        </ps-tab>
        <ps-tab label="Mapeamentos" id="tabMappings" icon="icon-list" fa="list">
            <ps-select name="cpf_mapping" label='Campo de CPF'>
                {foreach from=$module->getCpfMapping()->getColumnsFromTable() item=column key=key}
                    <option value="{$key}" {if $module->getCpfMapping()->getMappedField() == $key}selected="selected"{/if}>{$column}</option>
                {/foreach}
            </ps-select>

            <ps-select name="picpay_status_created" label='Pedido Criado'>
                {foreach from=$orderStates key=key item=order_state}
                    <option value="{$order_state['id_order_state']}" {if $order_state['id_order_state'] == $picpayStatusCreated } selected="selected" {/if}>{$order_state['name']}</option>
                {/foreach}
            </ps-select>

            <ps-select name="picpay_status_expired" label='Prazo para Pagamento Expirado'>
                {foreach from=$orderStates key=key item=order_state}
                    <option value="{$order_state['id_order_state']}" {if $order_state['id_order_state'] == $picpayStatusExpired } selected="selected" {/if}>{$order_state['name']}</option>
                {/foreach}
            </ps-select>

            <ps-select name="picpay_status_analysis" label='Em Análise'>
                {foreach from=$orderStates key=key item=order_state}
                    <option value="{$order_state['id_order_state']}" {if $order_state['id_order_state'] == $picpayStatusAnalysis } selected="selected" {/if}>{$order_state['name']}</option>
                {/foreach}
            </ps-select>

            <ps-select name="picpay_status_paid" label='Pagamento Aprovado'>
                {foreach from=$orderStates key=key item=order_state}
                    <option value="{$order_state['id_order_state']}" {if $order_state['id_order_state'] == $picpayStatusPaid } selected="selected" {/if}>{$order_state['name']}</option>
                {/foreach}
            </ps-select>

            <ps-select name="picpay_status_completed" label='Transação Concluída'>
                {foreach from=$orderStates key=key item=order_state}
                    <option value="{$order_state['id_order_state']}" {if $order_state['id_order_state'] == $picpayStatusCompleted } selected="selected" {/if}>{$order_state['name']}</option>
                {/foreach}
            </ps-select>

            <ps-select name="picpay_status_refunded" label='Pagamento Devolvido'>
                {foreach from=$orderStates key=key item=order_state}
                    <option value="{$order_state['id_order_state']}" {if $order_state['id_order_state'] == $picpayStatusRefunded } selected="selected" {/if}>{$order_state['name']}</option>
                {/foreach}
            </ps-select>

            <ps-select name="picpay_status_chargeback" label='Pagamento Contestado'>
                {foreach from=$orderStates key=key item=order_state}
                    <option value="{$order_state['id_order_state']}" {if $order_state['id_order_state'] == $picpayStatusChargeback } selected="selected" {/if}>{$order_state['name']}</option>
                {/foreach}
            </ps-select>

            <ps-panel-footer>
                <ps-panel-footer-submit direction="left" title="Cancelar" icon='process-icon-cancel'></ps-panel-footer-submit>
                <ps-panel-footer-submit direction="right" title="Salvar" icon='process-icon-save' name="agpicpay-save"></ps-panel-footer-submit>
            </ps-panel-footer>
        </ps-tab>
    </ps-tabs>
</form>
