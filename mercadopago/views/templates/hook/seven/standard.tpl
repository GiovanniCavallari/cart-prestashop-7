{*
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
* @author PrestaShop SA <contact@prestashop.com>
* @copyright 2007-2019 PrestaShop SA
* @license http://opensource.org/licenses/afl-3.0.php Academic Free License (AFL 3.0)
* International Registered Trademark & Property of PrestaShop SA
*}

<form id="mp_standard_checkout" method="post" action="{$redirect}">
    <div class="row frame-checkout-seven">

        {if count($credit) != 0}
        <div class="col-xs-12 col-md-12 col-12">
            <div class="frame-tarjetas">
                <p class="subtitle-standard-checkout">
                    {l s='Tarjetas de crédito' mod='mercadopago'}
                    <span class="badge-checkout">
                        {l s='Hasta' mod='mercadopago'} {$installments|escape:'html':'UTF-8'} {l s='cuotas' mod='mercadopago'}
                    </span>
                </p>

                {foreach $credit as $tarjeta}
                <img src="{$tarjeta['image']|escape:'html':'UTF-8'}" class="img-fluid img-tarjetas" />
                {/foreach}
            </div>
        </div>
        {/if}

        {if count($debit) != 0}
        <div class="col-xs-12 col-lg-6 col-md-6 col-12">
            <div class="frame-tarjetas">
                <p class="subtitle-standard-checkout">{l s='Tarjetas de débito' mod='mercadopago'}</p>

                {foreach $debit as $tarjeta}
                <img src="{$tarjeta['image']|escape:'html':'UTF-8'}" class="img-fluid img-tarjetas" />
                {/foreach}
            </div>
        </div>
        {/if}

        {if count($ticket) != 0}
        <div class="col-xs-12 col-lg-6 col-md-6 col-12">
            <div class="frame-tarjetas">
                <p class="subtitle-checkout">{l s='Pagos en efectivo' mod='mercadopago'}</p>

                {foreach $ticket as $tarjeta}
                <img src="{$tarjeta['image']|escape:'html':'UTF-8'}" class="img-fluid img-tarjetas" />
                {/foreach}
            </div>
        </div>
        {/if}

        {if $modal != true && $preference == ""}
        <div class="col-md-12 pt-20">
            <div class="redirect-frame">
                <img src="{$module_dir|escape:'html':'UTF-8'}views/img/redirect_checkout.png" class="img-fluid" />
                <p>{l s='Te llevamos a nuestro sitio para completar el pago' mod='mercadopago'}</p>
            </div>
        </div>
        {/if}
    </div>

    {if $modal == true && $preference != ""}
        <script src="{$modal_link}" data-public-key="{$public_key}" data-preference-id="{$preference}"></script>
    {/if}
</form>

{if $modal == true && $preference != ""}
<script>
    var mercadopago_button = document.querySelector('.mercadopago-button');
    mercadopago_button.style.display = 'none';
    document.forms['mp_standard_checkout'].onsubmit = function () { 
        mercadopago_button.click();
        return false;
    }
</script>
{/if}