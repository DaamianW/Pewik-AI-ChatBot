<?php
/**
 * Eksporter korekt do formatu Markdown
 * Generuje pliki .md gotowe do uploadu do Oracle Knowledge Base
 */

if (!defined('ABSPATH')) exit;

class PEWIK_Markdown_Exporter {
    
    private $corrections_manager;
    
    public function __construct() {
        $this->corrections_manager = new PEWIK_Corrections_Manager();
    }
    
    /**
     * Eksportuj zatwierdzone korekty do Markdown
     * 
     * @param array $correction_ids Lista ID korekt do eksportu (jeśli puste, wszystkie zatwierdzone)
     * @param array $options Opcje eksportu
     * @return string Zawartość pliku Markdown
     */
    public function export_to_markdown($correction_ids = array(), $options = array()) {
        global $wpdb;
        
        $defaults = array(
            'include_metadata' => true,
            'include_notes' => true,
            'group_by_category' => true,
            'add_toc' => true,
            'format_version' => '1.0'
        );
        
        $options = wp_parse_args($options, $defaults);
        
        // Pobierz korekty
        if (empty($correction_ids)) {
            // Wszystkie zatwierdzone
            $corrections = $this->corrections_manager->get_corrections(array(
                'status' => 'approved',
                'limit' => 1000
            ));
        } else {
            // Konkretne ID
            $table = $wpdb->prefix . 'chatbot_corrections';
            $ids_placeholder = implode(',', array_fill(0, count($correction_ids), '%d'));
            $corrections = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$table} WHERE id IN ({$ids_placeholder}) ORDER BY category, id",
                ...$correction_ids
            ));
        }
        
        if (empty($corrections)) {
            return "# Brak korekt do eksportu\n\nNie znaleziono zatwierdzonych korekt.";
        }
        
        // Generuj Markdown
        $md = $this->generate_markdown_header($corrections, $options);
        
        if ($options['group_by_category']) {
            $md .= $this->generate_grouped_content($corrections, $options);
        } else {
            $md .= $this->generate_linear_content($corrections, $options);
        }
        
        $md .= $this->generate_markdown_footer();
        
        return $md;
    }
    
    /**
     * Generuj nagłówek Markdown z metadanymi
     */
    private function generate_markdown_header($corrections, $options) {
        $md = "---\n";
        
        if ($options['include_metadata']) {
            $md .= "title: Korekty odpowiedzi chatbota PEWIK\n";
            $md .= "source: pewik_wordpress_chatbot\n";
            $md .= "generated: " . current_time('Y-m-d H:i:s') . "\n";
            $md .= "total_corrections: " . count($corrections) . "\n";
            $md .= "format_version: " . $options['format_version'] . "\n";
            $md .= "export_type: approved_corrections\n";
            
            // Kategorie
            $categories = array_unique(array_map(function($c) { return $c->category; }, $corrections));
            $md .= "categories: " . implode(', ', $categories) . "\n";
        }
        
        $md .= "---\n\n";
        $md .= "# 📚 Baza wiedzy chatbota PEWIK - Korekty\n\n";
        $md .= "> **Wygenerowano:** " . date_i18n('d.m.Y H:i') . "\n";
        $md .= "> **Liczba korekt:** " . count($corrections) . "\n\n";
        
        if ($options['add_toc']) {
            $md .= $this->generate_table_of_contents($corrections);
        }
        
        return $md;
    }
    
    /**
     * Generuj spis treści
     */
    private function generate_table_of_contents($corrections) {
        $toc = "## 📋 Spis treści\n\n";
        
        // Grupuj po kategoriach
        $by_category = array();
        foreach ($corrections as $c) {
            $cat = $c->category ?: 'Ogólne';
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = 0;
            }
            $by_category[$cat]++;
        }
        
        foreach ($by_category as $cat => $count) {
            $slug = sanitize_title($cat);
            $toc .= "- [{$cat}](#{$slug}) ({$count})\n";
        }
        
        $toc .= "\n---\n\n";
        
        return $toc;
    }
    
    /**
     * Generuj treść pogrupowaną po kategoriach
     */
    private function generate_grouped_content($corrections, $options) {
        $md = "";
        
        // Grupuj korekty po kategoriach
        $by_category = array();
        foreach ($corrections as $c) {
            $cat = $c->category ?: 'Ogólne';
            if (!isset($by_category[$cat])) {
                $by_category[$cat] = array();
            }
            $by_category[$cat][] = $c;
        }
        
        // Pobierz ikony kategorii
        $categories_meta = $this->get_categories_metadata();
        
        // Generuj sekcje
        foreach ($by_category as $category => $items) {
            $icon = $categories_meta[$category]['icon'] ?? '📁';
            $slug = sanitize_title($category);
            
            $md .= "## {$icon} {$category} {#" . $slug . "}\n\n";
            
            if (isset($categories_meta[$category]['description'])) {
                $md .= "> " . $categories_meta[$category]['description'] . "\n\n";
            }
            
            foreach ($items as $correction) {
                $md .= $this->format_correction_entry($correction, $options);
            }
            
            $md .= "\n---\n\n";
        }
        
        return $md;
    }
    
    /**
     * Generuj treść liniową (bez grupowania)
     */
    private function generate_linear_content($corrections, $options) {
        $md = "";
        
        foreach ($corrections as $correction) {
            $md .= $this->format_correction_entry($correction, $options);
        }
        
        return $md;
    }
    
    /**
     * Formatuj pojedynczy wpis korekty
     */
    private function format_correction_entry($correction, $options) {
        $md = "### " . esc_html($correction->original_question) . "\n\n";
        
        // Odpowiedź
        $md .= $this->format_answer($correction->corrected_answer ?: $correction->original_answer);
        $md .= "\n\n";
        
        // Notatki (jeśli włączone)
        if ($options['include_notes'] && !empty($correction->correction_notes)) {
            $md .= "> **💬 Notatka:** " . esc_html($correction->correction_notes) . "\n\n";
        }
        
        // Metadata
        if ($options['include_metadata']) {
            $md .= "<details>\n";
            $md .= "<summary>📊 Metadata</summary>\n\n";
            $md .= "- **ID korekty:** " . $correction->id . "\n";
            $md .= "- **Status:** " . $this->get_status_label($correction->status) . "\n";
            $md .= "- **Priorytet:** " . $this->get_priority_label($correction->priority) . "\n";
            
            if ($correction->corrected_by_user_id) {
                $user = get_userdata($correction->corrected_by_user_id);
                $md .= "- **Poprawione przez:** " . ($user ? $user->display_name : 'Nieznany') . "\n";
            }
            
            if ($correction->correction_timestamp) {
                $md .= "- **Data poprawki:** " . date_i18n('d.m.Y H:i', strtotime($correction->correction_timestamp)) . "\n";
            }
            
            $md .= "\n</details>\n\n";
        }
        
        return $md;
    }
    
    /**
     * Formatuj odpowiedź (markdown, listy, linki)
     */
    private function format_answer($answer) {
        $answer = esc_html($answer);
        
        // Wykryj i formatuj listy numerowane
        $answer = preg_replace('/^(\d+)\.\s+(.+)$/m', '$1. $2', $answer);
        
        // Wykryj i formatuj listy punktowane
        $answer = preg_replace('/^[-*]\s+(.+)$/m', '- $1', $answer);
        
        // Formatuj pogrubienia (**tekst**)
        $answer = preg_replace('/\*\*(.+?)\*\*/s', '**$1**', $answer);
        
        // Wykryj numery telefonów i zrób linki
        $answer = preg_replace('/\b(994|58\s?\d{2}\s?\d{2}\s?\d{3})\b/', '[$1](tel:$1)', $answer);
        
        // Wykryj adresy email
        $answer = preg_replace('/\b([a-zA-Z0-9._%+-]+@pewik\.gdynia\.pl)\b/', '[$1](mailto:$1)', $answer);
        
        return $answer;
    }
    
    /**
     * Pobierz metadane kategorii
     */
    private function get_categories_metadata() {
        global $wpdb;
        $table = $wpdb->prefix . 'chatbot_categories';
        
        $categories = $wpdb->get_results(
            "SELECT name, icon, color, description FROM {$table}",
            ARRAY_A
        );
        
        $metadata = array();
        foreach ($categories as $cat) {
            $metadata[$cat['name']] = $cat;
        }
        
        return $metadata;
    }
    
    /**
     * Etykiety statusów
     */
    private function get_status_label($status) {
        $labels = array(
            'pending' => '⏳ Oczekuje',
            'in_progress' => '✏️ W trakcie',
            'approved' => '✅ Zatwierdzona',
            'rejected' => '❌ Odrzucona',
            'exported' => '📤 Wyeksportowana'
        );
        
        return $labels[$status] ?? $status;
    }
    
    /**
     * Etykiety priorytetów
     */
    private function get_priority_label($priority) {
        $labels = array(
            'low' => '🟢 Niski',
            'medium' => '🟡 Średni',
            'high' => '🟠 Wysoki',
            'critical' => '🔴 Krytyczny'
        );
        
        return $labels[$priority] ?? $priority;
    }
    
    /**
     * Generuj stopkę
     */
    private function generate_markdown_footer() {
        $md = "\n---\n\n";
        $md .= "## 📝 Informacje o dokumencie\n\n";
        $md .= "Ten dokument został wygenerowany automatycznie przez system WordPress PEWIK Chatbot.\n\n";
        $md .= "**Instrukcja użycia:**\n\n";
        $md .= "1. Pobierz ten plik `.md`\n";
        $md .= "2. Zaloguj się do OCI Console\n";
        $md .= "3. Przejdź do: **Generative AI Agents** → **Twój Agent** → **Knowledge Bases**\n";
        $md .= "4. Wybierz swój bucket w Object Storage\n";
        $md .= "5. Upload tego pliku do folderu `knowledge-base/corrections/`\n";
        $md .= "6. Poczekaj ~5 minut na reindeksację\n";
        $md .= "7. Agent automatycznie zacznie używać zaktualizowanej wiedzy\n\n";
        $md .= "**Wsparcie:** Jeśli masz pytania, skontaktuj się z administratorem systemu.\n\n";
        $md .= "---\n\n";
        $md .= "*Wygenerowano przez PEWIK AI Chatbot v1.0*\n";
        
        return $md;
    }
    
    /**
     * Zapisz Markdown do pliku i wymuś download
     * 
     * @param string $markdown Zawartość Markdown
     * @param string $filename Nazwa pliku (bez rozszerzenia)
     */
    public function download_markdown($markdown, $filename = null) {
        if (!$filename) {
            $filename = 'pewik-korekty-' . date('Y-m-d-His');
        }
        
        $filename = sanitize_file_name($filename) . '.md';
        
        header('Content-Type: text/markdown; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($markdown));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        echo $markdown;
        exit;
    }
}