<?php

// A small browser for the end-to-end and demo scripts: a cookie jar, the CSRF token of the session, redirects looked at by hand.
// Used by webday.php and devdata.php.

class Web
{
    private $base;
    private $jar;
    private $token = '';

    public function __construct($base)
    {
        $this->base = $base;
        $this->jar = tempnam(sys_get_temp_dir(), 'e2ejar');
    }

    public function request($method, $path, array $fields = null)
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar, CURLOPT_COOKIEFILE => $this->jar, CURLOPT_TIMEOUT => 180,
            CURLOPT_CUSTOMREQUEST => $method === 'GET' ? 'GET' : 'POST',
        ]);
        if ($method !== 'GET') {
            $fields = ($fields ?: []) + ['_token' => $this->token];
            if ($method !== 'POST') {
                $fields['_method'] = $method;
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            throw new RuntimeException('request failed: ' . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headers = substr($raw, 0, $size);
        preg_match('/^Location:\s*(\S+)/mi', $headers, $m);

        return ['status' => $status, 'headers' => $headers, 'body' => substr($raw, $size), 'location' => isset($m[1]) ? preg_replace('#^https?://[^/]+#', '', $m[1]) : null];
    }

    public function get($path)
    {
        return $this->request('GET', $path);
    }

    // POST (or PUT / DELETE) and follow the redirect once, so the page with the flash message or the errors is returned too.
    public function send($method, $path, array $fields = [])
    {
        $r = $this->request($method, $path, $fields);
        $r['page'] = $r['location'] ? $this->get($r['location']) : null;

        return $r;
    }

    public function login($username, $password = 'secret1')
    {
        $page = $this->get('/login');
        preg_match('/name="_token" value="([^"]+)"/', $page['body'], $m);
        $this->token = $m[1];
        $r = $this->request('POST', '/login', ['username' => $username, 'password' => $password]);
        $home = $this->get('/');
        if (preg_match('/name="_token" value="([^"]+)"/', $home['body'], $m)) {
            $this->token = $m[1]; // the session gets a new token at login
        }

        return $r['status'] === 302 && $home['status'] === 200;
    }
}
