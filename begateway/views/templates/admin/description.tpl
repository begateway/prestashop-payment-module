{*
* NOTICE OF LICENSE
*
* This file is licenced under the Software License Agreement.
* With the purchase or the installation of the software in your application
* you accept the licence agreement.
*
* You must not modify, adapt or create derivative works of this source code
*
*  @author    eComCharge
*  @copyright 2018 eComCharge
*  @license   LICENSE
*}

<div class="panel">
    <div class="row begateway-header">
        <img src="{$module_dir|escape:'html':'UTF-8'}views/img/begateway.png" class="col-xs-6 col-md-4 text-center" id="payment-logo" />
        <div class="col-xs-6 col-md-4 text-center">
            <h2>{l s='BeGateway Payments' mod='begateway'}</h2>
            <h4>{l s='Online payment processing' mod='begateway'}</h4>
            <h4>{l s='Fast - Secure - Reliable' mod='begateway'}</h4>
        </div>
        <div class="col-xs-12 col-md-4 text-center">
            <a href="https://begateway.com" class="btn btn-primary" id="create-account-btn">{l s='Create an account now!' mod='begateway'}</a><br />
            {l s='Already have an account?' mod='begateway'}<a href="https://docs.begateway.com/ru/using_api/id_key/"> {l s='Where get ID and keys' mod='begateway'}</a>
        </div>
    </div>

    <hr />

    <div class="begateway-content">
        <div class="row">
            <div class="col-md-12">
                <p>{l s='This module allows you to accept online payments' mod='begateway'}</p>
                <p>{l s='You need to configure your account with your payment processor first before using this module' mod='begateway'}</p>
            </div>
        </div>
    </div>
</div>
