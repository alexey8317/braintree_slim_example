<html>
<head>
    <title>BraintreeSlimExample</title>
</head>
<body>
    <h1>Checkout</h1>
    <form method="post" id="checkout" action="/checkout.php">
        <label for="amount">Amount</label>
        <input type="text" name="amount" id="amount" value="10.00">
        <div id="payment-form"></div>
        <input type="submit" value="Pay Now">
    </form>

    <script src="https://js.braintreegateway.com/v2/braintree.js"></script>
    <script>
      var client_token = "<?php echo $this->data['client_token'] ?>";
      braintree.setup(client_token, "dropin", {
        container: "payment-form"
      });
    </script>
</body>
</html>
