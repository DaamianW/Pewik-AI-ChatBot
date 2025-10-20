<?php
/**
 * Plugin Name: PEWIK AI Chatbot
 * Plugin URI: https://pewik.gdynia.pl
 * Description: Integracja Oracle Generative AI Agent z WordPress - Asystent PEWIK Gdynia
 * Version: 1.0.0
 * Author: Damian Wasilewski
 * Text Domain: pewik-ai-chatbot
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

// ========================================
// KONFIGURACJA - UZUPEŁNIJ TE DANE!
// ========================================

// Dane Twojego agenta (już uzupełnione)
define('PEWIK_AGENT_ENDPOINT_ID', 'ocid1.genaiagentendpoint.oc1.eu-frankfurt-1.amaaaaaabpav2yyaoeurgfnmaocvgq2hrsj247okmu5segpt5hj5gzejbpyq');
define('PEWIK_AGENT_ID', 'ocid1.genaiagent.oc1.eu-frankfurt-1.amaaaaaabpav2yya5imv22dukw5u2outumspiz3hbhfg2j67gi6yi7ukqdnq');
define('PEWIK_REGION', 'eu-frankfurt-1');

// UZUPEŁNIJ TE DANE Z OCI CONSOLE (API Keys):
define('PEWIK_USER_OCID', 'ocid1.user.oc1..aaaaaaaa52ojeh3lyclptbndf3wb7obdavt32w3k2alzvehmps22nerf4k4a');
define('PEWIK_TENANCY_OCID', 'ocid1.tenancy.oc1..aaaaaaaahakj6sqsxfouv57essllobaj4euh6e24mxa2ab7i6ktjuju4fxiq');
define('PEWIK_KEY_FINGERPRINT', 'ee:c2:22:96:fe:c6:b1:a7:7c:af:ca:ae:82:7f:71:94');

// Wklej CAŁY klucz prywatny (z -----BEGIN PRIVATE KEY----- i -----END PRIVATE KEY-----)
define('PEWIK_PRIVATE_KEY', '-----BEGIN PRIVATE KEY-----
MIIEvAIBADANBgkqhkiG9w0BAQEFAASCBKYwggSiAgEAAoIBAQC89W2j3Z75Yt2m
fhrYghifSXA0wI0GO1D6l8UpSDIWfXHPmqFAUJP6uwRp0J3+xy0BYhP5h8fC3GRe
DMAG5WYVU0PJI9Nur4K9x/nwO61D5O2UV4fHimLNWQ/bSkVAiUth0D5aj2tf5qjU
c3xllMfu9P2F2anuZUEo3y67ZMGQmhdOWioWRcOHcRoYBCIzwBnSQflD0qmGnzcM
p6/gzxE+Zf6iREJI5vUVY3j+KmotEaUWordNt7Ci11yb7r7Qo1N0LTTHsF0XsEih
cULpgmfmgaEjsyW6Zesu3IIyh6V8v7aBhXa5XLahcyyY3fSav+8zbKgphz6cZU/B
S6PGO9hzAgMBAAECggEAHrhNSZAaQbPPvTr7OlUhk6Po2MAFLWIvWXjwgN76kFV3
T1J1rMhs28fT1oV9aj0ParjQmTIjSTbIynw1gT7Xndfvnp/IcVJEuyiWeyFN8J/N
+t6ldcy1y2oTUzTRQBtnZKY/vOaxhOHcc3hjB6YXQG4WrtAi+bL/KO8/4GS9Dg5M
y6tHyOGiI5Pn/Q+8q3wmZf3Jb3WwjQebNHoCyu96EZITfwJE3iXBZyG6k062uZxT
RU0WV5Rcjfop9Af5/D4dIknc7RIVYxkkENxV9tx3vD51OsryE24f/o3UZjKfLhRW
A4or8gxKQ70w4pafHxyQmQvvUIrcZYz54ZYWmDkeoQKBgQDtNj+1UDNjwtBjKEe1
idNQ0mPnZblKkhy9Nc2VL6aPQElnQhPHvkQSCCr682WXtNwowCJeMmjGeVFZzcfx
vpTcoUj6s45xE5SDPxrf96RCnu6V+gPgukS3TcmDHGtA5/r7EuaW5nNdcCmxf29E
TnnTYkDiM2De6ymy7DkkT2D/7wKBgQDL7MnuQCCGiN0Mg3X3uAh7rVaSNL7ebN2E
R/En3j4TcXAdnNryBqYZ8KpW5rKDRMgoVRVKzXW1BbS4nwMg51FrHvgbq4bO9odN
13OZfUtTfZEFn2F/R/Md2qe7nijSYkJgEXzErx8hIPY0ReDS84qkBxMpMp0b3LRv
JsPQI7prvQKBgBXLBx1cSexfaI/Dkpr+F5j0S1NmCBjuxY8ok0OihhXhHR1Md87B
DzXs5C38EJhYeGWSCVZIIVIisTOj8Tune7utYawOtQZ0ew93y7tJ4CByw46p0pNh
6ZBBqELQaJYk+ez5NpAkifLKrDnvcESBRTYDb9yYRc0VI9aZV0KbvFinAoGAXRyN
Rz/4mfU8GU6dOrLJDM+ky7VRwXWr346Jyk5rwaz2KE9KmV/3z7hXzr4fnFh3nBLd
Wf5eVH16eyH/57I3NtY5K0kykKV4Ok659ceD8WdQJGUVu2w60dLY643XzdgXvo29
joD3kcTfJhcSBMA2+ZZRZWo62lH4ARiOKCdoI3ECgYAOoFYNHkMZFzL08tF+szui
4UHjQ1B3NyUKZRTHVIrOloNgMQlN0TBS4skisn6T08dNcdISFyIQC71oeX6dWJD8
bh1fRq5kUxu0BUtqvXMQw/VWW8/cHMooUfSzEZGEhCb/WFvn1/6IbNS+L2aHYHgr
91FQtsnCho34P0IfswXb6w==
-----END PRIVATE KEY-----');

// ========================================
// KONIEC KONFIGURACJI
// ========================================

// ✅ ZAŁADUJ WSZYSTKIE KLASY POMOCNICZE
require_once plugin_dir_path(__FILE__) . 'includes/class-oci-signer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-chatbot-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-corrections-manager.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-markdown-exporter.php';

/**
 * ✅ JEDEN activation hook - tworzy WSZYSTKIE tabele
 */
register_activation_hook(__FILE__, 'pewik_chatbot_create_tables');

function pewik_chatbot_create_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // 1. Tabela conversations (główna)
    $table_conversations = $wpdb->prefix . 'chatbot_conversations';
    
    $sql_conversations = "CREATE TABLE IF NOT EXISTS $table_conversations (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_message text NOT NULL,
        bot_response text NOT NULL,
        user_ip varchar(45),
        user_id bigint(20),
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        response_time float,
        rating int(1),
        feedback text,
        metadata longtext,
        category varchar(100) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY timestamp (timestamp),
        KEY idx_rating_timestamp (rating, timestamp)
    ) $charset_collate;";
    
    // 2. Tabela corrections (NOWA)
    $table_corrections = $wpdb->prefix . 'chatbot_corrections';
    
    $sql_corrections = "CREATE TABLE IF NOT EXISTS $table_corrections (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        conversation_id bigint(20) NOT NULL,
        original_question text NOT NULL,
        original_answer text NOT NULL,
        corrected_answer text,
        correction_notes text,
        category varchar(100) DEFAULT 'Ogólne',
        priority enum('low','medium','high','critical') DEFAULT 'medium',
        status enum('pending','in_progress','approved','rejected','exported') DEFAULT 'pending',
        corrected_by_user_id bigint(20) DEFAULT NULL,
        correction_timestamp datetime DEFAULT NULL,
        approved_timestamp datetime DEFAULT NULL,
        exported_timestamp datetime DEFAULT NULL,
        export_filename varchar(255) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_conversation_id (conversation_id),
        KEY idx_status (status),
        KEY idx_category (category),
        KEY idx_priority (priority)
    ) $charset_collate;";
    
    // 3. Tabela categories (NOWA)
    $table_categories = $wpdb->prefix . 'chatbot_categories';
    
    $sql_categories = "CREATE TABLE IF NOT EXISTS $table_categories (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        slug varchar(100) NOT NULL,
        description text,
        keywords text,
        icon varchar(50) DEFAULT '📁',
        color varchar(7) DEFAULT '#0066CC',
        sort_order int(11) DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_conversations);
    dbDelta($sql_corrections);
    dbDelta($sql_categories);
    
    // Dodaj domyślne kategorie
    pewik_chatbot_insert_default_categories();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Funkcja do wstawiania domyślnych kategorii
 */
