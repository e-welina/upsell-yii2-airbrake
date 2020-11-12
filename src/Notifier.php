<?php

namespace upsell\airbrake;

use Airbrake\Notifier as AirbrakeNotifier;
use GuzzleHttp\Client;
use Yii;

class Notifier extends AirbrakeNotifier
{
   
    private $httpClient;
 
    
    protected function sendRequest($req)
    {
        return $this->httpClient->send($req, ['http_errors' => false, 'verify' => false]);
    }
    
    protected function sendRequestAsync($req)
    {
        return $this->httpClient->sendAsync($req, ['http_errors' => false, 'verify' => false]);
    }
    
    public function getClient(){
        return $this->httpClient;
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
    
    protected function gitRevision($dir)
    {
        $headFile = join(DIRECTORY_SEPARATOR, [$dir, '.git', 'HEAD']);
        if (!$headFile) return null;
        $head = @file_get_contents($headFile);
        if ($head === false) {
            return null;
        }

        $head = rtrim($head);
        $prefix = 'ref: ';
        if (strpos($head, $prefix) === false) {
            return $head;
        }
        $head = substr($head, strlen($prefix));

        $refFile = join(DIRECTORY_SEPARATOR, [$dir, '.git', $head]);
        $rev = @file_get_contents($refFile);
        if ($rev !== false) {
            return rtrim($rev);
        }

        $refsFiles = join(DIRECTORY_SEPARATOR, [$dir, '.git', 'packed-refs']);
        $handle = fopen($refsFiles, 'r');
        if (!$handle) {
            return null;
        }

        while (($line = fgets($handle)) !== false) {
            if (!$line || $line[0] === '#' || $line[0] === '^') {
                continue;
            }

            $parts = explode(' ', rtrim($line));
            if (count($parts) !== 2) {
                continue;
            }

            if ($parts[1] == $head) {
                return $parts[0];
            }
        }

        return null;
    }

}