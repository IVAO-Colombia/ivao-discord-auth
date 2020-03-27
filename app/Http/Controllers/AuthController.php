<?php

namespace App\Http\Controllers;

use App\Services\IVAOApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Socialite;
use App\Services\DiscordService;

class AuthController extends Controller
{
    public function Discord(){
        return Socialite::with('discord')->scopes(['guilds.join'])->redirect();
    }

    public function DiscordCallback(Request $request){
        $user = Socialite::driver('discord')->user();
        $request->session()->put('DISCORD_TOKEN', $user->token);
        $discordService = new DiscordService($user->token);
        $discordService->joinInTheServer();

        $contents = Storage::disk('local')->get('roles');
        $data = Crypt::decryptString($contents);
        $rolesData = json_decode($data);

        $IVAOTOKEN = $request->session()->get('IVAOTOKEN');
        $IVAOAPI = new IVAOApiService($IVAOTOKEN);
        $IVAOAPI->getUserData();

        try {
            if(count($IVAOAPI->getStaff()) > 0){
                $this->assignStaff($discordService, $IVAOAPI, $rolesData);
            }

            $this->assignMember($discordService, $IVAOAPI, $rolesData);

            return redirect('/success');
        }
        catch (Exception $e){

        }

    }

    private function assignMember(DiscordService $discordService, IVAOApiService $IVAOAPI, $rolesData){
        $role = array_values(array_filter($rolesData, function($role) {
            return $role->sulfix === 'Member';
        }))[0];

        foreach($role->id as $id){
            $discordService->addRole($id);
        }
    }

    private function assignStaff(DiscordService $discordService, IVAOApiService $IVAOAPI, $rolesData) {
        $staffPos = $IVAOAPI->getStaff();
        foreach($staffPos as $staff){
            foreach($rolesData as $role){
                if(strstr($role->sulfix,$staff)){
                    foreach($role->id as $id){
                        $discordService->addRole($id);
                    }
                }
            }
        }
    }
}