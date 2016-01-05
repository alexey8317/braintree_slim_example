<?php
require_once("includes/braintree_init.php");

class IndexPageTest extends PHPUnit_Framework_TestCase
{
    function test_rootReturnsHttpRedirect()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $httpLocation = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        curl_close($curl);
        $this->assertEquals($httpStatus, 302);
        $this->assertContains("/checkouts", $httpLocation);
    }

    function test_checkoutsReturnsHttpSuccess()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->assertEquals($httpStatus, 200);
    }

    function test_checkoutsHasClientTokenOnPage()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        $this->assertRegExp('/var client_token = ".*";/', $output);
    }

    function test_checkoutsHasFormOnPage()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        curl_close($curl);
        $this->assertRegExp('/<form method="post" id="checkout"/', $output);
        $this->assertRegExp('/<div id="payment-form"/', $output);
        $this->assertRegExp('/<input type="text" name="amount" id="amount"/', $output);
    }

    function test_checkoutsShowContainsTransactionInformation()
    {
        $non_duplicate_amount = rand(1,100) . "." . rand(1,99);
        $result = Braintree\Transaction::sale([
            'amount' => $non_duplicate_amount,
            'paymentMethodNonce' => 'fake-valid-nonce'
        ]);
        $transaction = $result->transaction;
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts/" . $transaction->id);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $output = curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->assertEquals($httpStatus, 200);
        $this->assertContains($transaction->id, $output);
        $this->assertContains($transaction->type, $output);
        $this->assertContains($transaction->amount, $output);
        $this->assertContains($transaction->status, $output);
        $this->assertContains($transaction->creditCardDetails->bin, $output);
        $this->assertContains($transaction->creditCardDetails->last4, $output);
        $this->assertContains($transaction->creditCardDetails->cardType, $output);
        $this->assertContains($transaction->creditCardDetails->expirationDate, $output);
        $this->assertContains($transaction->creditCardDetails->customerLocation, $output);
    }

    function test_createsCheckoutsRedirectsToTransactionPage()
    {
        $non_duplicate_amount = rand(1,100) . "." . rand(1,99);
        $fields = array(
            'amount' => $non_duplicate_amount,
            'payment_method_nonce' => "fake-valid-nonce"
        );
        $fields_string = "";
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($curl);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $redirectUrl = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        curl_close($curl);
        $this->assertEquals($httpStatus, 302);
        $this->assertRegExp('/\/checkouts\/[a-z0-9]+/', $redirectUrl);
    }

    function test_checkoutsErrorRedirectsToCheckoutCreatePage()
    {
        $non_duplicate_amount = rand(1,100) . "." . rand(1,99);
        $fields = array(
            'amount' => $non_duplicate_amount,
            'payment_method_nonce' => "fake-consumed-nonce"
        );
        $fields_string = "";
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($curl);
        $redirectUrl = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->assertEquals($httpStatus, 302);
        $this->assertRegExp('/\/checkouts/', $redirectUrl);
    }

    function test_transactionErrorDisplaysFlashMessage()
    {
        $non_duplicate_amount = rand(1,100) . "." . rand(1,99);
        $fields = array(
            'amount' => $non_duplicate_amount,
            'payment_method_nonce' => "fake-consumed-nonce"
        );
        $fields_string = "";
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_COOKIEFILE, "");
        $output = curl_exec($curl);
        curl_close($curl);
        $this->assertRegExp('/Cannot use a paymentMethodNonce more than once./', $output);
    }

    function test_transactionProcessorErrorRedirectsToCheckoutShowPage()
    {
        $fields = array(
            'amount' => 2000,
            'payment_method_nonce' => "fake-valid-nonce"
        );
        $fields_string = "";
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_exec($curl);
        $redirectUrl = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        $httpStatus = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        $this->assertEquals($httpStatus, 302);
        $this->assertRegExp('/\/checkouts\/[a-z0-9]+/', $redirectUrl);
    }

    function test_transactionProcessorErrorDisplaysFlashMessage()
    {
        $fields = array(
            'amount' => 2000,
            'payment_method_nonce' => "fake-valid-nonce"
        );
        $fields_string = "";
        foreach($fields as $key=>$value) { $fields_string .= $key.'='.$value.'&'; }
        rtrim($fields_string, '&');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, "localhost:3000/checkouts");
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl, CURLOPT_COOKIEFILE, "");
        $output = curl_exec($curl);
        curl_close($curl);
        $this->assertRegExp('/Transaction status - processor_declined/', $output);
    }
}