function pewik_chatbot_insert_default_categories() {
    global $wpdb;
    $table_categories = $wpdb->prefix . 'chatbot_categories';
    
    // Sprawdź czy kategorie już istnieją
    $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_categories");
    
    if ($count > 0) {
        return; // Już są kategorie
    }
    
    // Domyślne kategorie dla PEWiK
    $default_categories = array(
        array(
            'name' => 'Awarie',
            'slug' => 'awarie',
            'description' => 'Zgłaszanie i usuwanie awarii wodociągowych i kanalizacyjnych',
            'keywords' => 'awaria,awarie,zgłoszenie,zgłosić,994,przeciek,brak wody,nie ma wody,uszkodzenie,naprawa',
            'icon' => '⚠️',
            'color' => '#dc3232',
            'sort_order' => 1
        ),
        array(
            'name' => 'Płatności',
            'slug' => 'platnosci',
            'description' => 'Rachunki, terminy płatności, faktury, opłaty',
            'keywords' => 'płatność,rachunek,faktura,termin,opłata,zapłacić,przelew,karta,online,rata',
            'icon' => '💳',
            'color' => '#00aa44',
            'sort_order' => 2
        ),
        array(
            'name' => 'Wodomierze',
            'slug' => 'wodomierze',
            'description' => 'Odczyty, wymiana, legalizacja wodomierzy',
            'keywords' => 'wodomierz,licznik,odczyt,wymiana,legalizacja,plomba,montaż,zużycie',
            'icon' => '📊',
            'color' => '#0066cc',
            'sort_order' => 3
        ),
        array(
            'name' => 'Kontakt',
            'slug' => 'kontakt',
            'description' => 'Dane kontaktowe, godziny otwarcia BOK, adresy',
            'keywords' => 'kontakt,telefon,email,adres,godziny,BOK,biuro,obsługa,klienta',
            'icon' => '📞',
            'color' => '#826eb4',
            'sort_order' => 4
        ),
        array(
            'name' => 'Przyłącza',
            'slug' => 'przylacza',
            'description' => 'Budowa i modernizacja przyłączy wodociągowych',
            'keywords' => 'przyłącze,budowa,projekt,wniosek,zgoda,warunki,techniczne',
            'icon' => '🔧',
            'color' => '#f56e28',
            'sort_order' => 5
        ),
        array(
            'name' => 'Jakość wody',
            'slug' => 'jakosc-wody',
            'description' => 'Badania i parametry wody pitnej',
            'keywords' => 'jakość,badania,twardość,parametry,analiza,czystość,normy,smak,zapach',
            'icon' => '💧',
            'color' => '#2ea3f2',
            'sort_order' => 6
        ),
        array(
            'name' => 'Ogólne',
            'slug' => 'ogolne',
            'description' => 'Pytania ogólne i inne tematy',
            'keywords' => 'ogólne,inne,pytanie,informacja',
            'icon' => '📁',
            'color' => '#666666',
            'sort_order' => 99
        )
    );
    
    foreach ($default_categories as $cat) {
        $wpdb->insert(
            $table_categories,
            $cat,
            array('%s', '%s', '%s', '%s', '%s', '%s', '%d')
        );
    }
}

/**
 * Aktywacja pluginu - sprawdzanie wymagań
 */
register_activation_hook(__FILE__, 'pewik_chatbot_activate');
function pewik_chatbot_activate() {
    // Sprawdź czy OpenSSL jest dostępne
    if (!extension_loaded('openssl')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('PEWIK AI Chatbot wymaga rozszerzenia PHP OpenSSL. Skontaktuj się z administratorem serwera.');
    }
    
    // Sprawdź wersję PHP
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('PEWIK AI Chatbot wymaga PHP 7.4 lub nowszego. Twoja wersja: ' . PHP_VERSION);
    }
    
    // Twórz tabele zostanie wywołane przez osobny hook wyżej
    flush_rewrite_rules();
}

/**
 * Deaktywacja pluginu
 */
register_deactivation_hook(__FILE__, 'pewik_chatbot_deactivate');
function pewik_chatbot_deactivate() {
    flush_rewrite_rules();
}

/**
 * ✅ ZAREJESTRUJ MENU ADMINA
 */
add_action('admin_menu', 'pewik_chatbot_admin_menu');

/**
 * Zarejestruj REST API endpoint dla chatbota
 */
add_action('rest_api_init', function() {
    register_rest_route('pewik-chatbot/v1', '/chat', array(
        'methods' => 'POST',
        'callback' => 'pewik_chatbot_handle_message',
        'permission_callback' => '__return_true',
        'args' => array(
            'message' => array(
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            ),
            'sessionId' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field'
            )
        )
    ));
    
    // Endpoint do tworzenia nowej sesji
    register_rest_route('pewik-chatbot/v1', '/session/create', array(
        'methods' => 'POST',
        'callback' => 'pewik_chatbot_create_session',
        'permission_callback' => '__return_true'
    ));
    
    // Endpoint do resetowania sesji
    register_rest_route('pewik-chatbot/v1', '/session/reset', array(
        'methods' => 'POST',
        'callback' => 'pewik_chatbot_reset_session',
        'permission_callback' => '__return_true'
    ));

    register_rest_route('pewik-chatbot/v1', '/rate', array(
        'methods' => 'POST',
        'callback' => 'pewik_chatbot_rate_message',
        'permission_callback' => '__return_true',
        'args' => array(
            'messageId' => array(
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint'
            ),
            'rating' => array(
                'required' => true,
                'type' => 'integer',
                'validate_callback' => function($param) {
                    return in_array($param, array(1, -1));
                }
            ),
            'feedback' => array(
                'required' => false,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_textarea_field'
            )
        )
    ));
});

/**
 * Utwórz nową sesję Oracle AI Agent
 */
function pewik_chatbot_create_session($request) {
    error_log('[PEWIK Chatbot] === FUNKCJA CREATE_SESSION WYWOŁANA ===');
    
    try {
        // Sprawdź czy klasa istnieje
        if (!class_exists('PEWIK_Chatbot_API')) {
            error_log('[PEWIK Chatbot ERROR] Klasa PEWIK_Chatbot_API nie istnieje!');
            throw new Exception('Klasa API chatbota nie została załadowana');
        }
        
        error_log('[PEWIK Chatbot] Tworzenie instancji PEWIK_Chatbot_API...');
        $chatbot = new PEWIK_Chatbot_API();
        
        error_log('[PEWIK Chatbot] Wywołanie create_session()...');
        $session_id = $chatbot->create_session();
        
        error_log('[PEWIK Chatbot] Sesja utworzona: ' . $session_id);
        
        return rest_ensure_response(array(
            'success' => true,
            'sessionId' => $session_id
        ));
        
    } catch (Exception $e) {
        error_log('[PEWIK Chatbot ERROR] Exception w create_session: ' . $e->getMessage());
        error_log('[PEWIK Chatbot ERROR] Stack trace: ' . $e->getTraceAsString());
        
        return new WP_Error(
            'session_error',
            'Nie udało się utworzyć sesji: ' . $e->getMessage(),
            array('status' => 500)
        );
    }
}

/**
 * Obsługa oceny wiadomości (thumbs up/down)
 */
function pewik_chatbot_rate_message($request) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_conversations';
    
    $message_id = $request->get_param('messageId');
    $rating = $request->get_param('rating');
    $feedback = $request->get_param('feedback');
    
    // Sprawdź czy wiadomość istnieje
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE id = %d",
        $message_id
    ));
    
    if (!$exists) {
        return new WP_Error(
            'message_not_found',
            'Wiadomość nie została znaleziona',
            array('status' => 404)
        );
    }
    
    // Aktualizuj ocenę
    $update_data = array('rating' => $rating);
    
    if (!empty($feedback)) {
        $update_data['feedback'] = $feedback;
    }
    
    $result = $wpdb->update(
        $table_name,
        $update_data,
        array('id' => $message_id),
        array('%d', '%s'),
        array('%d')
    );
    
    if ($result === false) {
        return new WP_Error(
            'update_failed',
            'Nie udało się zapisać oceny',
            array('status' => 500)
        );
    }
    
    // Loguj ocenę jeśli debug włączony
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log(sprintf(
            '[PEWIK Chatbot] Ocena wiadomości #%d: %s',
            $message_id,
            $rating == 1 ? '👍 Pozytywna' : '👎 Negatywna'
        ));
    }
    
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Dziękujemy za opinię!',
        'rating' => $rating
    ));
}

/**
 * Obsługa wiadomości chatbota
 */
function pewik_chatbot_handle_message($request) {
    $user_message = $request->get_param('message');
    $session_id = $request->get_param('sessionId');
    
    if (empty($user_message)) {
        return new WP_Error(
            'empty_message', 
            'Wiadomość nie może być pusta', 
            array('status' => 400)
        );
    }
    
    // Rate limiting - max 30 wiadomości na godzinę z jednego IP
    $user_ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (!pewik_chatbot_check_rate_limit($user_ip)) {
        return new WP_Error(
            'rate_limit_exceeded',
            'Przekroczono limit wiadomości. Spróbuj ponownie za godzinę.',
            array('status' => 429)
        );
    }
    
    try {
        $chatbot = new PEWIK_Chatbot_API();
        
        // Jeśli nie ma sessionId, utwórz nową sesję
        if (empty($session_id)) {
            $session_id = $chatbot->create_session();
        }
        
        $response = $chatbot->send_message($user_message, $session_id);
        
        // Loguj wiadomości (opcjonalnie)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[PEWIK Chatbot] User: %s | Bot: %s | Session: %s',
                $user_message,
                substr($response['message'] ?? 'error', 0, 100),
                $session_id
            ));
        }
        
        return rest_ensure_response($response);
        
    } catch (Exception $e) {
        return new WP_Error(
            'chatbot_error',
            'Wystąpił błąd: ' . $e->getMessage(),
            array('status' => 500)
        );
    }
}

