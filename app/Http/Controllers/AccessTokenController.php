<?php

namespace App\Http\Controllers;

use GuzzleHttp\Exception\ClientException;
use Laravel\Passport\Http\Controllers\AccessTokenController as PassportAccessTokenController;
use League\OAuth2\Server\Exception\OAuthServerException;
use Psr\Http\Message\ServerRequestInterface;
use Zend\Diactoros\Response as Psr7Response;
use Auth;
use App\Http\Models\User;
use Modules\Merchant\Entities\Merchant;

class AccessTokenController extends PassportAccessTokenController
{
    /**
     * Authorize a client to access the user's account.
     *
     * @param  ServerRequestInterface $request
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     */
    public function issueToken(ServerRequestInterface $request)
    {
        //return response()->json($request->getParsedBody());
        try {
            if (isset($request->getParsedBody()['username']) && isset($request->getParsedBody()['password'])) {
                if (Auth::attempt(['phone' => $request->getParsedBody()['username'], 'password' => $request->getParsedBody()['password']])) {
                    $user = User::where('phone', $request->getParsedBody()['username'])->first();
                    if ($user) {
                        //check if user already suspended
                        if ($user->is_suspended == '1') {
                            return response()->json(['status' => 'fail', 'messages' => 'Akun Anda telah diblokir karena menunjukkan aktivitas mencurigakan. Untuk informasi lebih lanjut harap hubungi customer service kami.']);
                        }

                        if (isset($request->getParsedBody()['scope'])) {
                            if ($request->getParsedBody()['scope'] == 'be' && strtolower($user->level) == 'customer') {
                                return response()->json(['status' => 'fail', 'messages' => "You don't have access in this app"]);
                            }
                                if($request->getParsedBody()['scope'] == 'be'&& strtolower($user->level) == 'mitra'){
                                   $merhcant = Merchant::where('id_user',$user->id)->where('merchant_status','Active')->first();
                                   if(!$merhcant){
                                        return response()->json(['status' => 'fail', 'messages' => "User mitra not found or user not active"]);
                                    } 
                                }
                                if ($request->getParsedBody()['scope'] == 'apps' && strtolower($user->level) != 'customer') {
                                    return response()->json(['status' => 'fail', 'messages' => "You don't have access in this app"]);
                                }
                                if ($request->getParsedBody()['scope'] == 'mitra-apps' && strtolower($user->level) != 'mitra') {
                                return response()->json(['status' => 'fail', 'messages' => "You don't have access in this app"]);
                            }
                        } else {
                            return response()->json(['status' => 'fail', 'messages' => 'Incompleted input']);
                        }
                    }
                }
                if (Auth::attempt(['email' => $request->getParsedBody()['username'], 'password' => $request->getParsedBody()['password']])) {
                    $user = User::where('email', $request->getParsedBody()['username'])->first();
                    if ($user) {
                        //check if user already suspended
                        if ($user->is_suspended == '1') {
                            return response()->json(['status' => 'fail', 'messages' => 'Akun Anda telah diblokir karena menunjukkan aktivitas mencurigakan. Untuk informasi lebih lanjut harap hubungi customer service kami.']);
                        }

                        if (isset($request->getParsedBody()['scope'])) {
                            if ($request->getParsedBody()['scope'] == 'be' && strtolower($user->level) == 'customer') {
                                return response()->json(['status' => 'fail', 'messages' => "You don't have access in this app"]);
                            }
                                if($request->getParsedBody()['scope'] == 'be'&& strtolower($user->level) == 'mitra'){
                                   $merhcant = Merchant::where('id_user',$user->id)->where('merchant_status','Active')->first();
                                   if(!$merhcant){
                                        return response()->json(['status' => 'fail', 'messages' => "User mitra not found or user not active"]);
                                    } 
                                }
                                if ($request->getParsedBody()['scope'] == 'apps' && strtolower($user->level) != 'customer') {
                                    return response()->json(['status' => 'fail', 'messages' => "You don't have access in this app"]);
                                }
                                if ($request->getParsedBody()['scope'] == 'mitra-apps' && strtolower($user->level) != 'mitra') {
                                return response()->json(['status' => 'fail', 'messages' => "You don't have access in this app"]);
                            }
                        } else {
                            return response()->json(['status' => 'fail', 'messages' => 'Incompleted input']);
                        }
                    }
                }
            }
            return $this->convertResponse(
                $this->server->respondToAccessTokenRequest($request, new Psr7Response())
            );
        } catch (OAuthServerException $exception) {
            //return error message

            if ($exception->getCode() == 6) {
                return response()->json(['status' => 'fail', 'messages' => 'Pin tidak sesuai.']);
            }

            return $this->withErrorHandling(function () use ($exception) {
                throw $exception;
            });
        }
    }
}
