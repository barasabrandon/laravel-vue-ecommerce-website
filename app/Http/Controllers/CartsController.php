<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Cart;
use App\Models\Processing;
use App\Models\Product;
use Stripe;
use Illuminate\Support\Facades\Auth;


class CartsController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
       return view('pages.checkout');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!$request->get('product_id')) {
            return [
                'message' => 'Cart items returned',
                'items'   => Cart::where('user_id', auth()->user()->id)->sum('quantity'),
            ];
        }

        //Getting product details        
        $product = Product::where('id', $request->get('product_id'))->first();
      

        $productFoundInCart = Cart::where('product_id', $request->get('product_id'))->pluck('id');
         
       
        
        if ($productFoundInCart->isEmpty()) {
              // Adding Product in Cart

            $cart = Cart::create([
                'product_id' => $product->id,
                'quantity'   => 1,
                'price'      => $product->sale_price,
                'user_id'    => auth()->user()->id, 
    
            ]);
        }
        else
        {
            //Incrementing Product quantitys
            $cart = Cart::where('product_id', $request->get('product_id'))->increment('quantity'); 
        }


        if ($cart)
        {
            return [
                'message' => 'Cart Updated',
                'items'   => Cart::where('user_id', auth()->user()->id)->sum('quantity'),
            ];
        }
        
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }

    /**
     * 
     */
    public function getCartItemsForCheckout()
    {
        //echo "<pre>";
        $cartItems = Cart::with('product')->where('user_id', auth()->user()->id)->get();

        $finalData = [];
        $amount = 0;

        if (isset($cartItems)) 
        {
            foreach($cartItems as $cartItem)
            {
                if ($cartItem->product) {
                    
    
                    foreach ($cartItem->product as $cartProduct) {
                        if ($cartProduct->id == $cartItem->product_id)
                        {                
                            $finalData[$cartItem->product_id]['id'] = $cartProduct->id;
                            $finalData[$cartItem->product_id]['name'] = $cartProduct->name;
                            $finalData[$cartItem->product_id]['quantity'] = $cartItem->quantity;
                            $finalData[$cartItem->product_id]['sale_price'] = $cartItem->price;
                            $finalData[$cartItem->product_id]['total'] = $cartItem->price * $cartItem->quantity;
                            $amount += $cartItem->price * $cartItem->quantity;
                            $finalData['totalAmount'] =  $amount;
                        }
                        // var_dump($cartProduct->id );                 

                    }
                }             
                
            }

            // dd($finalData);
        }

        // dd($cartItems);
        // return 123; 
        return response()->json($finalData);

    }

    public function processPaymment(Request $request){
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

        $clientId = auth()->user()->id;

        $orders = Cart::where('user_id', $clientId)->get();
        if (isset($orders)) {
           foreach ($orders as $order) {
               $processing = new Processing;
               $processing->client_name =  $firstName.' '.$lastName;
               $processing->client_id = $clientId;              
               $processing->client_address =  json_encode([
                'line1' => $address,
                'postal_code' => $zipCode,
                'city' => $city,
                'state' => $state,
                'country' => $country,
            ]);              
               $processing->client_details = json_encode([
                'order_id' => $order->id,               
                'quantity' => $order->quantity,               
            ]);  ;
               $processing->amount = $amount;           
               $processing->currency ='USD'; 
               $processing->save();

              if ($processing->save()) {
                Cart::where('user_id', $clientId)->delete();

                return ['success'=> 'Order completed successfully'];
              }
              else {
                return ['error'=> 'Order failed contact support'];
              }

           }
        }
       
        // $orders = $request->get('order');

        // $ordersArray = [];

        // foreach ($orders as $order) {
        //     if ($order['id']) {
        //         $ordersArray[] = $order['id'];  
        //     }
                    
        // }
        // dd($order, $ordersArray);

        //Process Payment 
        $stripe = Stripe::make(env('STRIPE_API_KEY'));//Stripe is imported above

        $token = $stripe->tokens()->create([
            'card' => [
                'number' => $cardNumber,
                'exp_month' => $expirationMonth,
                'exp_year'  => $expirationYear,
                'cvc'     => $cvv,
            ],
        ]);
               
        if(!$token['id'])
        {
            session()->flush('error', 'Stripe token generation failed');
            return;
        }

        //Create a customer stripe
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

        //Code charging the client in Stripe.
        $charge = $stripe->charges()->create([
            'customer' => $customer['id'],
            'currency' => 'USD',
            'amount' => $amount,
            'description' => 'Payment for order',

        ]);

        if ($charge['status'] == 'succeeded') {

            //Capture the details from stripe
            $customerIdStripe = $charge['id']; 
            $amountReceived =   $charge['amount'];  
            $client_id = auth()->user()->id;
           
        }
        else
        {
            dd($charge);
        }


    }


}
