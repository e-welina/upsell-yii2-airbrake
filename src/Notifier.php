<?php

namespace upsell\airbrake;

use Airbrake\Notifier as AirbrakeNotifier;
use GuzzleHttp\Client;
use Yii;

class Notifier extends AirbrakeNotifier
{
    private $httpClient;
   
    public function __construct($opt)
    {
        parent::__construct($opt);
        $this->httpClient = $this->newHTTPClient();
        
    }
    
    public function getClient(){
        return $this->httpClient;
    }

    private function newHTTPClient()
    {
        return new Client([
            'connect_timeout' => 5,
            'read_timeout' => 5,
            'timeout' => 5,
            'verify' => false
        ]);
       
    }
    
    
    public function buildNotice($exc)
    {
        $notice = parent::buildNotice($exc);

        if (isset(Yii::$app->user)) {
            $user = Yii::$app->user;
            if (isset($user->id)) {
                $notice['context']['user']['id'] = $user->id;
            }
            if (isset($user->identity)) {
                $notice['context']['user']['name'] = $user->identity->nombre;
                $notice['context']['user']['email'] = $user->identity->email;
            }
        }

        return $notice;
    }

}