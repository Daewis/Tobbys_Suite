<?php
/**
 * Supabase Auth Bridge for Tobby's Suite
 */
class SupabaseAuth {
    private $url;
    private $key;

    public function __construct() {
        $this->url = rtrim($_ENV['SUPABASE_URL'] ?? '', '/');
        $this->key = $_ENV['SUPABASE_KEY'] ?? '';
    }

    public function signUp($email, $password, $metadata = []) {
        $data = [
            'email'    => $email,
            'password' => $password,
            // 'data' must always be a JSON object {}, never an array []
            // empty PHP array [] encodes to [] — stdClass encodes to {}
            'data'     => empty($metadata) ? new \stdClass() : (object) $metadata
        ];
        return $this->request('POST', '/auth/v1/signup', $data);
    }

    public function verifyOtp($email, $token, $type = 'signup') {
        $data = [
            'email' => $email,
            'token' => $token,
            'type'  => $type
        ];
        return $this->request('POST', '/auth/v1/verify', $data);
    }

    private function request($method, $path, $data = null) {
        $ch = curl_init($this->url . $path);

        $headers = [
            'apikey: ' . $this->key,
            'Authorization: Bearer ' . $this->key,
            'Content-Type: application/json'
        ];

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);

        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // cURL-level failure (no network, bad URL, etc.)
        if ($response === false) {
            return ['error' => 'cURL Error: ' . $curlErr, 'status' => 0];
        }

        $result = json_decode($response, true);

        // Supabase returns errors under 'msg' or 'error_description'
        if ($status >= 400) {
            $msg = $result['msg']
                ?? $result['error_description']
                ?? $result['message']
                ?? 'Unknown auth error';
            return ['error' => $msg, 'status' => $status];
        }

        return $result;
    }
}