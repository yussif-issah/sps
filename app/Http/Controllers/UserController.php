<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\user;
use Illuminate\Http\Response;
use Mail;
use Validator;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

class UserController extends Controller
{
    //

    //public $user;
    public function __construct(){
    $this->middleware('jwt.auth',['only'=>['logout']]); 
    }
    
    public function otpregistration(Request $request){
        $validator = Validator::make($request->all(),[
            'email'=>'required|string|email|max:100|unique:users'
        ]);
        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }
        $email = $request->input('email');
        $num_user=User::where('email',$email)->count();
        if($num_user>=1){
            if($request->input("resend")){
                $user = User::where('email',$email)->first();
                $codes = $this->generatMerchantAndPasscode();
                $passcode = $codes[1];
                $user->otp = $passcode;
                $user->save();
                $data = "Hello\n Your Merchant Id is $user->merchant_id\n Your Pass code is $passcode";
                $response = $this->sendEmail($data,$user->email);
                return $response;
            }else{
                $response = response()->json(["resgistration"=>"failed","message"=>"email is already in use"],404);
                return $response;
            }

        }else{
        $new_user = new User();
        $new_user->email=$email;
        $codes = $this->generatMerchantAndPasscode();
        $merchant_id = $codes[0];
        $passcode = $codes[1];
        $new_user->merchant_id = $merchant_id;
        $new_user->otp = $passcode;
        $new_user->save();
        $data = "Hello\n Your Merchant Id is $merchant_id\n Your Pass code is $passcode";
        $response = $this->sendEmail($data,$email);
        return $response;
        }
    }

    public function register(Request $request){
        
        $validator=Validator::make($request->all(),[
            'email'=>'required|string|email|max:100',
            'firstname'=>'required|max:50|string',
            'lastname'=>'required|max:50|string',
            'gender'=>'required|max:10|string',
            'username'=>'required|max:50|string',
            'password'=>'required|max:50|password',
            'birthdate'=>'required|date',
            'merchant_id'=>'bigInteger|required',
            'pass_code'=>'bigInteger|required'
        ]);

        if($validator->fails()){
            return response()->json($validator->errors(),400);
        }

        $merchant_id = $request->input("merchant_id");
        $passcode = $request->input("pass_code");
        $matchingValues=["merchant_id"=>$merchant_id,"otp"=>$passcode];
        $user = User::where($matchingValues)->first();
        $otpTime = strtotime(date('Y-m-d H:i:s'));
        $updatedAt = strtotime($user->updated_at);
        $min=round(abs($otpTime - $updatedAt)/60);
        if($min<6){
            return response()->json(["status"=>"Pass code expired"]);
        }
        if($user){
            $user->password=$request->input("password");
            $user->name=$request->input("username");
            $user->firstname=$request->input("firstname");
            $user->lastname=$request->input("lastname");
            $user->gender=$request->input("gender");
            $user->birthdate=$request->input("birthdate");
            $user->bank=$request->input("bank");
            $user->save();
            $response = response()->json(["status"=>"success","user_details"=>$user],200);
        }else{
            $response=response()->json(["status"=>"failed","message"=>"please check the correctness of your merchant id and pass code"],404);
        }
        return $response;
    }

    public function login(Request $request){
        $credentials = $request(['email','pass_code']);
        if(!$token=JWTAuth::attempt($credentials)){
            return response()->json(['error'=>'Unauthorized'],401);
        }

        return responseWithToken($token);
    }

    public function logout(Request $request){
        auth()->logout();
        return response()->json(["status"=>"You have logged out successfully"]);
    }

    private function sendEmail($data,$email){
        Mail::raw($data,function($message) use($email){
            $message->to($email,"merchant registration")->subject("SPS registration");
            $message->from("gyankoissah88@gmail.com","Sovereign pay solution");
            });
            return $response = response()->json(["status"=>"success"],201);
        }

    private function generatMerchantAndPasscode(){
        $count = User::count();
        srand($count);
        $merchant_id = rand(100000,999999);
        $passcode=rand(1000,9999);
        return [$merchant_id,$passcode];
    }

    protected function respondWithToken($token){
        return response()->json([
            'access_token'=>$token,
            'token_type'=>'bearer',
            'expires_in'=>auth()->factory()->getTTL()*60
        ]);
    }
}
