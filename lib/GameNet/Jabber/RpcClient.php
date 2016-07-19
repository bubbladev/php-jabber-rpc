<?php
/**
 * The MIT License
 *
 * Copyright (c) 2014, GameNet
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * PHP version 5.4
 *
 * @package GameNet\Jabber
 * @copyright 2014, GameNet
 * @author Vadim Sabirov <vadim.sabirov@syncopate.ru>
 * @license MIT http://opensource.org/licenses/MIT
 */
namespace GameNet\Jabber;

use Comodojo\Xmlrpc\XmlrpcDecoder;

/**
 * Class RpcClient
 *
 * @package   GameNet\Jabber
 * @copyright Copyright (с) 2014, GameNet. All rights reserved.
 * @author    Vadim Sabirov <vadim.sabirov@syncopate.ru>
 * @version   1.0
 */
class RpcClient
{
    use Mixins\UserTrait;
    use Mixins\GroupTrait;
    use Mixins\RoomTrait;
    use Mixins\RosterTrait;

    const VCARD_FULLNAME = 'FN';
    const VCARD_NICKNAME = 'NICKNAME';
    const VCARD_BIRTHDAY = 'BDAY';
    const VCARD_EMAIL = 'EMAIL USERID';
    const VCARD_COUNTRY = 'ADR CTRY';
    const VCARD_CITY = 'ADR LOCALITY';
    const VCARD_DESCRIPTION = 'DESC';
    const VCARD_AVATAR_URL = 'EXTRA PHOTOURL';

    const RESPONSE_MAX_LENGTH = 10000000;

    /**
     * @var string
     */
    protected $server;
    /**
     * @var string
     */
    protected $host;
    /**
     * @var bool
     */
    protected $debug;
    /**
     * @var int
     */
    protected $timeout;
    /**
     * @var string
     */
    protected $username;
    /**
     * @var string
     */
    protected $password;
    /**
     * @var string
     */
    protected $userAgent;

    public function __construct(array $options)
    {
        if (!isset($options['server'])) {
            throw new \InvalidArgumentException("Parameter 'server' is not specified");
        }

        if (!isset($options['host'])) {
            throw new \InvalidArgumentException("Parameter 'host' is not specified");
        }

        $this->server = $options['server'];
        $this->host = $options['host'];
        $this->username = isset($options['username']) ? $options['username'] : '';
        $this->password = isset($options['password']) ? $options['password'] : '';
        $this->debug = isset($options['debug']) ? (bool)$options['debug'] : false;
        $this->timeout = isset($options['timeout']) ? (int)$options['timeout'] : 5;
        $this->userAgent = isset($options['userAgent']) ? $options['userAgent'] : 'GameNet';

        if ($this->username && !$this->password) {
            throw new \InvalidArgumentException("Password cannot be empty if username was defined");
        }
        if (!$this->username && $this->password) {
            throw new \InvalidArgumentException("Username cannot be empty if password was defined");
        }
    }

    /**
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout)
    {
        if (!is_int($timeout) || $timeout < 0) {
            throw new \InvalidArgumentException('Timeout value must be integer');
        }

        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getTimeout()
    {
        return $this->timeout;
    }

    /**
     * @param string $userAgent
     * @return $this
     */
    public function setUserAgent($userAgent)
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    protected function sendRequest($command, array $params)
    {
        if ($this->username && $this->password) {
            $params = [
                ['user' => $this->username, 'server' => $this->server, 'password' => $this->password], $params
            ];
        }

        $request = xmlrpc_encode_request($command, $params, ['encoding' => 'utf-8', 'escaping' => 'markup']);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->server);
        curl_setopt($ch, CURLOPT_FAILONERROR, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: ' . $this->userAgent, 'Content-Type: text/xml']);

        $response = curl_exec($ch);
        curl_close($ch);

        // INFO: We must use a custom parser instead xmlrpc_decode if the answer is longer than 10000000 bytes
        if (strlen($response) > self::RESPONSE_MAX_LENGTH) {
            $xml = (new XmlrpcDecoder)->decodeResponse($response);
        } else {
            $xml = \xmlrpc_decode($response);
        }

        if (!$xml || \xmlrpc_is_fault($xml)) {
            throw new \RuntimeException("Error execution command '$command'' with parameters " . var_export($params, true) . ". Response: ");
        }

        if ($this->debug) {
            var_dump($command, $params, $response);
        }

        return $xml;
    }
}
