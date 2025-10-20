<?php
/**
 * Oracle Cloud Infrastructure Request Signer
 * Implementacja podpisywania requestów OCI z użyciem RSA-SHA256
 */

if (!defined('ABSPATH')) exit;

class PEWIK_OCI_Request_Signer {
    private $user_ocid;
    private $tenancy_ocid;
    private $key_fingerprint;
    private $private_key;
    private $private_key_resource;

    public function __construct() {
        $this->user_ocid = PEWIK_USER_OCID;
        $this->tenancy_ocid = PEWIK_TENANCY_OCID;
        $this->key_fingerprint = PEWIK_KEY_FINGERPRINT;
        $this->private_key = PEWIK_PRIVATE_KEY;

        // Załaduj klucz prywatny
        $this->load_private_key();
    }

    /**
     * Załaduj klucz prywatny z PEM string
     */
    private function load_private_key() {
        $this->private_key_resource = openssl_pkey_get_private($this->private_key);

        if ($this->private_key_resource === false) {
            throw new Exception('Nie udało się załadować klucza prywatnego OCI. Sprawdź format klucza w konfiguracji.');
        }
    }

    /**
     * Podpisz request HTTP dla Oracle Cloud Infrastructure
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $url Pełny URL endpointu
     * @param array $query_params Query parameters (opcjonalne)
     * @param string $body Request body (dla POST/PUT)
     * @return array Nagłówki HTTP do dodania do requesta
     */
    public function sign_request($method, $url, $query_params = array(), $body = null) {
        $method = strtoupper($method);

        // Parsuj URL
        $parsed_url = parse_url($url);
        $host = $parsed_url['host'];
        $path = $parsed_url['path'] ?? '/';

        // Dodaj query params do path jeśli istnieją
        if (!empty($query_params)) {
            $path .= '?' . http_build_query($query_params);
        } elseif (isset($parsed_url['query'])) {
            $path .= '?' . $parsed_url['query'];
        }

        // Data w formacie RFC 7231
        $date = gmdate('D, d M Y H:i:s T');

        // Przygotuj signing string dla OCI
        $signing_headers = array('(request-target)', 'host', 'date');
        $signing_string_parts = array();

        // (request-target)
        $request_target = strtolower($method) . ' ' . $path;
        $signing_string_parts[] = '(request-target): ' . $request_target;

        // host
        $signing_string_parts[] = 'host: ' . $host;

        // date
        $signing_string_parts[] = 'date: ' . $date;

        // Dla POST/PUT dodaj content-length, content-type i x-content-sha256
        $content_length = 0;
        $content_type = 'application/json';
        $content_sha256 = null;

        if (in_array($method, array('POST', 'PUT', 'PATCH')) && $body !== null) {
            $content_length = strlen($body);
            $content_sha256 = base64_encode(hash('sha256', $body, true));

            $signing_headers[] = 'content-length';
            $signing_headers[] = 'content-type';
            $signing_headers[] = 'x-content-sha256';

            $signing_string_parts[] = 'content-length: ' . $content_length;
            $signing_string_parts[] = 'content-type: ' . $content_type;
            $signing_string_parts[] = 'x-content-sha256: ' . $content_sha256;
        }

        // Złóż signing string
        $signing_string = implode("\n", $signing_string_parts);

        // Podpisz używając klucza prywatnego (RSA-SHA256)
        $signature = '';
        $result = openssl_sign($signing_string, $signature, $this->private_key_resource, OPENSSL_ALGO_SHA256);

        if (!$result) {
            throw new Exception('Nie udało się podpisać requestu OCI: ' . openssl_error_string());
        }

        $signature_base64 = base64_encode($signature);

        // Zbuduj Authorization header w formacie OCI
        $key_id = sprintf(
            '%s/%s/%s',
            $this->tenancy_ocid,
            $this->user_ocid,
            $this->key_fingerprint
        );

        $auth_header = sprintf(
            'Signature version="1",headers="%s",keyId="%s",algorithm="rsa-sha256",signature="%s"',
            implode(' ', $signing_headers),
            $key_id,
            $signature_base64
        );

        // Przygotuj wszystkie nagłówki
        $headers = array(
            'date' => $date,
            'host' => $host,
            'authorization' => $auth_header
        );

        // Dodaj nagłówki dla POST/PUT
        if (in_array($method, array('POST', 'PUT', 'PATCH')) && $body !== null) {
            $headers['content-type'] = $content_type;
            $headers['content-length'] = (string)$content_length;
            $headers['x-content-sha256'] = $content_sha256;
        }

        return $headers;
    }

    /**
     * Destruktor - zwolnij zasób klucza prywatnego
     * Uwaga: Od PHP 8.0+ openssl_free_key() jest deprecated i nie jest potrzebne
     * Zasoby są automatycznie zwalniane przez garbage collector
     */
    public function __destruct() {
        // ✅ POPRAWKA: Usunięto wywołanie openssl_free_key() 
        // które jest deprecated od PHP 8.0+
        // Zasoby OpenSSL są teraz automatycznie zarządzane
        
        // Stary kod (USUŃ):
        // if ($this->private_key_resource) {
        //     openssl_free_key($this->private_key_resource);
        // }
    }
}