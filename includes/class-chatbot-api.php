<?php
/**
 * Klasa do komunikacji z Oracle Generative AI Agent
 * Obsługuje wysyłanie wiadomości, zarządzanie sesjami i wyciąganie odpowiedzi
 */

if (!defined('ABSPATH')) exit;

class PEWIK_Chatbot_API {
    private $signer;
    private $endpoint_url;
    private $agent_endpoint_id;
    private $region;
    
    public function __construct() {
        error_log('[PEWIK Chatbot API] === Konstruktor wywołany ===');
        
        $this->signer = new PEWIK_OCI_Request_Signer();
        $this->agent_endpoint_id = PEWIK_AGENT_ENDPOINT_ID;
        $this->region = PEWIK_REGION;
        
        // URL endpointu dla regionu eu-frankfurt-1
        $this->endpoint_url = 'https://agent-runtime.generativeai.' . $this->region . '.oci.oraclecloud.com';
        
        error_log('[PEWIK Chatbot API] Region: ' . $this->region);
        error_log('[PEWIK Chatbot API] Endpoint URL: ' . $this->endpoint_url);
        error_log('[PEWIK Chatbot API] Agent Endpoint ID: ' . $this->agent_endpoint_id);
    }
    
    /**
     * Pobierz informacje o regionie
     */
    public function get_region_info() {
        return array(
            'region' => $this->region,
            'endpoint_url' => $this->endpoint_url,
            'agent_endpoint_id' => $this->agent_endpoint_id
        );
    }
    
