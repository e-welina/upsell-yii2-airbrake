<?php

namespace upsell\airbrake;

use Airbrake\Notifier as AirbrakeNotifier;
use GuzzleHttp\Client;
use Airbrake\CodeHunk;

use Yii;

class Notifier extends AirbrakeNotifier
{
    private static $instanceCount = 0;
    /**
     * Http client
     * @var GuzzleHttp\ClientInterface
     */
    private $httpClient;
 
    public function __construct($opt)
    {
        if (empty($opt['projectId']) || empty($opt['projectKey'])) {
            throw new Exception('phpbrake: Notifier requires projectId and projectKey');
        }

        if (isset($opt['keysBlacklist'])) {
            error_log(
                'phpbrake: keysBlacklist is a deprecated option. Use keysBlocklist instead.'
            );
            $opt['keysBlocklist'] = $opt['keysBlacklist'];
        }

        $this->opt = array_merge([
          'host' => 'api.airbrake.io',
          'keysBlocklist' => ['/password/i', '/secret/i'],
        ], $opt);

        $this->httpClient = $this->newHTTPClient();
        $this->noticesURL = $this->buildNoticesURL();
        $this->codeHunk = new CodeHunk();
        $this->context = $this->buildContext();

        if (array_key_exists('keysBlocklist', $this->opt)) {
            $this->addFilter(function ($notice) {
                $noticeKeys = array('context', 'params', 'session', 'environment');
                foreach ($noticeKeys as $key) {
                    if (array_key_exists($key, $notice)) {
                        $this->filterKeys($notice[$key], $this->opt['keysBlocklist']);
                    }
                }
                return $notice;
            });
        }

        if (self::$instanceCount === 0) {
            Instance::set($this);
        }
        self::$instanceCount++;
       
    }

    private function newHTTPClient()
    {
        
        if ($this->opt['httpClient'] instanceof GuzzleHttp\ClientInterface) {
                return $this->opt['httpClient'];
        }
        
        return new Client([
            'connect_timeout' => 5,
            'read_timeout' => 5,
            'timeout' => 5
        ]);
    }
    
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
    
    private function buildContext()
    {
        $context = [
            'notifier' => [
                'name' => 'phpbrake',
                'version' => '0.7.5',
                'url' => 'https://github.com/airbrake/phpbrake',
            ],
            'os' => php_uname(),
            'language' => 'php ' . phpversion(),
        ];

        if (array_key_exists('appVersion', $this->opt)) {
            $context['version'] = $this->opt['appVersion'];
        }
        if (array_key_exists('environment', $this->opt)) {
            $context['environment'] = $this->opt['environment'];
        }
        if (($hostname = gethostname()) !== false) {
            $context['hostname'] = $hostname;
        }
        if (array_key_exists('revision', $this->opt)) {
            $context['revision'] = $this->opt['revision'];
        } else if (array_key_exists('SOURCE_VERSION', $_ENV)) {
            // https://devcenter.heroku.com/changelog-items/630
            $context['revision'] = $_ENV['SOURCE_VERSION'];
        }

        if (array_key_exists('rootDirectory', $this->opt)) {
            $context['rootDirectory'] = $this->opt['rootDirectory'];
            $this->addFilter(function ($notice) {
                return $this->rootDirectoryFilter($notice);
            });

            if (!array_key_exists('revision', $context)) {
                $rev = $this->gitRevision($this->opt['rootDirectory']);
                if ($rev) {
                    $context['revision'] = $rev;
                }
            }
        }

        return $context;
    }

}