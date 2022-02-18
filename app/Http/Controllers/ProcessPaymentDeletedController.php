
    public function processPaymmentDeleted(Request $request)
    {
        
        $firstName = $request->get('firstName');
        $lastName = $request->get('lastName');
        $address = $request->get('address');
        $city = $request->get('city');
        $state = $request->get('state');
        $zipCode = $request->get('zipCode');
        $email = $request->get('email');
        $phone = $request->get('phone');
        $country = $request->get('country');
        $cardType = $request->get('cardType');
        $expirationMonth = $request->get('expirationMonth');
        $expirationYear = $request->get('expirationYear');
        $cvv = $request->get('cvv');  
        $cardNumber = $request->get('cardNumber');

        $amount = $request->get('amount');

        $orders = $request->get('order');

        $ordersArray = [];
        
        //Getting order details

       foreach($orders as $order)
       {
           if ($order['id']) {
             $ordersArray[$order['id']]['order_id'] = $order['id'];
             $ordersArray[$order['id']]['quantity'] = $order['quantity'];
           }
        
       }
       dd($order, json_encode($ordersArray));
        
        //Process payment

        $stripe = Stripe::make(env('STRIPE_KEY'));

        $token = $stripe->tokens()->create([
         'card' => [
            'number' => $cardNumber,
            'exp_month' => $expirationMonth,
            'exp_year'  => $expirationYear,
            'cvc'     => $cvv,
         ],
        ]);

        if(!$token['id']){
            session()->flush('error', 'Stripe token generation failed');
            return;
        }

        //Create customer stripe,
        $customer = $stripe->customers()->create([
            'name' => $firstName.' '.$lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => [
                'line1' => $address,
                'postal_code' => $zipCode,
                'city' => $city,
                'state' => $state,
                'country' => $country,
            ],
            'shipping' => [
                'name' => $firstName.' '.$lastName,
                'address' => [
                    'line1' => $address,
                    'postal_code' => $zipCode,
                    'city' => $city,
                    'state' => $state,
                    'country' => $country,
                ],
            ],
            'source' => $token['id'],

        ]);

        //Code for charging the client in Stripe
        $charge = $stripe->charges()->create([
           'customer' => $customer['id'],
           'currency' => 'USD',
           'amount'   => $amount,
           'description' => 'Payment for order',

        ]);

        if ($charge['status'] == 'succeeded') 
        {
            //Capture the details from stripe
            $customerIdStripe = $charge['id']; 
            $amountReceived =   $charge['amount'];  
            $client_id = auth()->user()->id;

            $processingDetails = Processing::create([
                'client_id' => $client_id,
                'client_name' => $firstName.' '.$lastName,
                'client_address' =>       
                json_encode([
                                'line1' => $address,
                                'postal_code' => $zipCode,
                                'city' => $city,
                                'state' => $state,
                                'country' => $country,
                            ]),
                'order_details' =>json_encode($ordersArray),
                'amount' => $amount,
                'currency' =>$charge['currency'],
            ]);

            if ($processingDetails) 
            {
            //    Clear the Cart after payment successful
            Cart::where('user_id', $client_id)->delete();

            return ['success'=> 'Order completed successfully'];

            }
        }
        else
        {
         return ['error' => 'Order failed contact support'];
        }   
        
        
      
    }