/**
 * Reset sesji chatbota
 */
function pewik_chatbot_reset_session($request) {
    return rest_ensure_response(array(
        'success' => true,
        'message' => 'Sesja została zresetowana'
    ));
}

/**
 * Rate limiting
 */
function pewik_chatbot_check_rate_limit($user_ip) {
    $transient_key = 'pewik_chatbot_rate_' . md5($user_ip);
    $count = get_transient($transient_key);
    
    if ($count && $count >= 30) {
        return false;
    }
    
    set_transient($transient_key, ($count ? $count + 1 : 1), HOUR_IN_SECONDS);
    return true;
}

/**
 * Dodaj chatbot widget do stopki strony
 */
add_action('wp_footer', 'pewik_chatbot_add_widget');
function pewik_chatbot_add_widget() {
    // Możesz ograniczyć wyświetlanie do konkretnych stron
    // if (!is_front_page()) return;
    
    ?>
    <div id="pewik-chatbot-container">
        <div id="pewik-chatbot-button" title="Otwórz czat z asystentem PEWIK">
            <svg width="28" height="28" viewBox="0 0 24 24" fill="white">
                <path d="M20 2H4c-1.1 0-2 .9-2 2v18l4-4h14c1.1 0 2-.9 2-2V4c0-1.1-.9-2-2-2z"/>
            </svg>
        </div>
        
        <div id="pewik-chatbot-window" style="display: none;">
            <div id="pewik-chatbot-header">
                <div>
                    <h3>Asystent PEWIK GDYNIA</h3>
                </div>
                <button id="pewik-chatbot-close" aria-label="Zamknij czat">×</button>
            </div>
            
            <div id="pewik-chatbot-messages">
                <div class="message bot-message initial-message">
                Cześć! W czym mogę pomóc? Jestem wirtualnym asystentem, korzystającym z informacji zawartych na stronie. Mogę pomóc Ci w odnalezieniu poszukiwanych informacji.
                </div>
            </div>
            
            <div id="pewik-chatbot-input-container">
                <input 
                    type="text" 
                    id="pewik-chatbot-input" 
                    placeholder="Napisz wiadomość..."
                    maxlength="500"
                    autocomplete="off"
                >
                <button id="pewik-chatbot-send" aria-label="Wyślij wiadomość">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Załaduj style i skrypty
 */
add_action('wp_enqueue_scripts', 'pewik_chatbot_enqueue_assets');
function pewik_chatbot_enqueue_assets() {
    // CSS
    wp_enqueue_style(
        'pewik-chatbot-css',
        plugin_dir_url(__FILE__) . 'assets/css/chatbot.css',
        array(),
        '1.0.0'
    );
    
    // JavaScript
    wp_enqueue_script(
        'pewik-chatbot-js',
        plugin_dir_url(__FILE__) . 'assets/js/chatbot.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    // Przekaż dane do JavaScript
    wp_localize_script('pewik-chatbot-js', 'pewikChatbot', array(
        'chatUrl' => rest_url('pewik-chatbot/v1/chat'),
        'sessionCreateUrl' => rest_url('pewik-chatbot/v1/session/create'),
        'sessionResetUrl' => rest_url('pewik-chatbot/v1/session/reset'),
        'nonce' => wp_create_nonce('wp_rest'),
        'agentName' => 'Asystent PEWIK Gdynia',
        'region' => PEWIK_REGION
    ));
}

/**
 * Menu w panelu admina
 */
function pewik_chatbot_admin_menu() {
    // Główna strona
    add_menu_page(
        'PEWIK AI Chatbot',
        'AI Chatbot',
        'manage_options',
        'pewik-chatbot',
        'pewik_chatbot_admin_page',
        'dashicons-format-chat',
        30
    );
    
    // ✅ NOWE: Korekty odpowiedzi
    add_submenu_page(
        'pewik-chatbot',
        'Korekty odpowiedzi',
        '✏️ Korekty',
        'manage_options',
        'pewik-chatbot-corrections',
        'pewik_chatbot_corrections_page'
    );
    
    // Statystyki
    add_submenu_page(
        'pewik-chatbot',
        'Statystyki rozmów',
        'Statystyki',
        'manage_options',
        'pewik-chatbot-stats',
        'pewik_chatbot_stats_page'
    );
    
    // Przegląd ocen
    add_submenu_page(
        'pewik-chatbot',
        'Przegląd ocen',
        'Oceny 👍👎',
        'manage_options',
        'pewik-chatbot-ratings',
        'pewik_chatbot_ratings_page'
    );
    
    // ✅ ZMODYFIKOWANE: Eksport (teraz tylko do MD)
    add_submenu_page(
        'pewik-chatbot',
        'Eksport do Markdown',
        'Eksport 📥',
        'manage_options',
        'pewik-chatbot-export',
        'pewik_chatbot_export_page'
    );
}

/**
 * ✅ NOWA STRONA: Panel korekt
 */
function pewik_chatbot_corrections_page() {
    
    $manager = new PEWIK_Corrections_Manager();
    
    // Obsługa akcji
    if (isset($_POST['action'])) {
        check_admin_referer('pewik_corrections_action', 'pewik_corrections_nonce');
        
        switch ($_POST['action']) {
            case 'auto_create':
                $created = $manager->auto_create_from_negative_ratings();
                echo '<div class="notice notice-success"><p>Utworzono ' . $created . ' nowych korekt z negatywnych ocen.</p></div>';
                break;
                
            case 'approve':
                if (isset($_POST['correction_id'])) {
                    $manager->approve_correction(intval($_POST['correction_id']));
                    echo '<div class="notice notice-success"><p>Korekta została zatwierdzona.</p></div>';
                }
                break;
                
            case 'reject':
                if (isset($_POST['correction_id'])) {
                    $reason = isset($_POST['reject_reason']) ? sanitize_textarea_field($_POST['reject_reason']) : '';
                    $manager->reject_correction(intval($_POST['correction_id']), $reason);
                    echo '<div class="notice notice-info"><p>Korekta została odrzucona.</p></div>';
                }
                break;
        }
    }
    
    // Filtry
    $filter_status = isset($_GET['filter_status']) ? sanitize_text_field($_GET['filter_status']) : 'all';
    $filter_category = isset($_GET['filter_category']) ? sanitize_text_field($_GET['filter_category']) : '';
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    
    // Pobierz korekty
    $args = array(
        'limit' => 20,
        'offset' => (isset($_GET['paged']) ? (max(1, intval($_GET['paged'])) - 1) * 20 : 0)
    );
    
    if ($filter_status !== 'all') {
        $args['status'] = $filter_status;
    }
    
    if (!empty($filter_category)) {
        $args['category'] = $filter_category;
    }
    
    if (!empty($search_query)) {
        $args['search'] = $search_query;
    }
    
    $corrections = $manager->get_corrections($args);
    $stats = $manager->get_stats();
    $categories = $manager->get_categories();
    
    // Wyświetl interfejs
    include plugin_dir_path(__FILE__) . 'admin/corrections-list.php';
}

/**
 * REST API endpoints dla korekt
 */
add_action('rest_api_init', function() {
    // Zapisz korektę
    register_rest_route('pewik-chatbot/v1', '/corrections/save', array(
        'methods' => 'POST',
        'callback' => 'pewik_chatbot_api_save_correction',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Zatwierdź korektę
    register_rest_route('pewik-chatbot/v1', '/corrections/approve', array(
        'methods' => 'POST',
        'callback' => 'pewik_chatbot_api_approve_correction',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
    
    // Eksportuj do Markdown
    register_rest_route('pewik-chatbot/v1', '/corrections/export-md', array(
        'methods' => 'POST',
        'callback' => 'pewik_chatbot_api_export_markdown',
        'permission_callback' => function() {
            return current_user_can('manage_options');
        }
    ));
});

/**
 * API: Zapisz korektę
 */
function pewik_chatbot_api_save_correction($request) {
    $correction_id = $request->get_param('correction_id');
    $corrected_answer = $request->get_param('corrected_answer');
    $notes = $request->get_param('correction_notes');
    $category = $request->get_param('category');
    
    if (!$correction_id || !$corrected_answer) {
        return new WP_Error('missing_params', 'Brak wymaganych parametrów', array('status' => 400));
    }
    
    $manager = new PEWIK_Corrections_Manager();
    $success = $manager->save_correction($correction_id, $corrected_answer, $notes, $category);
    
    if ($success) {
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Korekta została zapisana'
        ));
    } else {
        return new WP_Error('save_failed', 'Nie udało się zapisać korekty', array('status' => 500));
    }
}

/**
 * API: Zatwierdź korektę
 */
function pewik_chatbot_api_approve_correction($request) {
    $correction_id = $request->get_param('correction_id');
    
    if (!$correction_id) {
        return new WP_Error('missing_params', 'Brak ID korekty', array('status' => 400));
    }
    
    $manager = new PEWIK_Corrections_Manager();
    $success = $manager->approve_correction($correction_id);
    
    if ($success) {
        return rest_ensure_response(array(
            'success' => true,
            'message' => 'Korekta została zatwierdzona'
        ));
    } else {
        return new WP_Error('approve_failed', 'Nie udało się zatwierdzić korekty', array('status' => 500));
    }
}

/**
 * API: Eksportuj do Markdown
 */
function pewik_chatbot_api_export_markdown($request) {
    $correction_ids = $request->get_param('correction_ids'); // array
    $options = $request->get_param('options'); // array
    
    $exporter = new PEWIK_Markdown_Exporter();
    $markdown = $exporter->export_to_markdown($correction_ids, $options);
    
    // Zwróć jako plain text
    $response = new WP_REST_Response($markdown);
    $response->set_headers(array(
        'Content-Type' => 'text/markdown; charset=utf-8'
    ));
    
    return $response;
}

/**
 * Strona ustawień w panelu admina
 */
function pewik_chatbot_admin_page() {
    ?>
    <div class="wrap">
        <h1>🤖 PEWIK AI Chatbot</h1>
        
        <div class="card">
            <h2>Status połączenia</h2>
            <table class="form-table">
                <tr>
                    <th>Agent ID:</th>
                    <td><code><?php echo esc_html(PEWIK_AGENT_ID); ?></code></td>
                </tr>
                <tr>
                    <th>Endpoint ID:</th>
                    <td><code><?php echo esc_html(PEWIK_AGENT_ENDPOINT_ID); ?></code></td>
                </tr>
                <tr>
                    <th>Region:</th>
                    <td><strong><?php echo esc_html(PEWIK_REGION); ?></strong> (Frankfurt)</td>
                </tr>
                <tr>
                    <th>User OCID:</th>
                    <td><code><?php echo esc_html(substr(PEWIK_USER_OCID, 0, 40)); ?>...</code></td>
                </tr>
                <tr>
                    <th>Status konfiguracji:</th>
                    <td>
                        <?php if (strpos(PEWIK_USER_OCID, 'TUTAJ') === false): ?>
                            <span style="color: green; font-weight: bold;">✓ Skonfigurowany poprawnie</span>
                        <?php else: ?>
                            <span style="color: red; font-weight: bold;">✗ Wymaga konfiguracji</span>
                            <p><strong>Uzupełnij dane API w pliku oracle-ai-chatbot.php</strong></p>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>
        </div>
        
        <div class="card">
            <h2>Instrukcje konfiguracji</h2>
            <ol>
                <li>Zaloguj się do <a href="https://cloud.oracle.com" target="_blank">OCI Console</a></li>
                <li>Kliknij ikonę profilu → <strong>User Settings</strong></li>
                <li>Przejdź do <strong>API Keys</strong> → <strong>Add API Key</strong></li>
                <li>Pobierz klucz prywatny i skopiuj dane z Configuration Preview</li>
                <li>Edytuj plik <code>oracle-ai-chatbot.php</code> i wklej dane</li>
                <li>Odśwież stronę - chatbot pojawi się w prawym dolnym rogu</li>
            </ol>
        </div>
        
        <div class="card">
            <h2>Test połączenia</h2>
            <p>Odwiedź swoją stronę i kliknij ikonę chatbota w prawym dolnym rogu.</p>
            <p>Spróbuj zadać pytanie: <strong>"Jak zgłosić awarię?"</strong></p>
        </div>
        
        <div class="card">
            <h2>Przydatne linki</h2>
            <ul>
                <li><a href="https://docs.oracle.com/en-us/iaas/Content/generative-ai-agents/home.htm" target="_blank">Dokumentacja Oracle AI Agents</a></li>
                <li><a href="https://cloud.oracle.com/generative-ai/agents" target="_blank">OCI Console - Agents</a></li>
                <li><a href="<?php echo admin_url('plugins.php'); ?>">Zarządzaj pluginami</a></li>
                <li><a href="https://pewik.gdynia.pl" target="_blank">Strona PEWIK Gdynia</a></li>
            </ul>
        </div>
    </div>
    
    <style>
        .card { 
            background: white; 
            padding: 20px; 
            margin: 20px 0; 
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border-left: 4px solid #0066CC;
        }
        .card h2 { 
            margin-top: 0; 
            color: #0066CC;
        }
        .card code { 
            background: #f5f5f5; 
            padding: 2px 6px; 
            border-radius: 3px;
            font-size: 12px;
        }
        .card ol li, .card ul li {
            margin-bottom: 20px;
        }
    </style>
    <?php
}

/**
 * Strona przeglądu ocen z filtrowaniem
 */
function pewik_chatbot_ratings_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_conversations';
    
    // Obsługa filtrów
    $filter_rating = isset($_GET['filter_rating']) ? sanitize_text_field($_GET['filter_rating']) : 'all';
    $search_query = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
    $items_per_page = 20;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $items_per_page;
    
    // Buduj SQL query z filtrami
    $where_clauses = array();
    
    if ($filter_rating === 'positive') {
        $where_clauses[] = "rating = 1";
    } elseif ($filter_rating === 'negative') {
        $where_clauses[] = "rating = -1";
    } elseif ($filter_rating === 'rated') {
        $where_clauses[] = "rating IS NOT NULL";
    } elseif ($filter_rating === 'unrated') {
        $where_clauses[] = "rating IS NULL";
    }
    
    if (!empty($search_query)) {
        $where_clauses[] = $wpdb->prepare(
            "(user_message LIKE %s OR bot_response LIKE %s)",
            '%' . $wpdb->esc_like($search_query) . '%',
            '%' . $wpdb->esc_like($search_query) . '%'
        );
    }
    
    $where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';
    
    // Pobierz całkowitą liczbę wyników
    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name $where_sql");
    $total_pages = ceil($total_items / $items_per_page);
    
    // Pobierz wyniki z paginacją
    $conversations = $wpdb->get_results("
        SELECT * FROM $table_name 
        $where_sql
        ORDER BY timestamp DESC 
        LIMIT $items_per_page OFFSET $offset
    ");
    
    // Statystyki
    $total_positive = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE rating = 1");
    $total_negative = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE rating = -1");
    $total_unrated = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE rating IS NULL");
    $total_conversations = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    
    ?>
    <div class="wrap">
        <h1>📊 Przegląd Ocen Chatbota</h1>
        
        <!-- Statystyki -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0;">
            <div class="rating-stat-card positive">
                <div class="stat-number"><?php echo number_format($total_positive); ?></div>
                <div class="stat-label">👍 Pozytywne</div>
            </div>
            <div class="rating-stat-card negative">
                <div class="stat-number"><?php echo number_format($total_negative); ?></div>
                <div class="stat-label">👎 Negatywne</div>
            </div>
            <div class="rating-stat-card neutral">
                <div class="stat-number"><?php echo number_format($total_unrated); ?></div>
                <div class="stat-label">⚪ Nieocenione</div>
            </div>
            <div class="rating-stat-card total">
                <div class="stat-number"><?php echo number_format($total_conversations); ?></div>
                <div class="stat-label">💬 Wszystkie</div>
            </div>
        </div>
        
        <!-- Filtry i wyszukiwanie -->
        <div class="card" style="padding: 20px; margin-bottom: 20px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="pewik-chatbot-ratings">
                
                <div style="display: flex; gap: 15px; align-items: center;">
                    <div>
                        <label for="filter_rating"><strong>Filtruj:</strong></label>
                        <select name="filter_rating" id="filter_rating" onchange="this.form.submit()">
                            <option value="all" <?php selected($filter_rating, 'all'); ?>>Wszystkie</option>
                            <option value="positive" <?php selected($filter_rating, 'positive'); ?>>👍 Pozytywne</option>
                            <option value="negative" <?php selected($filter_rating, 'negative'); ?>>👎 Negatywne</option>
                            <option value="rated" <?php selected($filter_rating, 'rated'); ?>>Ocenione (wszystkie)</option>
                            <option value="unrated" <?php selected($filter_rating, 'unrated'); ?>>⚪ Nieocenione</option>
                        </select>
                    </div>
                    
                    <div style="flex: 1;">
                        <label for="search"><strong>Szukaj:</strong></label>
                        <input 
                            type="text" 
                            name="search" 
                            id="search" 
                            value="<?php echo esc_attr($search_query); ?>" 
                            placeholder="Szukaj w pytaniach i odpowiedziach..."
                            style="width: 100%;"
                        >
                    </div>
                    
                    <div style="padding-top: 22px;">
                        <button type="submit" class="button button-primary">🔍 Szukaj</button>
                        <?php if (!empty($search_query) || $filter_rating !== 'all'): ?>
                            <a href="?page=pewik-chatbot-ratings" class="button">Wyczyść filtry</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- Tabela z konwersacjami -->
        <?php if ($conversations): ?>
            <form method="post" action="<?php echo admin_url('admin.php?page=pewik-chatbot-export'); ?>">
                <?php wp_nonce_field('pewik_export_conversations', 'pewik_export_nonce'); ?>
                
                <div style="margin-bottom: 15px;">
                    <button type="submit" name="export_selected" class="button button-primary">
                        📥 Eksportuj zaznaczone do AI Eval
                    </button>
                    <span style="margin-left: 15px; color: #666;">
                        Znaleziono: <strong><?php echo number_format($total_items); ?></strong> rozmów
                    </span>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all"></th>
                            <th style="width: 50px;">Ocena</th>
                            <th style="width: 140px;">Data</th>
                            <th style="width: 35%;">Pytanie użytkownika</th>
                            <th style="width: 35%;">Odpowiedź bota</th>
                            <th style="width: 80px;">Czas</th>
                            <th style="width: 100px;">Akcje</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($conversations as $conv): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="conversation_ids[]" value="<?php echo $conv->id; ?>">
                            </td>
                            <td style="text-align: center; font-size: 24px;">
                                <?php
                                if ($conv->rating == 1) {
                                    echo '<span title="Pozytywna">👍</span>';
                                } elseif ($conv->rating == -1) {
                                    echo '<span title="Negatywna" style="opacity: 0.6;">👎</span>';
                                } else {
                                    echo '<span title="Nieoceniona" style="opacity: 0.3;">⚪</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <small><?php echo date('Y-m-d H:i', strtotime($conv->timestamp)); ?></small>
                            </td>
                            <td>
                                <div class="conversation-text">
                                    <?php echo esc_html(mb_substr($conv->user_message, 0, 200)); ?>
                                    <?php if (mb_strlen($conv->user_message) > 200): ?>
                                        <span class="show-more" onclick="showFullText(<?php echo $conv->id; ?>, 'user')">... więcej</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div class="conversation-text">
                                    <?php echo esc_html(mb_substr($conv->bot_response, 0, 200)); ?>
                                    <?php if (mb_strlen($conv->bot_response) > 200): ?>
                                        <span class="show-more" onclick="showFullText(<?php echo $conv->id; ?>, 'bot')">... więcej</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($conv->response_time): ?>
                                    <small><?php echo number_format($conv->response_time, 2); ?>s</small>
                                <?php else: ?>
                                    <small>-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button 
                                    type="button" 
                                    class="button button-small" 
                                    onclick="viewDetails(<?php echo $conv->id; ?>)"
                                    title="Zobacz szczegóły"
                                >
                                    👁️ Szczegóły
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Paginacja -->
                <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom" style="margin-top: 20px;">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo; Poprzednia',
                            'next_text' => 'Następna &raquo;',
                            'total' => $total_pages,
                            'current' => $current_page,
                            'type' => 'plain'
                        ));
                        echo $page_links;
                        ?>
                    </div>
                </div>
                <?php endif; ?>
            </form>
        <?php else: ?>
            <div class="notice notice-info">
                <p>Brak rozmów do wyświetlenia z wybranymi filtrami.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Modal ze szczegółami -->
    <div id="conversation-modal" style="display: none;">
        <div class="modal-overlay" onclick="closeModal()"></div>
        <div class="modal-content">
            <span class="modal-close" onclick="closeModal()">&times;</span>
            <h2>Szczegóły rozmowy</h2>
            <div id="modal-body"></div>
        </div>
    </div>
    
    <style>
        .rating-stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-left: 4px solid #ddd;
        }
        .rating-stat-card.positive { border-left-color: #46b450; }
        .rating-stat-card.negative { border-left-color: #dc3232; }
        .rating-stat-card.neutral { border-left-color: #999; }
        .rating-stat-card.total { border-left-color: #0073aa; }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 15px;
        }
        .rating-stat-card.positive .stat-number { color: #46b450; }
        .rating-stat-card.negative .stat-number { color: #dc3232; }
        .rating-stat-card.neutral .stat-number { color: #999; }
        .rating-stat-card.total .stat-number { color: #0073aa; }
        
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        
        .conversation-text {
            line-height: 1.6;
        }
        
        .show-more {
            color: #0073aa;
            cursor: pointer;
            text-decoration: underline;
        }
        
        .show-more:hover {
            color: #005177;
        }
        
        #conversation-modal .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.7);
            z-index: 100000;
        }
        
        #conversation-modal .modal-content {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            z-index: 100001;
        }
        
        .modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 28px;
            cursor: pointer;
            color: #999;
        }
        
        .modal-close:hover {
            color: #333;
        }
        
        .detail-row {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .detail-label {
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        
        .detail-value {
            background: #f5f5f5;
            padding: 10px;
            border-radius: 4px;
            white-space: pre-wrap;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // Select all checkbox
        $('#select-all').on('change', function() {
            $('input[name="conversation_ids[]"]').prop('checked', this.checked);
        });
    });
    
    function viewDetails(conversationId) {
        jQuery.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: 'get_conversation_details',
                conversation_id: conversationId,
                nonce: '<?php echo wp_create_nonce('get_conversation_details'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const data = response.data;
                    let html = '';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">📅 Data i czas:</div>';
                    html += '<div class="detail-value">' + data.timestamp + '</div>';
                    html += '</div>';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">💬 Pytanie użytkownika:</div>';
                    html += '<div class="detail-value">' + data.user_message + '</div>';
                    html += '</div>';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">🤖 Odpowiedź bota:</div>';
                    html += '<div class="detail-value">' + data.bot_response + '</div>';
                    html += '</div>';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">⭐ Ocena:</div>';
                    html += '<div class="detail-value">' + data.rating_display + '</div>';
                    html += '</div>';
                    
                    if (data.feedback) {
                        html += '<div class="detail-row">';
                        html += '<div class="detail-label">💭 Feedback:</div>';
                        html += '<div class="detail-value">' + data.feedback + '</div>';
                        html += '</div>';
                    }
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">⏱️ Czas odpowiedzi:</div>';
                    html += '<div class="detail-value">' + data.response_time + '</div>';
                    html += '</div>';
                    
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">🔑 Session ID:</div>';
                    html += '<div class="detail-value" style="font-family: monospace; font-size: 11px;">' + data.session_id + '</div>';
                    html += '</div>';
                    
                    jQuery('#modal-body').html(html);
                    jQuery('#conversation-modal').fadeIn(200);
                }
            }
        });
    }
    
    function closeModal() {
        jQuery('#conversation-modal').fadeOut(200);
    }
    
    // Zamknij modal przy ESC
    jQuery(document).keyup(function(e) {
        if (e.key === "Escape") {
            closeModal();
        }
    });
    </script>
    <?php
}

/**
 * Strona eksportu do AI Eval
 */


function pewik_chatbot_export_page() {
    $manager = new PEWIK_Corrections_Manager();
    $exporter = new PEWIK_Markdown_Exporter();
    
    // Obsługa eksportu
    if (isset($_POST['export_action']) && check_admin_referer('pewik_export_markdown', 'pewik_export_nonce')) {
        
        $export_type = sanitize_text_field($_POST['export_action']);
        $correction_ids = array();
        
        switch ($export_type) {
            case 'export_selected':
                if (isset($_POST['correction_ids']) && is_array($_POST['correction_ids'])) {
                    $correction_ids = array_map('intval', $_POST['correction_ids']);
                }
                break;
                
            case 'export_all_approved':
                $corrections = $manager->get_corrections(array(
                    'status' => 'approved',
                    'limit' => 1000
                ));
                $correction_ids = array_map(function($c) { return $c->id; }, $corrections);
                break;
                
            case 'export_by_category':
                $category = sanitize_text_field($_POST['export_category']);
                $corrections = $manager->get_corrections(array(
                    'status' => 'approved',
                    'category' => $category,
                    'limit' => 1000
                ));
                $correction_ids = array_map(function($c) { return $c->id; }, $corrections);
                break;
        }
        
        if (!empty($correction_ids)) {
            // Opcje eksportu
            $options = array(
                'include_metadata' => isset($_POST['include_metadata']),
                'include_notes' => isset($_POST['include_notes']),
                'group_by_category' => isset($_POST['group_by_category']),
                'add_toc' => isset($_POST['add_toc'])
            );
            
            // Generuj Markdown
            $markdown = $exporter->export_to_markdown($correction_ids, $options);
            
            // Oznacz jako wyeksportowane
            $filename = 'pewik-korekty-' . date('Y-m-d-His') . '.md';
            foreach ($correction_ids as $id) {
                $manager->mark_as_exported($id, $filename);
            }
            
            // Pobierz plik
            $exporter->download_markdown($markdown, $filename);
            exit;
        } else {
            echo '<div class="notice notice-error"><p>Nie wybrano żadnych korekt do eksportu.</p></div>';
        }
    }
    
    // Statystyki
    $stats = $manager->get_stats();
    $categories = $manager->get_categories();
    
    // Pobierz zatwierdzone korekty do wyświetlenia
    $approved_corrections = $manager->get_corrections(array(
        'status' => 'approved',
        'limit' => 100
    ));
    
    ?>
    <div class="wrap pewik-export-page">
        <h1>📥 Eksport korekt do Markdown</h1>
        
        <p class="description" style="font-size: 15px; margin-bottom: 30px;">
            Eksportuj zatwierdzone korekty do formatu <strong>Markdown (.md)</strong>, 
            gotowego do uploadu do Oracle Knowledge Base.
        </p>
        
        <!-- Statystyki eksportu -->
        <div class="export-stats">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo number_format($stats['approved']); ?></div>
                <div class="stat-label">Gotowe do eksportu</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📤</div>
                <div class="stat-number"><?php echo number_format($stats['exported']); ?></div>
                <div class="stat-label">Już wyeksportowane</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo count($approved_corrections); ?></div>
                <div class="stat-label">Dostępne teraz</div>
            </div>
        </div>
        
        <?php if ($stats['approved'] == 0): ?>
            <div class="notice notice-warning">
                <p><strong>Brak zatwierdzonych korekt do eksportu.</strong></p>
                <p>Przejdź do <a href="?page=pewik-chatbot-corrections">panelu korekt</a> 
                   i zatwierdź odpowiedzi przed eksportem.</p>
            </div>
        <?php else: ?>
            
            <!-- Szybki eksport -->
            <div class="card export-quick">
                <h2>⚡ Szybki eksport</h2>
                <p>Eksportuj wszystkie zatwierdzone korekty jednym kliknięciem.</p>
                
                <form method="post">
                    <?php wp_nonce_field('pewik_export_markdown', 'pewik_export_nonce'); ?>
                    <input type="hidden" name="export_action" value="export_all_approved">
                    
                    <div class="export-options">
                        <label>
                            <input type="checkbox" name="include_metadata" value="1" checked>
                            📊 Dołącz metadane (ID, status, data)
                        </label>
                        <label>
                            <input type="checkbox" name="include_notes" value="1" checked>
                            💬 Dołącz notatki administratora
                        </label>
                        <label>
                            <input type="checkbox" name="group_by_category" value="1" checked>
                            📁 Grupuj po kategoriach
                        </label>
                        <label>
                            <input type="checkbox" name="add_toc" value="1" checked>
                            📋 Dodaj spis treści
                        </label>
                    </div>
                    
                    <button type="submit" class="button button-primary button-hero">
                        📥 Eksportuj wszystkie zatwierdzone (<?php echo $stats['approved']; ?>)
                    </button>
                </form>
            </div>
            
            <!-- Eksport per kategoria -->
            <div class="card export-by-category">
                <h2>📁 Eksport według kategorii</h2>
                <p>Eksportuj korekty z konkretnej kategorii.</p>
                
                <form method="post">
                    <?php wp_nonce_field('pewik_export_markdown', 'pewik_export_nonce'); ?>
                    <input type="hidden" name="export_action" value="export_by_category">
                    
                    <div style="display: flex; gap: 15px; align-items: end; margin-bottom: 20px;">
                        <div style="flex: 1;">
                            <label for="export_category"><strong>Wybierz kategorię:</strong></label>
                            <select name="export_category" id="export_category" style="width: 100%;">
                                <?php 
                                // Pokaż tylko kategorie z zatwierdzonymi korektami
                                foreach ($stats['by_category'] as $cat_stat):
                                    if ($cat_stat['count'] == 0) continue;
                                    
                                    $cat_obj = array_filter($categories, function($c) use ($cat_stat) {
                                        return $c->name === $cat_stat['category'];
                                    });
                                    $cat_obj = reset($cat_obj);
                                    $icon = $cat_obj ? $cat_obj->icon : '📁';
                                    
                                    // Policz zatwierdzone
                                    global $wpdb;
                                    $table = $wpdb->prefix . 'chatbot_corrections';
                                    $approved_count = $wpdb->get_var($wpdb->prepare(
                                        "SELECT COUNT(*) FROM {$table} WHERE category = %s AND status = 'approved'",
                                        $cat_stat['category']
                                    ));
                                    
                                    if ($approved_count > 0):
                                ?>
                                    <option value="<?php echo esc_attr($cat_stat['category']); ?>">
                                        <?php echo esc_html($icon . ' ' . $cat_stat['category'] . ' (' . $approved_count . ')'); ?>
                                    </option>
                                <?php 
                                    endif;
                                endforeach; 
                                ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="button button-secondary button-large">
                                📥 Eksportuj kategorię
                            </button>
                        </div>
                    </div>
                    
                    <div class="export-options">
                        <label>
                            <input type="checkbox" name="include_metadata" value="1" checked>
                            📊 Dołącz metadane
                        </label>
                        <label>
                            <input type="checkbox" name="include_notes" value="1" checked>
                            💬 Dołącz notatki
                        </label>
                        <label>
                            <input type="checkbox" name="group_by_category" value="1">
                            📁 Grupuj podkategorie
                        </label>
                        <label>
                            <input type="checkbox" name="add_toc" value="1" checked>
                            📋 Dodaj spis treści
                        </label>
                    </div>
                </form>
            </div>
            
            <!-- Eksport wybranych -->
            <div class="card export-selected">
                <h2>✅ Eksport wybranych korekt</h2>
                <p>Zaznacz konkretne korekty do eksportu.</p>
                
                <form method="post">
                    <?php wp_nonce_field('pewik_export_markdown', 'pewik_export_nonce'); ?>
                    <input type="hidden" name="export_action" value="export_selected">
                    
                    <div style="margin-bottom: 15px;">
                        <button type="button" id="select-all-corrections" class="button">
                            ✅ Zaznacz wszystkie
                        </button>
                        <button type="button" id="deselect-all-corrections" class="button">
                            ❌ Odznacz wszystkie
                        </button>
                        <span style="margin-left: 15px; font-weight: 600;">
                            Zaznaczono: <span id="selected-count">0</span>
                        </span>
                    </div>
                    
                    <div class="corrections-checklist">
                        <?php foreach ($approved_corrections as $correction): 
                            $cat_obj = array_filter($categories, function($c) use ($correction) {
                                return $c->name === $correction->category;
                            });
                            $cat_obj = reset($cat_obj);
                            $cat_icon = $cat_obj ? $cat_obj->icon : '📁';
                            $cat_color = $cat_obj ? $cat_obj->color : '#666';
                        ?>
                            <label class="correction-checkbox-item">
                                <input 
                                    type="checkbox" 
                                    name="correction_ids[]" 
                                    value="<?php echo $correction->id; ?>"
                                    class="correction-checkbox"
                                >
                                <div class="correction-preview">
                                    <div class="correction-preview-header">
                                        <span class="correction-id">#<?php echo $correction->id; ?></span>
                                        <span class="correction-category" style="background: <?php echo esc_attr($cat_color); ?>20; color: <?php echo esc_attr($cat_color); ?>;">
                                            <?php echo esc_html($cat_icon . ' ' . $correction->category); ?>
                                        </span>
                                    </div>
                                    <div class="correction-preview-question">
                                        <?php echo esc_html(mb_substr($correction->original_question, 0, 100)); ?>
                                        <?php echo mb_strlen($correction->original_question) > 100 ? '...' : ''; ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="export-options" style="margin-top: 20px;">
                        <label>
                            <input type="checkbox" name="include_metadata" value="1" checked>
                            📊 Dołącz metadane
                        </label>
                        <label>
                            <input type="checkbox" name="include_notes" value="1" checked>
                            💬 Dołącz notatki
                        </label>
                        <label>
                            <input type="checkbox" name="group_by_category" value="1" checked>
                            📁 Grupuj po kategoriach
                        </label>
                        <label>
                            <input type="checkbox" name="add_toc" value="1" checked>
                            📋 Dodaj spis treści
                        </label>
                    </div>
                    
                    <button type="submit" class="button button-primary button-large" style="margin-top: 20px;">
                        📥 Eksportuj zaznaczone
                    </button>
                </form>
            </div>
            
        <?php endif; ?>
        
        <!-- Instrukcja uploadu -->
        <div class="card export-instructions">
            <h2>📚 Instrukcja użycia wygenerowanego pliku</h2>
            
            <ol style="line-height: 2; font-size: 15px;">
                <li>
                    <strong>Pobierz plik .md</strong> - zostanie automatycznie pobrany po kliknięciu "Eksportuj"
                </li>
                <li>
                    <strong>Zaloguj się do Oracle Cloud Console</strong>
                    <br><a href="https://cloud.oracle.com" target="_blank">https://cloud.oracle.com</a>
                </li>
                <li>
                    <strong>Przejdź do Knowledge Base:</strong>
                    <br><code>Generative AI Agents → Twój Agent → Knowledge Bases</code>
                </li>
                <li>
                    <strong>Wybierz Object Storage bucket</strong>
                    <br>Domyślnie: <code>pewik-knowledge-base</code>
                </li>
                <li>
                    <strong>Upload pliku:</strong>
                    <br>Wrzuć plik do folderu <code>knowledge-base/corrections/</code>
                </li>
                <li>
                    <strong>Poczekaj ~5 minut</strong> na automatyczną reindeksację
                </li>
                <li>
                    <strong>Gotowe!</strong> Agent automatycznie zacznie używać zaktualizowanej wiedzy
                </li>
            </ol>
            
            <div style="background: #e3f2fd; padding: 20px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #2196f3;">
                <h3 style="margin-top: 0;">💡 Wskazówki:</h3>
                <ul style="line-height: 1.8;">
                    <li>
                        <strong>Organizacja plików:</strong> Zalecamy nazywanie plików wg daty, np. 
                        <code>korekty-2025-01-15.md</code>
                    </li>
                    <li>
                        <strong>Struktura folderów:</strong> 
                        <code>knowledge-base/corrections/2025-01/korekty-2025-01-15.md</code>
                    </li>
                    <li>
                        <strong>Aktualizacje:</strong> Możesz nadpisać stary plik lub dodać nowy - 
                        agent używa wszystkich dokumentów w bucketcie
                    </li>
                    <li>
                        <strong>Testowanie:</strong> Po uploadzie przetestuj agenta z kilkoma pytaniami, 
                        które były poprawiane
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- Statystyki per kategoria -->
        <?php if (!empty($stats['by_category'])): ?>
        <div class="card export-stats-detail">
            <h2>📊 Statystyki per kategoria</h2>
            
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Kategoria</th>
                        <th style="text-align: center;">Zatwierdzone</th>
                        <th style="text-align: center;">Wyeksportowane</th>
                        <th style="text-align: center;">Oczekujące</th>
                        <th style="text-align: center;">Wszystkie</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    global $wpdb;
                    $table = $wpdb->prefix . 'chatbot_corrections';
                    
                    foreach ($stats['by_category'] as $cat_stat): 
                        $cat_obj = array_filter($categories, function($c) use ($cat_stat) {
                            return $c->name === $cat_stat['category'];
                        });
                        $cat_obj = reset($cat_obj);
                        $icon = $cat_obj ? $cat_obj->icon : '📁';
                        
                        // Policz per status
                        $approved = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table} WHERE category = %s AND status = 'approved'",
                            $cat_stat['category']
                        ));
                        $exported = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table} WHERE category = %s AND status = 'exported'",
                            $cat_stat['category']
                        ));
                        $pending = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$table} WHERE category = %s AND status IN ('pending', 'in_progress')",
                            $cat_stat['category']
                        ));
                    ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($icon . ' ' . $cat_stat['category']); ?></strong>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-approved"><?php echo $approved; ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-exported"><?php echo $exported; ?></span>
                            </td>
                            <td style="text-align: center;">
                                <span class="badge badge-pending"><?php echo $pending; ?></span>
                            </td>
                            <td style="text-align: center;">
                                <strong><?php echo $cat_stat['count']; ?></strong>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
    </div>
    
    <style>
