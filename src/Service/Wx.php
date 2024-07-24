<?php
/**
 * vim:ft=php et ts=4 sts=4
 * @author Al Zee <z@alz.ee>
 * @version
 * @todo
 */

namespace App\Service;

use Symfony\Component\Cache\Adapter\RedisAdapter;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

class Wx
{
    private $httpClient;
    private $appid;
    private $secret;
    private $cache;

    public function __construct(HttpClientInterface $client)
    {
        $this->httpClient = $client;
        $this->appid = $_ENV['WX_APP_ID'];
        $this->secret = $_ENV['WX_APP_SECRET'];
        $this->cache = new RedisAdapter(RedisAdapter::createConnection('redis://localhost'));
        // $cache = new FilesystemAdapter();
    }

    public function getAccessToken()
    {
        return $this->cache->get('WX_ACCESS_TOKEN', function (ItemInterface $item) {
            $item->expiresAfter(7000);
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$this->appid}&secret={$this->secret}";
            $content = $this->httpClient->request('GET', $url)->toArray();
            // dump($content);
            return $content['access_token'];
        });
    }
    
    public function getStableAccessToken($force = false)
    {
        return $this->cache->get('WX_STABLE_ACCESS_TOKEN', function (ItemInterface $item) use ($force){
            $url = "https://api.weixin.qq.com/cgi-bin/stable_token";
            $data = [
                'grant_type' => "client_credential",
                'appid' => $this->appid,
                'secret' => $this->secret,
                'force_refresh' => $force,
            ];
            $content = $this->httpClient->request('POST', $url, ['json' => $data])->toArray();
            $item->expiresAfter($content['expires_in']);
            return $content['access_token'];
        });
    }

    public function getOpenid($code)
    {
        $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$this->appid}&secret={$this->secret}&js_code=$code&grant_type=authorization_code";
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept:application/json';
        $content = $this->httpClient->request('GET', $url, ['headers' => $headers])->toArray();
        // $sessionKey = $content['session_key'];
        $openid = $content['openid'];

        return $openid;
    }

    public function getAppid()
    {
        return $this->appid;
    }
    
    public function genUrlLink(string $page, string $query = '')
    {
        $token = $this->getStableAccessToken();
        $url = "https://api.weixin.qq.com/wxa/generate_urllink?access_token={$token}";
        $data = [
            'path' => "/pages/{$page}/index",
            'query' => $query,
        ];
        $content = $this->httpClient->request('POST', $url, ['json' => $data])->toArray();
        return $content['url_link'];
    }
    
    public function genScheme(string $page, string $query = '')
    {
        $token = $this->getStableAccessToken();
        $url = "https://api.weixin.qq.com/wxa/generatescheme?access_token={$token}";
        $data = [
            'jump_wxa' => [
                'path' => "/pages/{$page}/index",
                'query' => $query,
            ],
        ];
        $content = $this->httpClient->request('POST', $url, ['json' => $data])->toArray();
        return $content['openlink'];
    }
    
    public function getUrlLinkFromCache(int $uid, string $page, string $query = '')
    {
        return $this->cache->get("WX_URLLINK_{$uid}_{$page}_{$query}", function (ItemInterface $item) use ($page, $query) {
            $item->expiresAfter(3600 * 24 * 20);
            return $this->genUrlLink($page, $query);
        });
    }
    
    public function getSchemeFromCache(int $uid, string $page, string $query = '')
    {
        return $this->cache->get("WX_SCHEME_{$uid}_{$page}_{$query}", function (ItemInterface $item) use ($page, $query) {
            $item->expiresAfter(3600 * 24 * 20);
            return $this->genScheme($page, $query);
        });
    }
    
    /**
     * https://developers.weixin.qq.com/miniprogram/dev/OpenApiDoc/user-info/phone-number/getPhoneNumber.html
     */
    public function getPhoneNumber(string $code)
    {
        $token = $this->getStableAccessToken();
        $url = "https://api.weixin.qq.com/wxa/business/getuserphonenumber?access_token={$token}";
        $data = [
            'code' => $code,
        ];
        $content = $this->httpClient->request('POST', $url, ['json' => $data])->toArray();
        return $content;
    }

    /**
     * @see https://developers.weixin.qq.com/doc/offiaccount/OA_Web_Apps/JS-SDK.html#62
     */
    public function getJSTicket()
    {
        return $this->cache->get('WX_JS_TICKET', function (ItemInterface $item) {
            $token = $this->getStableAccessToken();
            $url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$token}&type=jsapi";
            $headers[] = 'Content-Type: application/json';
            $headers[] = 'Accept:application/json';
            $content = $this->httpClient->request('GET', $url, ['headers' => $headers])->toArray();
            $item->expiresAfter($content['expires_in']);

            return $content['ticket'];
        });
    }

    /**
     *
     */
    public function getJSSignature($ticket, $nonce, $timestamp, $url)
    {
        $str = "jsapi_ticket={$ticket}&noncestr={$nonce}&timestamp={$timestamp}&url={$url}";

        return sha1($str);
    }
}
