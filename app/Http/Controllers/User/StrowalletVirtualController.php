<?php

namespace App\Http\Controllers\User;

use Exception;
use App\Models\UserWallet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Http\Helpers\Response;
use App\Models\Admin\Currency;
use App\Models\VirtualCardApi;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use App\Models\Admin\BasicSettings;
use App\Constants\NotificationConst;
use App\Http\Controllers\Controller;
use App\Models\StrowalletVirtualCard;
use App\Constants\PaymentGatewayConst;
use App\Models\Admin\TransactionSetting;
use Illuminate\Support\Facades\Validator;

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
        

        return view('user.sections.virtual-card-strowallet.index',compact(
            'page_title',
            'cardApi',
            'myCards',
            'transactions',
            'cardCharge',
            
        ));
    }
    /**
     * Method for card details
     * @param $card_id
     * @param \Illuminate\Http\Request $request
     */
    public function cardDetails($card_id){
        $page_title = "Card Details";
        $myCard = StrowalletVirtualCard::where('card_id',$card_id)->first();
        if(!$myCard) return back()->with(['error' => ['Card Not Found!']]);
        $cardApi = $this->api;
        return view('user.sections.virtual-card-strowallet.details',compact(
            'page_title',
            'myCard',
            'cardApi'
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
        $basic_setting = BasicSettings::first();
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

        // dd($card_details['data']['card_detail']);
        $trx_id =  'CB'.getTrxNum();
        try{
            $sender = $this->insertCardBuy( $trx_id,$user,$wallet,$amount, $strowallet_card ,$payable);
            $this->insertBuyCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$strowallet_card->last4);
            if( $basic_setting->email_notification == true){
            $notifyDataSender = [
                'trx_id'  => $trx_id,
                'title'  => "Virtual Card (Buy Card)",
                'request_amount'  => getAmount($amount,4).' '.get_default_currency_code(),
                'payable'   =>  getAmount($payable,4).' ' .get_default_currency_code(),
                'charges'   => getAmount( $total_charge, 2).' ' .get_default_currency_code(),
                'card_amount'  => getAmount( $strowallet_card->amount, 2).' ' .get_default_currency_code(),
                'card_pan'  => $strowallet_card->last4,
                'status'  => "Success",
                ];
            }
            return redirect()->route("user.strowallet.virtual.card.index")->with(['success' => ['Card Successfully Buy']]);
        }catch(Exception $e){
            return back()->with(['error' => [$e->getMessage()]]);
        }
    }
    public function insertCardBuy( $trx_id,$user,$wallet,$amount, $strowallet_card ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $strowallet_card??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDBUY," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function insertBuyCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$masked_card) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>"Buy Card ",
                'message'       => "Buy card successful ".$masked_card,
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_BUY,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    //update user balance
    public function updateSenderWalletBalance($authWallet,$afterCharge) {
        $authWallet->update([
            'balance'   => $afterCharge,
        ]);
    }
    /**
     * card freeze unfreeze
     */
    public function cardBlockUnBlock(Request $request) {
        
        $validator = Validator::make($request->all(),[
            'status'                    => 'required|boolean',
            'data_target'               => 'required|string',
        ]);
        if ($validator->stopOnFirstFailure()->fails()) {
            $error = ['error' => $validator->errors()];
            return Response::error($error,null,400);
        }
        $validated = $validator->safe()->all();
        if($request->status == 1){
           
            $card   = StrowalletVirtualCard::where('id',$request->data_target)->where('is_active',1)->first();
            $client = new \GuzzleHttp\Client();
            $public_key     = $this->api->config->strowallet_public_key;
            $base_url       = $this->api->config->strowallet_url;

            $response = $client->request('POST', $base_url.'action/status/?action=freeze&card_id='.$card->card_id.'&public_key='.$public_key, [
            'headers' => [
                'accept' => 'application/json',
            ],
            ]);
           
            $result = $response->getBody();
            $data  = json_decode($result, true);
            
            if( isset($data['status']) ){
                $card->is_active = 0;
                $card->save();
                $success = ['success' => [' Card Freeze successfully']];
                return Response::success($success,null,200);
            }else{
                $error = ['error' => $data['message']];
                return Response::error($error,null,200);
            }
            

        }else{
           
            $card   = StrowalletVirtualCard::where('id',$request->data_target)->where('is_active',0)->first();
            $client = new \GuzzleHttp\Client();
            $public_key     = $this->api->config->strowallet_public_key;
            $base_url       = $this->api->config->strowallet_url;



            $response = $client->request('POST', $base_url.'action/status/?action=unfreeze&card_id='.$card->card_id.'&public_key='.$public_key, [
            'headers' => [
                'accept' => 'application/json',
            ],
            ]);

            $result = $response->getBody();
            $data  = json_decode($result, true);
            if(isset($data['status'])){
                $card->is_active = 1;
                $card->save();
                $success = ['success' => [' Card UnFreeze successfully']];
                return Response::success($success,null,200);
            }else{
                $error = ['error' => $data['message']];
                return Response::error($error,null,200);
            }
        }
        
    }
    /**
     * Card Fund
     */
    public function cardFundConfirm(Request $request){
        $request->validate([
            'id' => 'required|integer',
            'fund_amount' => 'required|numeric|gt:0',
        ]);
        $user = auth()->user();
        $myCard =  StrowalletVirtualCard::where('user_id',$user->id)->where('id',$request->id)->first();
        
        if(!$myCard){
            return back()->with(['error' => ['Your Card not found']]);
        }

        $amount = $request->fund_amount;
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
        $fixedCharge = $cardCharge->fixed_charge *  $rate;
        $percent_charge = ($amount / 100) * $cardCharge->percent_charge;
        $total_charge = $fixedCharge + $percent_charge;
        $payable = $total_charge + $amount;
        if($payable > $wallet->balance ){
            return back()->with(['error' => ['Sorry, insufficient balance']]);
        }
        $currency =$baseCurrency->code;
        $public_key     = $this->api->config->strowallet_public_key;
        $base_url       = $this->api->config->strowallet_url;

        $client = new \GuzzleHttp\Client();

        $response               = $client->request('POST', $base_url.'fund-card/', [
            'headers'           => [
                'accept'        => 'application/json',
            ],
            'form_params'       => [
                'card_id'       => $myCard->card_id,
                'amount'        => $amount,
                'public_key'     => $public_key,
            ],
        ]);

        $result         = $response->getBody();
        $decodedResult  = json_decode($result, true);
       
        if(!empty($result->status)  && $result->status == "success"){
            //added fund amount to card
            $myCard->balance += $amount;
            $myCard->save();
            $trx_id = 'CF'.getTrxNum();
            $sender = $this->insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable);
            $this->insertFundCardCharge( $fixedCharge,$percent_charge, $total_charge,$user,$sender,$myCard->card_number,$amount);
            return redirect()->back()->with(['success' => ['Card Funded Successfully']]);

        }else{
            return redirect()->back()->with(['error' => [@$result->message??'Please wait a moment & try again later.']]);
        }

    }
    //card fund helper
    public function insertCardFund( $trx_id,$user,$wallet,$amount, $myCard ,$payable) {
        $trx_id = $trx_id;
        $authWallet = $wallet;
        $afterCharge = ($authWallet->balance - $payable);
        $details =[
            'card_info' =>   $myCard??''
        ];
        DB::beginTransaction();
        try{
            $id = DB::table("transactions")->insertGetId([
                'user_id'                       => $user->id,
                'user_wallet_id'                => $authWallet->id,
                'payment_gateway_currency_id'   => null,
                'type'                          => PaymentGatewayConst::VIRTUALCARD,
                'trx_id'                        => $trx_id,
                'request_amount'                => $amount,
                'payable'                       => $payable,
                'available_balance'             => $afterCharge,
                'remark'                        => ucwords(remove_speacial_char(PaymentGatewayConst::CARDFUND," ")),
                'details'                       => json_encode($details),
                'attribute'                      =>PaymentGatewayConst::RECEIVED,
                'status'                        => true,
                'created_at'                    => now(),
            ]);
            $this->updateSenderWalletBalance($authWallet,$afterCharge);

            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
        return $id;
    }
    public function insertFundCardCharge($fixedCharge,$percent_charge, $total_charge,$user,$id,$card_number,$amount) {
        DB::beginTransaction();
        try{
            DB::table('transaction_charges')->insert([
                'transaction_id'    => $id,
                'percent_charge'    => $percent_charge,
                'fixed_charge'      =>$fixedCharge,
                'total_charge'      =>$total_charge,
                'created_at'        => now(),
            ]);
            DB::commit();

            //notification
            $notification_content = [
                'title'         =>"Card Fund ",
                'message'       => "Card fund successful card: ".$card_number.' '.getAmount($amount,2).' '.get_default_currency_code(),
                'image'         => files_asset_path('profile-default'),
            ];

            UserNotification::create([
                'type'      => NotificationConst::CARD_FUND,
                'user_id'  => $user->id,
                'message'   => $notification_content,
            ]);
            DB::commit();
        }catch(Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage());
        }
    }
    
}
