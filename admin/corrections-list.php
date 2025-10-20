<?php
/**
 * Panel korekt odpowiedzi chatbota - Widok listy
 */

if (!defined('ABSPATH')) exit;
?>

<div class="wrap">
    <h1>Korekty odpowiedzi chatbota</h1>
    
    <!-- Statystyki -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin: 20px 0;">
        <div class="rating-stat-card total">
            <div class="stat-number"><?php echo number_format($stats['total']); ?></div>
            <div class="stat-label">Wszystkie korekty</div>
        </div>
        
        <div class="rating-stat-card neutral">
            <div class="stat-number"><?php echo number_format($stats['pending']); ?></div>
            <div class="stat-label">Oczekujące</div>
        </div>
        
        <div class="rating-stat-card positive">
            <div class="stat-number"><?php echo number_format($stats['approved']); ?></div>
            <div class="stat-label">Zatwierdzone</div>
        </div>
        
        <div class="rating-stat-card negative">
            <div class="stat-number"><?php echo number_format($stats['exported']); ?></div>
            <div class="stat-label">Wyeksportowane</div>
        </div>
    </div>
    
    <!-- Szybkie akcje i filtry -->
    <div class="filters-panel">
    <form method="get" action="">
        <input type="hidden" name="page" value="<?php echo esc_attr($_GET['page']); ?>">
        
        <div class="filters-grid">
            <div class="filter-group">
                <label for="filter_status">Status</label>
                <select name="filter_status" id="filter_status" onchange="this.form.submit()">
                    <option value="all" <?php selected($filter_status, 'all'); ?>>Wszystkie statusy</option>
                    <option value="pending" <?php selected($filter_status, 'pending'); ?>>Oczekujące</option>
                    <option value="in_progress" <?php selected($filter_status, 'in_progress'); ?>>W trakcie</option>
                    <option value="approved" <?php selected($filter_status, 'approved'); ?>>Zatwierdzone</option>
                    <option value="rejected" <?php selected($filter_status, 'rejected'); ?>>Odrzucone</option>
                    <option value="exported" <?php selected($filter_status, 'exported'); ?>>Wyeksportowane</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label for="filter_category">Kategoria</label>
                <select name="filter_category" id="filter_category" onchange="this.form.submit()">
                    <option value="">Wszystkie kategorie</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo esc_attr($cat->name); ?>" <?php selected($filter_category, $cat->name); ?>>
                            <?php echo esc_html($cat->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-group filter-search">
                <label for="search">Wyszukiwanie</label>
                <input 
                    type="text" 
                    name="search" 
                    id="search" 
                    value="<?php echo esc_attr($search_query); ?>" 
                    placeholder="Szukaj w pytaniach i odpowiedziach..."
                >
            </div>
            
            <div class="filter-group filter-actions">
                <label>&nbsp;</label>
                <div class="action-buttons">
                    <button type="submit" class="button button-primary">Szukaj</button>
                    <?php if ($filter_status !== 'all' || !empty($filter_category) || !empty($search_query)): ?>
                        <a href="?page=<?php echo esc_attr($_GET['page']); ?>" class="button">Wyczyść</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
    
    <!-- Dodatkowe akcje -->
    <div class="quick-actions-section">
        <form method="post" style="display: inline-block; margin-right: 10px;">
            <?php wp_nonce_field('pewik_corrections_action', 'pewik_corrections_nonce'); ?>
            <input type="hidden" name="action" value="auto_create">
            <button type="submit" class="button button-primary">
                📝 Utwórz korekty z negatywnych ocen
            </button>
        </form>
        
        <a href="?page=pewik-chatbot-export" class="button button-secondary">
            📤 Eksportuj zatwierdzone do Markdown
        </a>
    </div>
</div>
    
    <!-- Tabela korekt -->
    <?php if (empty($corrections)): ?>
        <div class="notice notice-info">
            <p>Brak korekt spełniających wybrane kryteria.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 100px;">Status</th>
                    <th style="width: 120px;">Kategoria</th>
                    <th style="width: 30%;">Pytanie użytkownika</th>
                    <th style="width: 30%;">Poprawiona odpowiedź</th>
                    <th style="width: 140px;">Data</th>
                    <th style="width: 150px;">Akcje</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($corrections as $correction): 
                    // Znajdź kategorię dla koloru
                    $cat_obj = array_filter($categories, function($c) use ($correction) {
                        return $c->name === $correction->category;
                    });
                    $cat_obj = reset($cat_obj);
                    $cat_color = $cat_obj ? $cat_obj->color : '#666';
                    
                    // Status display
                    $status_labels = array(
                        'pending' => 'Oczekujące',
                        'in_progress' => 'W trakcie',
                        'approved' => 'Zatwierdzone',
                        'rejected' => 'Odrzucone',
                        'exported' => 'Wyeksportowane'
                    );
                    $status_label = $status_labels[$correction->status] ?? $correction->status;
                ?>
                <tr>
                    <td><strong>#<?php echo $correction->id; ?></strong></td>
                    <td>
                        <span class="status-badge status-<?php echo $correction->status; ?>">
                            <?php echo $status_label; ?>
                        </span>
                    </td>
                    <td>
                        <span class="category-badge" style="background: <?php echo esc_attr($cat_color); ?>;">
                            <?php echo esc_html($correction->category); ?>
                        </span>
                    </td>
                    <td>
                        <div class="conversation-text">
                            <?php echo esc_html(mb_substr($correction->original_question, 0, 150)); ?>
                            <?php if (mb_strlen($correction->original_question) > 150): ?>
                                <span class="show-more" onclick="showCorrectionDetails(<?php echo $correction->id; ?>)">... więcej</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <div class="conversation-text">
                            <?php 
                            $answer = !empty($correction->corrected_answer) ? $correction->corrected_answer : $correction->original_answer;
                            echo esc_html(mb_substr($answer, 0, 150)); 
                            ?>
                            <?php if (mb_strlen($answer) > 150): ?>
                                <span class="show-more" onclick="showCorrectionDetails(<?php echo $correction->id; ?>)">... więcej</span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td>
                        <small><?php echo $correction->correction_timestamp ? date('Y-m-d H:i', strtotime($correction->correction_timestamp)) : '-'; ?></small>
                    </td>
                    <td>
                        <button 
                            type="button" 
                            class="button button-small" 
                            onclick="editCorrection(<?php echo $correction->id; ?>)"
                            title="Edytuj korektę"
                        >
                            Edytuj
                        </button>
                        
                        <?php if ($correction->status !== 'approved'): ?>
                        <form method="post" style="display: inline;">
                            <?php wp_nonce_field('pewik_corrections_action', 'pewik_corrections_nonce'); ?>
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="correction_id" value="<?php echo $correction->id; ?>">
                            <button type="submit" class="button button-small button-primary" style="margin-left: 5px;">
                                Zatwierdź
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <!-- Paginacja -->
        <?php if ($args['limit'] < $stats['total']): ?>
        <div class="tablenav bottom" style="margin-top: 20px;">
            <div class="tablenav-pages">
                <?php
                $total_pages = ceil($stats['total'] / $args['limit']);
                $current_page = ($args['offset'] / $args['limit']) + 1;
                
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
    <?php endif; ?>
</div>

<!-- Modal szczegółów korekty -->
<div id="correction-details-modal" style="display: none;">
    <div class="modal-overlay" onclick="closeCorrectionModal()"></div>
    <div class="modal-content">
        <span class="modal-close" onclick="closeCorrectionModal()">&times;</span>
        <h2>Szczegóły korekty</h2>
        <div id="correction-modal-body"></div>
    </div>
</div>

<!-- Modal edycji -->
<div id="edit-correction-modal" class="correction-modal" style="display: none;">
    <div class="modal-overlay" onclick="closeEditModal()"></div>
    <div class="modal-content">
        <span class="modal-close" onclick="closeEditModal()">&times;</span>
        <h2>Edycja korekty</h2>
        <div id="edit-modal-body">
            <form id="edit-correction-form">
                <input type="hidden" id="edit-correction-id" name="correction_id">
                
                <div class="detail-row">
                    <div class="detail-label">Poprawiona odpowiedź:</div>
                    <textarea 
                        id="edit-corrected-answer" 
                        name="corrected_answer" 
                        rows="8" 
                        style="width: 100%;"
                        required
                    ></textarea>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Kategoria:</div>
                    <select id="edit-category" name="category" style="width: 100%;">
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo esc_attr($cat->name); ?>">
                                <?php echo esc_html($cat->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                    <button type="submit" class="button button-primary">Zapisz zmiany</button>
                    <button type="button" class="button" onclick="closeEditModal()">Anuluj</button>
                </div>
            </form>
        </div>
    </div>
</div>

                    <label for="edit-notes">Notatki (opcjonalnie)</label>
                    <textarea 
                        id="edit-notes" 
                        name="correction_notes" 
                        rows="3"
                    ></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="button button-primary">Zapisz zmiany</button>
                    <button type="button" class="button" onclick="closeEditModal()">Anuluj</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* ----- GŁÓWNY PANEL I KARTY STATYSTYK ----- */
.wrap {
    max-width: 1600px;
}

.rating-stat-card {
    background: white;
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    border-left: 4px solid #ddd;
}
.rating-stat-card.positive { border-left-color: #46b450; }
.rating-stat-card.negative { border-left-color: #dc3232; }
.rating-stat-card.neutral { border-left-color: #ffb900; }
.rating-stat-card.total { border-left-color: #0073aa; }

.stat-number {
    font-size: 32px;
    font-weight: bold;
    margin-bottom: 10px;
}
.rating-stat-card.positive .stat-number { color: #46b450; }
.rating-stat-card.negative .stat-number { color: #dc3232; }
.rating-stat-card.neutral .stat-number { color: #ffb900; }
.rating-stat-card.total .stat-number { color: #0073aa; }

.stat-label {
    font-size: 14px;
    color: #555;
}

/* ----- PANEL FILTROWANIA (FINALNA POPRAWKA) ----- */
.filters-panel {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    padding: 20px;
    margin-bottom: 20px;
}

.filters-grid {
    display: grid;
    /* Używamy elastycznych jednostek `fr`, które same zarządzają przestrzenią */
    grid-template-columns: minmax(200px, 1fr) minmax(200px, 1fr) 2fr auto;
    gap: 15px;
    align-items: flex-end;
}

/* * NAJWAŻNIEJSZA POPRAWKA:
 * Resetujemy styl `.filter-group`, aby zignorować `width: 24%` i `float` z motywu.
 * Grid sam zarządza szerokością i pozycją swoich dzieci.
*/
.filters-grid .filter-group {
    width: auto; /* Resetuje `width: 24%` */
    float: none; /* Resetuje `float: left` */
    margin: 0; /* Resetuje marginesy */
    padding: 0; /* Resetuje padding */
    border: none; /* Resetuje obramowanie */
    box-shadow: none; /* Resetuje cień */
    background: transparent; /* Resetuje tło */
    display: flex;
    flex-direction: column;
}

.filter-group label {
    font-size: 12px;
    font-weight: 600;
    color: #555;
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
}

.filter-group input[type="text"],
.filter-group select {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    height: 38px;
    border: 1px solid #ccd0d4;
    border-radius: 3px;
    padding: 0 12px;
    font-size: 14px;
    width: 100%;
    box-sizing: border-box;
    background-color: #fff;
}

.filter-group select {
    background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2220%22%20height%3D%2220%22%20viewBox%3D%220%200%2020%2020%22%20fill%3D%22%23555555%22%3E%3Cpath%20d%3D%22M5.293%207.293a1%201%200%20011.414%200L10%2010.586l3.293-3.293a1%201%200%20111.414%201.414l-4%204a1%201%200%2001-1.414%200l-4-4a1%201%200%20010-1.414z%22%20%2F%3E%3C%2Fsvg%3E');
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1.25em;
    padding-right: 2.5rem !important;
    cursor: pointer;
}

.filter-group select:focus,
.filter-group input[type="text"]:focus {
    border-color: #0073aa;
    outline: none;
    box-shadow: 0 0 0 1px #0073aa;
}

.action-buttons {
    display: flex;
    gap: 8px;
    white-space: nowrap;
}

.action-buttons .button {
    height: 38px;
    padding: 0 20px;
}

/* ----- SEKCJA SZYBKICH AKCJI ----- */
.quick-actions-section {
    padding: 20px 0;
    border-top: 1px solid #e0e0e0;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* ----- TABELA I ELEMENTY LISTY ----- */
.conversation-text { line-height: 1.6; }
.show-more { color: #0073aa; cursor: pointer; text-decoration: underline; }

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
.status-pending { background: #fff8e1; color: #6d4c02; border: 1px solid #ffecb3; }
.status-in_progress { background: #e3f2fd; color: #0d47a1; border: 1px solid #bbdefb; }
.status-approved { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
.status-rejected { background: #ffebee; color: #b71c1c; border: 1px solid #ffcdd2; }
.status-exported { background: #f3e5f5; color: #4a148c; border: 1px solid #e1bee7; }

.category-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    color: white;
}

/* ----- MODALE ----- */
.modal-overlay {
    position: fixed;
    top: 0; left: 0; width: 100%; height: 100%;
    background: rgba(0,0,0,0.7);
    z-index: 100000;
}
.modal-content {
    position: fixed;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    width: 90%; max-width: 800px;
    max-height: 80vh;
    overflow-y: auto;
    z-index: 100001;
}
.modal-close {
    position: absolute;
    top: 15px; right: 20px;
    font-size: 28px;
    cursor: pointer;
    color: #999;
}
.detail-row { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
.detail-label { font-weight: bold; color: #666; margin-bottom: 5px; }
.detail-value { background: #f5f5f5; padding: 10px; border-radius: 4px; white-space: pre-wrap; }
.detail-row:last-child { border-bottom: none; }

/* ----- MEDIA QUERIES (RESPONSYWNOŚĆ) ----- */
@media (max-width: 960px) {
    .filters-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .action-buttons {
        width: 100%;
    }
    .action-buttons .button {
        flex-grow: 1;
        text-align: center;
    }
}
</style>

<script>
function editCorrection(correctionId) {
    // Pobierz dane korekty przez AJAX
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'get_correction_data',
            correction_id: correctionId,
            nonce: '<?php echo wp_create_nonce('get_correction_data'); ?>'
        },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                jQuery('#edit-correction-id').val(data.id);
                jQuery('#edit-corrected-answer').val(data.corrected_answer || data.original_answer);
                jQuery('#edit-category').val(data.category);
                jQuery('#edit-correction-modal').fadeIn(200);
            } else {
                alert('Błąd: ' + (response.data || 'Nie udało się pobrać danych'));
            }
        },
        error: function(xhr, status, error) {
            alert('Błąd połączenia: ' + error);
        }
    });
}

function closeEditModal() {
    jQuery('#edit-correction-modal').fadeOut(200);
}

function showCorrectionDetails(correctionId) {
    // Pobierz pełne szczegóły korekty
    jQuery.ajax({
        url: ajaxurl,
        method: 'POST',
        data: {
            action: 'get_correction_full_details',
            correction_id: correctionId,
            nonce: '<?php echo wp_create_nonce('get_correction_details'); ?>'
        },
        success: function(response) {
            if (response.success) {
                const data = response.data;
                let html = '';
                
                html += '<div class="detail-row">';
                html += '<div class="detail-label">ID korekty:</div>';
                html += '<div class="detail-value">#' + data.id + '</div>';
                html += '</div>';
                
                html += '<div class="detail-row">';
                html += '<div class="detail-label">Pytanie użytkownika:</div>';
                html += '<div class="detail-value">' + data.original_question + '</div>';
                html += '</div>';
                
                html += '<div class="detail-row">';
                html += '<div class="detail-label">Oryginalna odpowiedź:</div>';
                html += '<div class="detail-value">' + data.original_answer + '</div>';
                html += '</div>';
                
                if (data.corrected_answer) {
                    html += '<div class="detail-row">';
                    html += '<div class="detail-label">Poprawiona odpowiedź:</div>';
                    html += '<div class="detail-value">' + data.corrected_answer + '</div>';
                    html += '</div>';
                }
                
                html += '<div class="detail-row">';
                html += '<div class="detail-label">Status:</div>';
                html += '<div class="detail-value">' + data.status_label + '</div>';
                html += '</div>';
                
                html += '<div class="detail-row">';
                html += '<div class="detail-label">Kategoria:</div>';
                html += '<div class="detail-value">' + data.category + '</div>';
                html += '</div>';
                
                jQuery('#correction-modal-body').html(html);
                jQuery('#correction-details-modal').fadeIn(200);
            }
        }
    });
}

function closeCorrectionModal() {
    jQuery('#correction-details-modal').fadeOut(200);
}

jQuery(document).ready(function($) {
    // Obsługa formularza edycji
    $('#edit-correction-form').on('submit', function(e) {
        e.preventDefault();
        
        const formData = {
            action: 'pewik_save_correction_ajax',
            correction_id: $('#edit-correction-id').val(),
            corrected_answer: $('#edit-corrected-answer').val(),
            category: $('#edit-category').val(),
            nonce: '<?php echo wp_create_nonce('save_correction'); ?>'
        };
        
        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    alert('Korekta została zapisana');
                    location.reload();
                } else {
                    alert('Błąd zapisu: ' + (response.data || 'Nieznany błąd'));
                }
            },
            error: function(xhr, status, error) {
                alert('Błąd połączenia: ' + error);
            }
        });
    });
    
    // Zamknij modal przy ESC
    $(document).keyup(function(e) {
        if (e.key === "Escape") {
            closeEditModal();
            closeCorrectionModal();
        }
    });
});
</script>
<?php

/**
 * AJAX: Pobierz dane korekty
 */
add_action('wp_ajax_get_correction_data', 'pewik_get_correction_data');
function pewik_get_correction_data() {
    check_ajax_referer('get_correction_data', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Brak uprawnień');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'chatbot_corrections';
    $correction_id = intval($_POST['correction_id']);
    
    $correction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $correction_id
    ));
    
    if ($correction) {
        wp_send_json_success($correction);
    } else {
        wp_send_json_error('Nie znaleziono korekty');
    }
}

/**
 * AJAX: Pobierz pełne szczegóły korekty
 */
add_action('wp_ajax_get_correction_full_details', 'pewik_get_correction_full_details');
function pewik_get_correction_full_details() {
    check_ajax_referer('get_correction_details', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Brak uprawnień');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'chatbot_corrections';
    $correction_id = intval($_POST['correction_id']);
    
    $correction = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table WHERE id = %d",
        $correction_id
    ));
    
    if ($correction) {
        $status_labels = array(
            'pending' => 'Oczekujące',
            'in_progress' => 'W trakcie',
            'approved' => 'Zatwierdzone',
            'rejected' => 'Odrzucone',
            'exported' => 'Wyeksportowane'
        );
        
        $data = array(
            'id' => $correction->id,
            'original_question' => esc_html($correction->original_question),
            'original_answer' => esc_html($correction->original_answer),
            'corrected_answer' => $correction->corrected_answer ? esc_html($correction->corrected_answer) : null,
            'category' => esc_html($correction->category),
            'status_label' => $status_labels[$correction->status] ?? $correction->status
        );
        
        wp_send_json_success($data);
    } else {
        wp_send_json_error('Nie znaleziono korekty');
    }
}

/**
 * AJAX: Zapisz korektę
 */
add_action('wp_ajax_pewik_save_correction_ajax', 'pewik_save_correction_ajax');
function pewik_save_correction_ajax() {
    check_ajax_referer('save_correction', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Brak uprawnień');
    }
    
    global $wpdb;
    $table = $wpdb->prefix . 'chatbot_corrections';
    
    $correction_id = intval($_POST['correction_id']);
    $corrected_answer = sanitize_textarea_field($_POST['corrected_answer']);
    $category = sanitize_text_field($_POST['category']);
    
    $result = $wpdb->update(
        $table,
        array(
            'corrected_answer' => $corrected_answer,
            'category' => $category,
            'status' => 'in_progress',
            'corrected_by_user_id' => get_current_user_id(),
            'correction_timestamp' => current_time('mysql')
        ),
        array('id' => $correction_id),
        array('%s', '%s', '%s', '%d', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Korekta została zapisana');
    } else {
        wp_send_json_error('Nie udało się zapisać korekty');
    }
}
?>