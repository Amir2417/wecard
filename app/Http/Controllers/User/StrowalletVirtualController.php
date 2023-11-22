<?php

namespace App\Http\Controllers\User;

use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\Admin\Currency;
use App\Models\VirtualCardApi;
use App\Http\Controllers\Controller;
use App\Models\StrowalletVirtualCard;
use App\Models\Admin\TransactionSetting;

class StrowalletVirtualController extends Controller
{
    protected $api;
    public function __construct()
    {
        $cardApi = VirtualCardApi::first();
        $this->api =  $cardApi;
    }

    public function index()
    {
        $page_title = "Virtual Card";
        $myCards = StrowalletVirtualCard::where('user_id',auth()->user()->id)->get();
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $transactions = Transaction::auth()->virtualCard()->latest()->take(5)->get();
        $cardApi = $this->api;
        $user   = auth()->user();

        return view('user.sections.virtual-card-strowallet.index',compact(
            'page_title',
            'cardApi',
            'myCards',
            'transactions',
            'cardCharge',
            'user'
        ));
    }
    /**
     * Method for strowallet card buy
     */
    public function cardBuy(Request $request){
        $user = auth()->user();
        if($user->strowallet_customer == null){
            $request->validate([
                'card_amount'       => 'required|numeric|gt:0',
                'first_name'        => 'required|string',
                'last_name'         => 'required|string',
                'house_number'      => 'required|string',
                'id_number'         => 'required|string',
                'customer_email'    => 'required|string',
                'phone'             => 'required|string',
                'date_of_birth'     => 'required|string',
                'line1'             => 'required|string',
                'state'             => 'required|string',
                'zip_code'          => 'required|string',
                'city'              => 'required|string',
                'country'           => 'required|string',
                'id_type'           => 'required|string',
            ]);
        }else{
            $request->validate([
                'card_amount'       => 'required|numeric|gt:0',
            ]);
        }
        
        $formData   = $request->all();
        
        
        $amount = $request->card_amount;
        $wallet = UserWallet::where('user_id',$user->id)->first();
        if(!$wallet){
            return back()->with(['error' => ['Wallet not found']]);
        }
        $cardCharge = TransactionSetting::where('slug','virtual_card')->where('status',1)->first();
        $baseCurrency = Currency::default();
        $rate = $baseCurrency->rate;
        if(!$baseCurrency){
            return back()->with(['error' => ['Default currency not setup yet']]);
        }
        $minLimit =  $cardCharge->min_limit *  $rate;
        $maxLimit =  $cardCharge->max_limit *  $rate;
        if($amount < $minLimit || $amount > $maxLimit) {
            return back()->with(['error' => ['Please follow the transaction limit']]);
        }
        //charge calculations
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return back()->with(['error' => ['Sorry, insufficient balance']]);
        }
        $currency = $baseCurrency->code;

        if($user->strowallet_customer == null){
            $createCustomer     = stro_wallet_create_user($user,$formData,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
            
            if( $createCustomer['status'] == false){
                return back()->with(['error' => ["Customer doesn't created properly,Contact Follow the instruction"]]);
            }
            $user->strowallet_customer =   (object)$createCustomer['data'];
            $user->save();
            $customer = $user->strowallet_customer;
            
        }else{
            $customer = $user->strowallet_customer;
        }

        // $created_card = create_strowallet_virtual_card($user,$request->card_amount,$customer,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
        // if($created_card['status'] == false){
        //     return back()->with(['error' => [$created_card['message'] ?? "Card Creation Failed!"]]);
        // }
        // $card_id    = $created_card['data']->card_id;
        $card_id        = "11ac4e7e-8d28-435e-ac03-a91c60f087bf";
        $card_details   = card_details($card_id,$this->api->config->strowallet_public_key,$this->api->config->strowallet_url);
        if( $card_details['status'] == false){
            return back()->with(['error' => ["No Card Found!"]]);
        }
        
        $strowallet_card                            = new StrowalletVirtualCard();
        $strowallet_card->user_id                   = $user->id;
        $strowallet_card->name_on_card              = $card_details['data']['card_detail']['card_holder_name'];
        $strowallet_card->card_id                   = $card_details['data']['card_detail']['card_id'];
        $strowallet_card->card_created_date         = $card_details['data']['card_detail']['card_created_date'];
        $strowallet_card->card_type                 = $card_details['data']['card_detail']['card_type'];
        $strowallet_card->card_brand                = $card_details['data']['card_detail']['card_brand'];
        $strowallet_card->card_user_id              = $card_details['data']['card_detail']['card_user_id'];
        $strowallet_card->reference                 = $card_details['data']['card_detail']['reference'];
        $strowallet_card->card_status               = $card_details['data']['card_detail']['card_status'];
        $strowallet_card->customer_id               = $card_details['data']['card_detail']['customer_id'];
        $strowallet_card->card_name                 = $customer->card_brand;
        $strowallet_card->card_number               = $card_details['data']['card_detail']['card_number'];
        $strowallet_card->last4                     = $card_details['data']['card_detail']['last4'];
        $strowallet_card->cvv                       = $card_details['data']['card_detail']['cvv'];
        $strowallet_card->expiry                    = $card_details['data']['card_detail']['expiry'];
        $strowallet_card->customer_email            = $card_details['data']['card_detail']['customer_email'];
        $strowallet_card->balance                   = $card_details['data']['card_detail']['balance'];
        $strowallet_card->save();

        dd($card_details['data']['card_detail']);
    }
}
