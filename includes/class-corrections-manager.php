<?php
/**
 * Menedżer korekt odpowiedzi chatbota
 * Obsługuje CRUD operacje na korektach
 */

if (!defined('ABSPATH')) exit;

class PEWIK_Corrections_Manager {
    
    private $table_corrections;
    private $table_conversations;
    private $table_categories;
    
    public function __construct() {
        global $wpdb;
        $this->table_corrections = $wpdb->prefix . 'chatbot_corrections';
        $this->table_conversations = $wpdb->prefix . 'chatbot_conversations';
        $this->table_categories = $wpdb->prefix . 'chatbot_categories';
    }
    
    /**
     * Utwórz korektę z rozmowy z oceną negatywną
     * 
     * @param int $conversation_id ID rozmowy
     * @return int|false ID utworzonej korekty lub false
     */
    public function create_from_conversation($conversation_id) {
        global $wpdb;
        
        // Pobierz rozmowę
        $conversation = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_conversations} WHERE id = %d",
            $conversation_id
        ));
        
        if (!$conversation) {
            return false;
        }
        
        // Sprawdź czy korekta już istnieje
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_corrections} WHERE conversation_id = %d",
            $conversation_id
        ));
        
        if ($exists > 0) {
            // Zwróć ID istniejącej korekty
            return $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->table_corrections} WHERE conversation_id = %d",
                $conversation_id
            ));
        }
        
        // Auto-wykryj kategorię
        $category = $this->auto_detect_category($conversation->user_message);
        
        // Utwórz nową korektę
        $result = $wpdb->insert(
            $this->table_corrections,
            array(
                'conversation_id' => $conversation_id,
                'original_question' => $conversation->user_message,
                'original_answer' => $conversation->bot_response,
                'category' => $category,
                'priority' => $conversation->rating == -1 ? 'high' : 'medium',
                'status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
        
        return $result ? $wpdb->insert_id : false;
    }
    
    /**
     * Auto-wykrywanie kategorii na podstawie słów kluczowych
     * 
     * @param string $text Tekst do analizy
     * @return string Nazwa kategorii
     */
    public function auto_detect_category($text) {
        global $wpdb;
        
        $text_lower = mb_strtolower($text);
        
        // Pobierz wszystkie kategorie z keywords
        $categories = $wpdb->get_results(
            "SELECT name, keywords FROM {$this->table_categories} WHERE keywords IS NOT NULL ORDER BY sort_order"
        );
        
        $max_matches = 0;
        $best_category = 'Ogólne';
        
        foreach ($categories as $cat) {
            if (empty($cat->keywords)) continue;
            
            $keywords = array_map('trim', explode(',', mb_strtolower($cat->keywords)));
            $matches = 0;
            
            foreach ($keywords as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    $matches++;
                }
            }
            
            if ($matches > $max_matches) {
                $max_matches = $matches;
                $best_category = $cat->name;
            }
        }
        
        return $best_category;
    }
    
    /**
     * Pobierz korekty z filtrowaniem
     * 
     * @param array $args Argumenty filtrowania
     * @return array Lista korekt
     */
    public function get_corrections($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'status' => null,
            'category' => null,
            'priority' => null,
            'search' => null,
            'orderby' => 'id',
            'order' => 'DESC',
            'limit' => 50,
            'offset' => 0
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        
        if ($args['status']) {
            $where[] = $wpdb->prepare("status = %s", $args['status']);
        }
        
        if ($args['category']) {
            $where[] = $wpdb->prepare("category = %s", $args['category']);
        }
        
        if ($args['priority']) {
            $where[] = $wpdb->prepare("priority = %s", $args['priority']);
        }
        
        if ($args['search']) {
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $where[] = $wpdb->prepare(
                "(original_question LIKE %s OR corrected_answer LIKE %s OR correction_notes LIKE %s)",
                $search, $search, $search
            );
        }
        
        $where_sql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $order_sql = sprintf(
            "ORDER BY %s %s",
            esc_sql($args['orderby']),
            esc_sql($args['order'])
        );
        
        $limit_sql = sprintf(
            "LIMIT %d OFFSET %d",
            intval($args['limit']),
            intval($args['offset'])
        );
        
        $sql = "SELECT * FROM {$this->table_corrections} {$where_sql} {$order_sql} {$limit_sql}";
        
        return $wpdb->get_results($sql);
    }
    
    /**
     * Pobierz pojedynczą korektę
     * 
     * @param int $correction_id ID korekty
     * @return object|null Korekta lub null
     */
    public function get_correction($correction_id) {
        global $wpdb;
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table_corrections} WHERE id = %d",
            $correction_id
        ));
    }
    
    /**
     * Zapisz poprawioną odpowiedź
     * 
     * @param int $correction_id ID korekty
     * @param string $corrected_answer Poprawiona odpowiedź
     * @param string $notes Notatki
     * @param string $category Kategoria
     * @return bool Sukces operacji
     */
    public function save_correction($correction_id, $corrected_answer, $notes = '', $category = '') {
        global $wpdb;
        
        $data = array(
            'corrected_answer' => $corrected_answer,
            'correction_notes' => $notes,
            'corrected_by_user_id' => get_current_user_id(),
            'correction_timestamp' => current_time('mysql'),
            'status' => 'in_progress'
        );
        
        if (!empty($category)) {
            $data['category'] = $category;
        }
        
        $result = $wpdb->update(
            $this->table_corrections,
            $data,
            array('id' => $correction_id),
            array('%s', '%s', '%d', '%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Zatwierdź korektę
     * 
     * @param int $correction_id ID korekty
     * @return bool Sukces operacji
     */
    public function approve_correction($correction_id) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_corrections,
            array(
                'status' => 'approved',
                'approved_timestamp' => current_time('mysql')
            ),
            array('id' => $correction_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Odrzuć korektę
     * 
     * @param int $correction_id ID korekty
     * @param string $reason Powód odrzucenia
     * @return bool Sukces operacji
     */
    public function reject_correction($correction_id, $reason = '') {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_corrections,
            array(
                'status' => 'rejected',
                'correction_notes' => $reason
            ),
            array('id' => $correction_id),
            array('%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Oznacz korektę jako wyeksportowaną
     * 
     * @param int $correction_id ID korekty
     * @param string $filename Nazwa pliku eksportu
     * @return bool Sukces operacji
     */
    public function mark_as_exported($correction_id, $filename) {
        global $wpdb;
        
        $result = $wpdb->update(
            $this->table_corrections,
            array(
                'status' => 'exported',
                'exported_timestamp' => current_time('mysql'),
                'export_filename' => $filename
            ),
            array('id' => $correction_id),
            array('%s', '%s', '%s'),
            array('%d')
        );
        
        return $result !== false;
    }
    
    /**
     * Pobierz statystyki korekt
     * 
     * @return array Statystyki
     */
    public function get_stats() {
        global $wpdb;
        
        $stats = array(
            'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_corrections}"),
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_corrections} WHERE status = 'pending'"),
            'in_progress' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_corrections} WHERE status = 'in_progress'"),
            'approved' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_corrections} WHERE status = 'approved'"),
            'exported' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_corrections} WHERE status = 'exported'"),
            'rejected' => $wpdb->get_var("SELECT COUNT(*) FROM {$this->table_corrections} WHERE status = 'rejected'")
        );
        
        // Statystyki per kategoria
        $stats['by_category'] = $wpdb->get_results(
            "SELECT category, COUNT(*) as count FROM {$this->table_corrections} GROUP BY category ORDER BY count DESC",
            ARRAY_A
        );
        
        return $stats;
    }
    
    /**
     * Pobierz wszystkie kategorie
     * 
     * @return array Lista kategorii
     */
    public function get_categories() {
        global $wpdb;
        
        return $wpdb->get_results(
            "SELECT * FROM {$this->table_categories} ORDER BY sort_order, name"
        );
    }
    
    /**
     * Auto-tworzenie korekt dla wszystkich negatywnych ocen
     * 
     * @return int Liczba utworzonych korekt
     */
    public function auto_create_from_negative_ratings() {
        global $wpdb;
        
        // Pobierz wszystkie rozmowy z oceną -1, które nie mają jeszcze korekty
        $conversations = $wpdb->get_results("
            SELECT c.id 
            FROM {$this->table_conversations} c
            LEFT JOIN {$this->table_corrections} cor ON c.id = cor.conversation_id
            WHERE c.rating = -1 
            AND cor.id IS NULL
            ORDER BY c.timestamp DESC
        ");
        
        $created = 0;
        
        foreach ($conversations as $conv) {
            if ($this->create_from_conversation($conv->id)) {
                $created++;
            }
        }
        
        return $created;
    }
}