/* ----- OGÓLNE STYLE STRONY EKSPORTU ----- */
.pewik-export-page h1 {
    font-size: 28px;
    font-weight: 600;
    margin-bottom: 8px;
}

.pewik-export-page .description {
    font-size: 15px;
    color: #555;
    max-width: 800px;
    margin-bottom: 30px;
}

/* ----- KARTY STATYSTYK (STYL Z KOREKT) ----- */
.export-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 30px;
}

.export-stats .stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-left: 4px solid #ddd;
}
.export-stats .stat-card .stat-icon {
    font-size: 28px;
    margin-bottom: 10px;
}
.export-stats .stat-card .stat-number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 10px;
}
.export-stats .stat-card .stat-label {
    font-size: 14px;
    color: #555;
}
/* Kolory dla statystyk */
.export-stats .stat-card:nth-child(1) { border-left-color: #46b450; }
.export-stats .stat-card:nth-child(1) .stat-number { color: #46b450; }
.export-stats .stat-card:nth-child(2) { border-left-color: #826eb4; }
.export-stats .stat-card:nth-child(2) .stat-number { color: #826eb4; }
.export-stats .stat-card:nth-child(3) { border-left-color: #0073aa; }
.export-stats .stat-card:nth-child(3) .stat-number { color: #0073aa; }


/* ----- GŁÓWNE KARTY ZAWARTOŚCI ----- */
.pewik-export-page .card {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 25px;
    margin: 0 0 20px;
}

.pewik-export-page .card h2 {
    margin-top: 0;
    padding-bottom: 15px;
    border-bottom: 1px solid #e0e0e0;
    font-size: 20px;
}

/* ----- OPCJE EKSPORTU I FORMULARZE ----- */
.export-options {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin: 20px 0;
    padding: 15px;
    background: #f9f9f9;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
}

.export-options label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
}

.pewik-export-page select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2220%22%20height%3D%2220%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22%23555555%22%3E%3Cpath%20d%3D%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20%2F%3E%3C%2Fsvg%3E');
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1.25em;
    padding-right: 2.5rem !important;
    height: 38px;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
    padding-left: 12px;
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
    cursor: pointer;
    background-color: #fff;
}

/* ----- CHECKLISTA KOREKT ----- */
.corrections-checklist {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 10px;
    background: #f9f9f9;
}

.correction-checkbox-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px;
    margin-bottom: 8px;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.correction-checkbox-item:hover {
    border-color: #0073aa;
    box-shadow: 0 2px 5px rgba(0,0,0,0.07);
}

.correction-checkbox-item input[type="checkbox"] {
    flex-shrink: 0;
}

.correction-preview { flex: 1; }
.correction-preview-header { display: flex; gap: 10px; margin-bottom: 8px; align-items: center; }
.correction-preview-question { font-size: 14px; line-height: 1.5; color: #333; }

.correction-id {
    font-weight: 600;
    color: #555;
    font-size: 13px;
    font-family: monospace;
}

.correction-category {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

/* ----- STATYSTYKI PER KATEGORIA (TABELA) ----- */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.badge-approved { background: #e8f5e9; color: #1b5e20; }
.badge-exported { background: #f3e5f5; color: #4a148c; }
.badge-pending { background: #fff8e1; color: #6d4c02; }

/* ----- INSTRUKCJE ----- */
.export-instructions ol li {
    margin-bottom: 15px;
    line-height: 1.6;
}
.export-instructions code {
    background: #e0e0e0;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 13px;
    font-family: monospace;
}

/* ----- MEDIA QUERIES (RESPONSYWNOŚĆ) ----- */
@media (max-width: 960px) {
    .export-stats {
        grid-template-columns: 1fr 1fr;
    }
}
@media (max-width: 768px) {
    .export-stats,
    .export-options {
        grid-template-columns: 1fr;
    }
}
</style>
    
    <script>
    jQuery(document).ready(function($) {
        // Zaznacz wszystkie
        $('#select-all-corrections').on('click', function() {
            $('.correction-checkbox').prop('checked', true);
            updateSelectedCount();
        });
        
        // Odznacz wszystkie
        $('#deselect-all-corrections').on('click', function() {
            $('.correction-checkbox').prop('checked', false);
            updateSelectedCount();
        });
        
        // Update licznika
        $('.correction-checkbox').on('change', updateSelectedCount);
        
        function updateSelectedCount() {
            const count = $('.correction-checkbox:checked').length;
            $('#selected-count').text(count);
        }
        
        // Inicjalizuj licznik
        updateSelectedCount();
    });
    </script>
    
    <?php
}

/**
 * Wykonaj eksport do JSONL
 */
function pewik_chatbot_do_export($conversation_ids) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_conversations';
    
    if (empty($conversation_ids)) {
        wp_die('Brak rozmów do eksportu');
    }
    
    $ids_placeholder = implode(',', array_fill(0, count($conversation_ids), '%d'));
    
    $conversations = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id IN ($ids_placeholder) ORDER BY timestamp DESC",
        ...$conversation_ids
    ));
    
    if (empty($conversations)) {
        wp_die('Nie znaleziono rozmów');
    }
    
    // Generuj JSONL
    $jsonl_data = '';
    foreach ($conversations as $conv) {
        $entry = array(
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $conv->user_message
                ),
                array(
                    'role' => 'assistant',
                    'content' => $conv->bot_response
                )
            ),
            'rating' => $conv->rating,
            'session_id' => $conv->session_id,
            'timestamp' => date('c', strtotime($conv->timestamp)),
            'response_time' => $conv->response_time,
            'conversation_id' => $conv->id
        );
        
        $jsonl_data .= json_encode($entry, JSON_UNESCAPED_UNICODE) . "\n";
    }
    
    // Wyślij plik do pobrania
    $filename = 'pewik-chatbot-export-' . date('Y-m-d-His') . '.jsonl';
    
    header('Content-Type: application/x-ndjson');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . strlen($jsonl_data));
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    echo $jsonl_data;
    exit;
}

/**
 * Strona statystyk - zaktualizowana wersja
 */
function pewik_chatbot_stats_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_conversations';
    
    // Sprawdź czy tabela istnieje
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    if (!$table_exists) {
        echo '<div class="wrap">';
        echo '<h1>⚠️ Tabela nie istnieje</h1>';
        echo '<div class="notice notice-error"><p>Tabela <code>' . $table_name . '</code> nie została utworzona.</p></div>';
        echo '<button onclick="location.reload();" class="button button-primary">Utwórz tabelę</button>';
        echo '</div>';
        
        pewik_chatbot_create_logs_table();
        return;
    }
    
    // Pobierz statystyki
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    $today = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(timestamp) = CURDATE()");
    $this_week = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE YEARWEEK(timestamp, 1) = YEARWEEK(CURDATE(), 1)");
    $this_month = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE YEAR(timestamp) = YEAR(CURDATE()) AND MONTH(timestamp) = MONTH(CURDATE())");
    $avg_time = $wpdb->get_var("SELECT AVG(response_time) FROM $table_name WHERE response_time IS NOT NULL");
    
    // Statystyki ocen
    $positive_ratings = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE rating = 1");
    $negative_ratings = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE rating = -1");
    $total_rated = $positive_ratings + $negative_ratings;
    $rating_percentage = $total > 0 ? ($total_rated / $total) * 100 : 0;
    $satisfaction_rate = $total_rated > 0 ? ($positive_ratings / $total_rated) * 100 : 0;
    
    // Ostatnie rozmowy
    $recent = $wpdb->get_results("
        SELECT * FROM $table_name 
        ORDER BY timestamp DESC 
        LIMIT 10
    ");
    
    // Najpopularniejsze pytania
    $popular_questions = $wpdb->get_results("
        SELECT user_message, COUNT(*) as count 
        FROM $table_name 
        GROUP BY user_message 
        ORDER BY count DESC 
        LIMIT 5
    ", ARRAY_A);
    
    ?>
    <div class="wrap">
        <h1>📊 Statystyki Chatbota PEWIK</h1>
        
        <!-- Główne statystyki -->
        <h2>Ogólne statystyki</h2>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
            <div class="stat-card total-card">
                <div class="stat-icon">💬</div>
                <div class="stat-number"><?php echo number_format($total); ?></div>
                <div class="stat-label">Wszystkie rozmowy</div>
            </div>
            
            <div class="stat-card today-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number"><?php echo number_format($today); ?></div>
                <div class="stat-label">Dzisiaj</div>
            </div>
            
            <div class="stat-card week-card">
                <div class="stat-icon">📆</div>
                <div class="stat-number"><?php echo number_format($this_week); ?></div>
                <div class="stat-label">W tym tygodniu</div>
            </div>
            
            <div class="stat-card month-card">
                <div class="stat-icon">🗓️</div>
                <div class="stat-number"><?php echo number_format($this_month); ?></div>
                <div class="stat-label">W tym miesiącu</div>
            </div>
        </div>
        
        <!-- Statystyki wydajności i ocen -->
        <h2>Wydajność i oceny użytkowników</h2>
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin: 20px 0;">
            <div class="stat-card time-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-number"><?php echo number_format($avg_time, 2); ?>s</div>
                <div class="stat-label">Średni czas odpowiedzi</div>
            </div>
            
            <div class="stat-card positive-card">
                <div class="stat-icon">👍</div>
                <div class="stat-number"><?php echo number_format($positive_ratings); ?></div>
                <div class="stat-label">Pozytywne oceny</div>
            </div>
            
            <div class="stat-card negative-card">
                <div class="stat-icon">👎</div>
                <div class="stat-number"><?php echo number_format($negative_ratings); ?></div>
                <div class="stat-label">Negatywne oceny</div>
            </div>
            
            <div class="stat-card satisfaction-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-number"><?php echo number_format($satisfaction_rate, 1); ?>%</div>
                <div class="stat-label">Poziom zadowolenia</div>
                <div class="stat-sublabel"><?php echo number_format($rating_percentage, 1); ?>% ocenionych</div>
            </div>
        </div>
        
        <!-- Wykres trendu ocen (prosty) -->
        <?php if ($total_rated > 0): ?>
        <div class="card" style="padding: 20px; margin: 20px 0;">
            <h3>📈 Proporcja ocen</h3>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <div style="flex: <?php echo $positive_ratings; ?>; background: #46b450; height: 40px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border-radius: 4px;">
                    <?php if ($positive_ratings > 0): ?>
                        👍 <?php echo number_format(($positive_ratings / $total_rated) * 100, 1); ?>%
                    <?php endif; ?>
                </div>
                <div style="flex: <?php echo $negative_ratings; ?>; background: #dc3232; height: 40px; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; border-radius: 4px;">
                    <?php if ($negative_ratings > 0): ?>
                        👎 <?php echo number_format(($negative_ratings / $total_rated) * 100, 1); ?>%
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Najpopularniejsze pytania -->
        <?php if (!empty($popular_questions)): ?>
        <div class="card" style="padding: 20px; margin: 20px 0;">
            <h3>🔥 Najpopularniejsze pytania</h3>
            <ol style="line-height: 2;">
                <?php foreach ($popular_questions as $question): ?>
                <li>
                    <strong><?php echo esc_html($question['user_message']); ?></strong>
                    <span style="color: #666; margin-left: 10px;">(<?php echo $question['count']; ?>x)</span>
                </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <?php endif; ?>
        
        <!-- Ostatnie rozmowy -->
        <div class="card" style="padding: 20px; margin: 20px 0;">
            <h3>💬 Ostatnie rozmowy</h3>
            <?php if ($recent): ?>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th>Ocena</th>
                        <th>Data</th>
                        <th>Pytanie użytkownika</th>
                        <th>Odpowiedź bota</th>
                        <th>Czas</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $row): ?>
                    <tr>
                        <td style="text-align: center; font-size: 20px;">
                            <?php 
                            if ($row->rating == 1) echo '👍';
                            elseif ($row->rating == -1) echo '<span style="opacity: 0.5;">👎</span>';
                            else echo '<span style="opacity: 0.3;">⚪</span>';
                            ?>
                        </td>
                        <td><small><?php echo date('Y-m-d H:i', strtotime($row->timestamp)); ?></small></td>
                        <td><?php echo esc_html(mb_substr($row->user_message, 0, 60)); ?><?php echo mb_strlen($row->user_message) > 60 ? '...' : ''; ?></td>
                        <td><?php echo esc_html(mb_substr($row->bot_response, 0, 80)); ?><?php echo mb_strlen($row->bot_response) > 80 ? '...' : ''; ?></td>
                        <td><small><?php echo $row->response_time ? number_format($row->response_time, 2) . 's' : '-'; ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p style="margin-top: 15px;">
                <a href="?page=pewik-chatbot-ratings" class="button button-primary">Zobacz wszystkie rozmowy →</a>
            </p>
            <?php else: ?>
            <p>Brak rozmów. Chatbot jeszcze nie został użyty.</p>
            <?php endif; ?>
        </div>
        
        <!-- Szybkie akcje -->
        <div class="card" style="padding: 20px; margin: 20px 0; background: #f8f9fa;">
            <h3>🚀 Szybkie akcje</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px;">
                <a href="?page=pewik-chatbot-ratings&filter_rating=negative" class="button button-secondary">
                    👎 Przejrzyj negatywne oceny (<?php echo $negative_ratings; ?>)
                </a>
                <a href="?page=pewik-chatbot-ratings&filter_rating=positive" class="button button-secondary">
                    👍 Przejrzyj pozytywne oceny (<?php echo $positive_ratings; ?>)
                </a>
                <a href="?page=pewik-chatbot-export" class="button button-primary">
                    📥 Eksportuj do AI Eval
                </a>
            </div>
        </div>
    </div>
    
    <style>
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .stat-icon {
            font-size: 32px;
            margin-bottom: 20px;
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            margin: 25px 10px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .stat-sublabel {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }
        
        .total-card .stat-number { color: #0073aa; }
        .today-card .stat-number { color: #00a32a; }
        .week-card .stat-number { color: #826eb4; }
        .month-card .stat-number { color: #c9356e; }
        .time-card .stat-number { color: #f56e28; }
        .positive-card .stat-number { color: #46b450; }
        .negative-card .stat-number { color: #dc3232; }
        .satisfaction-card .stat-number { color: #ffb900; }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .card h3 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
    </style>
    <?php
}

/**
 * AJAX: Pobierz szczegóły rozmowy
 */
add_action('wp_ajax_get_conversation_details', 'pewik_chatbot_get_conversation_details');
function pewik_chatbot_get_conversation_details() {
    check_ajax_referer('get_conversation_details', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Brak uprawnień');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'chatbot_conversations';
    $conversation_id = intval($_POST['conversation_id']);
    
    $conv = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $conversation_id
    ));
    
    if (!$conv) {
        wp_send_json_error('Nie znaleziono rozmowy');
    }
    
    $rating_display = 'Brak oceny';
    if ($conv->rating == 1) {
        $rating_display = '👍 Pozytywna';
    } elseif ($conv->rating == -1) {
        $rating_display = '👎 Negatywna';
    }
    
    wp_send_json_success(array(
        'timestamp' => date('Y-m-d H:i:s', strtotime($conv->timestamp)),
        'user_message' => esc_html($conv->user_message),
        'bot_response' => esc_html($conv->bot_response),
        'rating_display' => $rating_display,
        'feedback' => $conv->feedback ? esc_html($conv->feedback) : null,
        'response_time' => $conv->response_time ? number_format($conv->response_time, 2) . 's' : 'Brak danych',
        'session_id' => esc_html($conv->session_id),
        'metadata' => $conv->metadata
    ));
}

/**
 * Dodaj link do ustawień na liście pluginów
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'pewik_chatbot_action_links');
function pewik_chatbot_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=pewik-chatbot') . '">Ustawienia</a>';
    array_unshift($links, $settings_link);
    return $links;
}