    /**
     * Utwórz nową sesję Oracle AI Agent
     * 
     * @return string Session ID
     * @throws Exception
     */
    public function create_session() {
        error_log('[PEWIK Chatbot API] === Rozpoczynam tworzenie sesji ===');
        
        $path = '/20240531/agentEndpoints/' . $this->agent_endpoint_id . '/sessions';
        $full_url = $this->endpoint_url . $path;
        
        error_log('[PEWIK Chatbot API] Full URL: ' . $full_url);
        
        $body = json_encode(array(
            'displayName' => 'WordPress Session - ' . date('Y-m-d H:i:s'),
            'description' => 'PEWIK Chatbot session from WordPress'
        ));
        
        error_log('[PEWIK Chatbot API] Request body: ' . $body);
        
        try {
            error_log('[PEWIK Chatbot API] Podpisuję request...');
            
            // Podpisz request
            $headers = $this->signer->sign_request('POST', $full_url, array(), $body);
            
            error_log('[PEWIK Chatbot API] Request podpisany. Liczba nagłówków: ' . count($headers));
            
            // Formatuj nagłówki dla WordPress HTTP API
            $wp_headers = $this->format_headers_for_wp($headers);
            
            error_log('[PEWIK Chatbot API] Wysyłam POST request do Oracle...');
            
            // Wyślij zapytanie
            $response = wp_remote_post($full_url, array(
                'headers' => $wp_headers,
                'body' => $body,
                'timeout' => 30,
                'sslverify' => true,
                'httpversion' => '1.1'
            ));
            
            error_log('[PEWIK Chatbot API] Otrzymano odpowiedź');
            
            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                error_log('[PEWIK Chatbot API ERROR] WP Error: ' . $error_msg);
                throw new Exception('Błąd tworzenia sesji: ' . $error_msg);
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log('[PEWIK Chatbot API] Response code: ' . $response_code);
            error_log('[PEWIK Chatbot API] Response body (first 500 chars): ' . substr($response_body, 0, 500));
            
            if ($response_code !== 200 && $response_code !== 201) {
                error_log('[PEWIK Chatbot API ERROR] Nieprawidłowy kod odpowiedzi: ' . $response_code);
                throw new Exception('Błąd API przy tworzeniu sesji (kod ' . $response_code . '): ' . $response_body);
            }
            
            $data = json_decode($response_body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('[PEWIK Chatbot API ERROR] JSON parse error: ' . json_last_error_msg());
                throw new Exception('Błąd parsowania JSON: ' . json_last_error_msg());
            }
            
            error_log('[PEWIK Chatbot API] JSON zdekodowany. Struktura: ' . print_r(array_keys($data), true));
            
            $session_id = $data['id'] ?? $data['sessionId'] ?? null;
            
            if (empty($session_id)) {
                error_log('[PEWIK Chatbot API ERROR] Brak session ID w odpowiedzi. Pełna odpowiedź: ' . print_r($data, true));
                throw new Exception('Nie otrzymano session ID z Oracle');
            }
            
            error_log('[PEWIK Chatbot API] ✓ Sesja utworzona pomyślnie! Session ID: ' . $session_id);
            
            return $session_id;
            
        } catch (Exception $e) {
            error_log('[PEWIK Chatbot API ERROR] Exception: ' . $e->getMessage());
            error_log('[PEWIK Chatbot API ERROR] Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Wyślij wiadomość do Oracle AI Agent
     * 
     * @param string $user_message Wiadomość użytkownika
     * @param string $session_id ID sesji (wymagany)
     * @return array Odpowiedź z wiadomością bota i session ID
     * @throws Exception
     */
    public function send_message($user_message, $session_id, $context = null) {
    
        $start_time = microtime(true); // Timer na początku
    
        if (empty($session_id)) {
            throw new Exception('Session ID jest wymagany');
        }
        
        $path = '/20240531/agentEndpoints/' . $this->agent_endpoint_id . '/actions/chat';
        $full_url = $this->endpoint_url . $path;

        $final_message = $user_message;
        
        if ($context && is_array($context)) {
            $page_title = isset($context['pageTitle']) ? $context['pageTitle'] : '';
            $page_url = isset($context['pageUrl']) ? $context['pageUrl'] : '';
            
            // Doklejamy instrukcję systemową niewidoczną dla użytkownika
            $final_message .= "\n\n[SYSTEM_CONTEXT: Użytkownik przegląda stronę: '{$page_title}' (URL: {$page_url}). Jeśli pytanie jest niejasne, wykorzystaj ten kontekst do udzielenia precyzyjnej odpowiedzi.]";
        }
        
        // Przygotuj body - sessionId jest ZAWSZE wymagany
        $request_body = array(
            'userMessage' => $final_message,
            'sessionId' => $session_id,
            'shouldStream' => false
        );
        
        $body_json = json_encode($request_body);
        
        // Podpisz request
        $headers = $this->signer->sign_request('POST', $full_url, array(), $body_json);
        
        // Formatuj nagłówki dla WordPress HTTP API
        $wp_headers = $this->format_headers_for_wp($headers);
        
        // Wyślij zapytanie przez WordPress HTTP API
        $response = wp_remote_post($full_url, array(
            'headers' => $wp_headers,
            'body' => $body_json,
            'timeout' => 45,
            'sslverify' => true,
            'httpversion' => '1.1'
        ));
        
        // Obsługa błędów WordPress HTTP API
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            
            // Loguj błąd jeśli debug jest włączony
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PEWIK Chatbot API Error] ' . $error_message);
            }
            
            return array(
                'error' => true,
                'message' => 'Błąd połączenia z Oracle Cloud: ' . $error_message
            );
        }
        
        // Pobierz kod odpowiedzi i body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Loguj odpowiedź jeśli debug jest włączony
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[PEWIK Chatbot API] Response Code: %d | Body: %s',
                $response_code,
                substr($response_body, 0, 200)
            ));
        }
        
        // WAŻNE: Sprawdź czy sesja wygasła (404)
        if ($response_code === 404) {
            return array(
                'error' => true,
                'message' => 'Sesja wygasła',
                'code' => 404,
                'session_expired' => true
            );
        }
        
        // Sprawdź kod odpowiedzi
        if ($response_code !== 200 && $response_code !== 201) {
            $error_data = json_decode($response_body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Nieznany błąd API';
            
            // Specjalna obsługa częstych błędów
            if ($response_code === 401) {
                $error_message = 'Błąd autoryzacji. Sprawdź konfigurację kluczy API.';
            } elseif ($response_code === 429) {
                $error_message = 'Przekroczono limit zapytań API. Spróbuj ponownie za chwilę.';
            } elseif ($response_code === 500) {
                $error_message = 'Błąd serwera Oracle. Spróbuj ponownie później.';
            }
            
            return array(
                'error' => true,
                'message' => sprintf('Błąd API (kod %d): %s', $response_code, $error_message),
                'code' => $response_code
            );
        }
        
        // Parsuj odpowiedź JSON
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'error' => true,
                'message' => 'Błąd parsowania odpowiedzi JSON: ' . json_last_error_msg()
            );
        }
        
        // Wyciągnij wiadomość z odpowiedzi
        $bot_message = $this->extract_message($data);
        
        // ✅ OBLICZ CZAS ODPOWIEDZI
        $response_time = microtime(true) - $start_time;
        
        // ✅ ZAPISZ DO BAZY DANYCH (LOGOWANIE)
        $this->log_conversation(array(
            'session_id' => $session_id,
            'user_message' => $user_message,
            'bot_response' => $bot_message,
            'user_ip' => $this->get_user_ip(),
            'user_id' => get_current_user_id(),
            'response_time' => $response_time,
            'metadata' => json_encode(array(
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'page_url' => isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '',
                'citations' => isset($data['citations']) ? $data['citations'] : null,
                'traces' => isset($data['traces']) ? $data['traces'] : null,
                'model_used' => 'oracle-ai-agent',
                'endpoint_id' => $this->agent_endpoint_id,
                'response_code' => $response_code
            ))
        ));
        
        // ✅ POBIERZ ID ostatnio dodanej wiadomości
        global $wpdb;
        $message_id = $wpdb->insert_id;
        
        // Zwróć odpowiedź Z ID WIADOMOŚCI
        return array(
            'error' => false,
            'message' => $bot_message,
            'sessionId' => $session_id,
            'messageId' => $message_id,
            'hasTrace' => isset($data['traces']),
            'hasCitations' => isset($data['message']['content']['citations'])
        );
    }
    
    /**
     * Wyciągnij wiadomość tekstową z odpowiedzi API
     */
    private function extract_message($data) {
        // POPRAWNA ŚCIEŻKA: message.content.text
        if (isset($data['message']['content']['text'])) {
            return $data['message']['content']['text'];
        }
        
        // Fallback na inne możliwe struktury (jeśli Oracle zmieni format)
        if (isset($data['message']['content']) && is_array($data['message']['content'])) {
            foreach ($data['message']['content'] as $content_item) {
                if (isset($content_item['text'])) {
                    return $content_item['text'];
                }
            }
        }
        
        if (isset($data['message']['text'])) {
            return $data['message']['text'];
        }
        
        if (isset($data['text'])) {
            return $data['text'];
        }
        
        if (isset($data['response'])) {
            return $data['response'];
        }
        
        // Jeśli nie znaleziono wiadomości, zwróć domyślną
        return 'Przepraszam, nie mogłem wygenerować odpowiedzi. Spróbuj przeformułować pytanie lub skontaktuj się z BOK: 58 66 87 311.';
    }
    
    /**
     * Loguj konwersację do bazy danych
     */
    private function log_conversation($data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'chatbot_conversations';
        
        $wpdb->insert(
            $table_name,
            array(
                'session_id' => $data['session_id'],
                'user_message' => $data['user_message'],
                'bot_response' => $data['bot_response'],
                'user_ip' => $data['user_ip'],
                'user_id' => $data['user_id'],
                'response_time' => $data['response_time'],
                'metadata' => $data['metadata']
            ),
            array(
                '%s', // session_id
                '%s', // user_message
                '%s', // bot_response
                '%s', // user_ip
                '%d', // user_id
                '%f', // response_time
                '%s'  // metadata
            )
        );
    }
    
    /**
     * Pobierz IP użytkownika
     */
    private function get_user_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return 'unknown';
    }
    
    /**
     * Formatuj nagłówki dla WordPress HTTP API
     */
    private function format_headers_for_wp($headers) {
        $wp_headers = array();
        foreach ($headers as $key => $value) {
            // WordPress wymaga nagłówków z wielkimi literami na początku
            $header_name = implode('-', array_map('ucfirst', explode('-', $key)));
            $wp_headers[$header_name] = $value;
        }
        return $wp_headers;
    }
    
    /**
     * Sprawdź status endpointu (opcjonalne)
     */
    public function check_endpoint_status() {
        $path = '/20240531/agentEndpoints/' . $this->agent_endpoint_id;
        $full_url = $this->endpoint_url . $path;
        
        try {
            $headers = $this->signer->sign_request('GET', $full_url);
            $wp_headers = $this->format_headers_for_wp($headers);
            
            $response = wp_remote_get($full_url, array(
                'headers' => $wp_headers,
                'timeout' => 15,
                'sslverify' => true
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'status' => 'error',
                    'message' => $response->get_error_message()
                );
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            
            return array(
                'status' => $response_code === 200 ? 'active' : 'inactive',
                'code' => $response_code
            );
            
        } catch (Exception $e) {
            return array(
                'status' => 'error',
                'message' => $e->getMessage()
            );
        }
    }
}