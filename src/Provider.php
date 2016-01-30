<?php
namespace SocialiteProviders\Thebizark;

use Illuminate\Http\Request;
use GuzzleHttp\ClientInterface;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class Provider extends AbstractProvider implements ProviderInterface
{
    protected $stateless = true;

    protected $lastAccessTokenResponse = null;
    /**
     * The options.
     *
     * @var array
     */
    protected $option = [
        'endpoint'=>'http://dbp.thebizark.com',
        'postfixAuthorize'            => '/oauth/authorize',
        'postfixAccessToken'          => '/oauth/access_token',
        'postfixResourceOwnerDetails' => '/oapi/v1/resource'
    ];

    public function setOption($key='',$value=null)
    {
        if(is_array($key)){
            $this->option = array_merge($this->option,$key);
        }else{
            $this->option[$key] = $value;
        }
    }


    /**
     * Create a new provider instance.
     *
     * @param  Request  $request
     * @param  string  $clientId
     * @param  string  $clientSecret
     * @param  string  $redirectUrl
     * @return void
     */
    public function __construct(Request $request, $clientId, $clientSecret, $redirectUrl)
    {
        parent::__construct($request,$clientId,$clientSecret,$redirectUrl);
        $config = config('services.thebizark',[]);
        if(!empty($config)){
            $this->setOption($config);
        }
    }

    /**
     * @param string $key
     * @param null   $default
     *
     * @return mixed
     */
    public function getOption($key = '', $default = null)
    {
        return array_get($this->option, $key, $default);
    }

    /**
     * {@inheritdoc}
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase($this->getOption('endpoint', 'http://dbp.thebizark.com').$this->getOption('postfixAuthorize', '/oauth/authorize'), $state);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenUrl()
    {
        return $this->getOption('endpoint', 'http://dbp.thebizark.com').$this->getOption('postfixAccessToken', '/oauth/access_token');
    }

    protected function getLastAccessTokenResponse(){
        return $this->lastAccessTokenResponse;
    }
    protected function setLastAccessTokenResponse($response){
        return $this->lastAccessTokenResponse = $response;
    }
    protected function getLastAccessTokenBody(){
        return !empty($this->lastAccessTokenResponse)?json_decode($this->lastAccessTokenResponse,true):null;
    }

    /**
     * Get the access token Body By Code
     *
     * @param  string  $code
     * @return string
     */
    public function getAccessTokenBodyByCode($code)
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getTokenFields($code),
        ]);
        return json_decode($this->setLastAccessTokenResponse($response->getBody()), true);
    }

    /**
     * Get the access token Body By Refresh Token
     *
     * @param  string  $code
     * @return string
     */
    public function getAccessTokenBodyByRefreshToken($refresh_token)
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';
        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getTokenFieldsByRefreshToken($refresh_token),
        ]);
        return json_decode($this->setLastAccessTokenResponse($response->getBody()), true);
    }

    /**
     * Get the access token Body
     *
     * @param  string  $code
     * @return string
     */
    public function getAccessTokenBody()
    {
        $lastAccessTokenBody = $this->getLastAccessTokenBody();
        if(empty($lastAccessTokenBody)){
            $lastAccessTokenBody = $this->getAccessTokenBodyByCode($this->getCode());
        }
        return $lastAccessTokenBody;
    }

    /**
     * Get the access token for the given code.
     *
     * @param  string  $code
     * @return string
     */
    public function getAccessToken($code)
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getTokenFields($code),
        ]);

        return $this->parseAccessToken($this->setLastAccessTokenResponse($response->getBody()));
    }


    /**
     * Get the access token for the given code.
     *
     * @param  string  $code
     * @return string
     */
    public function getAccessTokenByRefreshToken($refresh_token)
    {
        $postKey = (version_compare(ClientInterface::VERSION, '6') === 1) ? 'form_params' : 'body';

        $response = $this->getHttpClient()->post($this->getTokenUrl(), [
            'headers' => ['Accept' => 'application/json'],
            $postKey => $this->getTokenFieldsByRefreshToken($refresh_token),
        ]);

        return $this->parseAccessToken($this->setLastAccessTokenResponse($response->getBody()));
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array
     */
    protected function getTokenFieldsByRefreshToken($refresh_token)
    {
        return [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refresh_token, 'redirect_uri' => $this->redirectUrl,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get($this->getOption('endpoint', 'http://dbp.thebizark.com').$this->getOption('postfixResourceOwnerDetails', '/oapi/v1/resource'), [
            //'query' => ['access_token' => $token],
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    public function getUserOrganizationsByToken($token){
        $response = $this->getHttpClient()->get($this->getOption('endpoint', 'http://dbp.thebizark.com').'/oapi/v1/account/organizations', [
            //'query' => ['access_token' => $token],
            'headers' => [
                'Authorization' => 'Bearer '.$token,
            ],
        ]);
        return json_decode($response->getBody(), true);
    }

    public function getUserOrganizations(){
        if ($this->hasInvalidState()) {
            throw new InvalidStateException;
        }
        return $this->getUserOrganizationsByToken(
            $token = $this->getAccessToken($this->getCode())
        );
    }
    /**
     * {@inheritdoc}
     */
    protected function mapUserToObject(array $user)
    {
        return (new User())->setRaw($user)->map([
            'id'       => $user['id'],
            'nickname' => $user['name'],
            'email' => array_get($user, 'email'),
            'name' => array_get($user, 'username'),
            'avatar' => array_get($user, 'avatar_url'),
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code'
        ]);
    }
}
