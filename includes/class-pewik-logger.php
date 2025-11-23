<?php
if (!defined('ABSPATH')) exit;

class PEWIK_Logger {
    private static $log_file;
    private static $initialized = false;
    
    public static function init() {
        if (self::$initialized) return;
        
        self::$log_file = WP_CONTENT_DIR . '/pewik-chatbot-errors.log';
        self::$initialized = true;
        
        // Upewnij się że plik istnieje
        if (!file_exists(self::$log_file)) {
            touch(self::$log_file);
            chmod(self::$log_file, 0644);
        }
    }
    
    /**
     * Loguj błąd krytyczny
     */
    public static function error($message, $context = array()) {
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = sprintf(
            "[%s] ERROR: %s\n",
            $timestamp,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= "Context: " . json_encode($context, JSON_PRETTY_PRINT) . "\n";
        }
        
        $log_entry .= str_repeat('-', 80) . "\n";
        
        // Zapisz do pliku
        error_log($log_entry, 3, self::$log_file);
        
        // Wysyłka email dla krytycznych błędów
        if (isset($context['critical']) && $context['critical'] === true) {
            self::send_alert_email($message, $context);
        }
        
        // Również do standardowego WordPress error log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[PEWIK CHATBOT] ' . $message);
        }
    }
    
    /**
     * Loguj informacje (tylko w trybie debug)
     */
    public static function info($message, $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        self::init();
        
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = sprintf(
            "[%s] INFO: %s\n",
            $timestamp,
            $message
        );
        
        if (!empty($context)) {
            $log_entry .= "Context: " . json_encode($context) . "\n";
        }
        
        error_log($log_entry, 3, self::$log_file);
    }
    
    /**
     * Wyślij email alert dla krytycznych błędów
     */
    private static function send_alert_email($message, $context) {
        $admin_email = get_option('admin_email');
        
        $subject = '[PEWIK Chatbot] Błąd krytyczny';
        
        $body = "Wystąpił błąd krytyczny w chatbocie PEWIK:\n\n";
        $body .= "Wiadomość: $message\n\n";
        $body .= "Szczegóły:\n" . print_r($context, true) . "\n\n";
        $body .= "Data: " . date('Y-m-d H:i:s') . "\n";
        $body .= "URL: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'N/A') . "\n";
        
        wp_mail($admin_email, $subject, $body);
    }
    
    /**
     * Pobierz ostatnie logi
     */
    public static function get_recent_logs($lines = 50) {
        self::init();
        
        if (!file_exists(self::$log_file)) {
            return 'Brak logów.';
        }
        
        $file = new SplFileObject(self::$log_file);
        $file->seek(PHP_INT_MAX);
        $total_lines = $file->key();
        
        $start_line = max(0, $total_lines - $lines);
        
        $logs = array();
        $file->seek($start_line);
        
        while (!$file->eof()) {
            $logs[] = $file->current();
            $file->next();
        }
        
        return implode('', $logs);
    }
    
    /**
     * Wyczyść logi
     */
    public static function clear_logs() {
        self::init();
        
        if (file_exists(self::$log_file)) {
            file_put_contents(self::$log_file, '');
        }
    }
}