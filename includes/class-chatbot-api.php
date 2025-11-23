<?php
/**
 * Klasa do komunikacji z OCI Generative AI (Model Cohere Command R+)
 * Architektura: Hard Rules (PHP) + Local RAG + OCI Inference
 * Wersja: EXPERT (Z dodatkową wiedzą o Jakości Wody i Kanalizacji)
 */

if (!defined('ABSPATH')) exit;

class PEWIK_Chatbot_API {
    private $signer;
    private $inference_endpoint;
    private $compartment_id;
    private $model_id;
    
    // PROTOKÓŁ POWITANIA
    const MANDATORY_GREETING = "Cześć! W czym mogę pomóc? Jestem wirtualnym asystentem, korzystającym z informacji zawartych na stronie. Mogę pomóc Ci w odnalezieniu poszukiwanych informacji.";

    public function __construct() {
        $this->signer = new PEWIK_OCI_Request_Signer();
        
        // ⬇️ WAŻNE: UZUPEŁNIJ SWOIM COMPARTMENT OCID (tym samym co w Pythonie)
        $this->compartment_id = "ocid1.tenancy.oc1..aaaaaaaahakj6sqsxfouv57essllobaj4euh6e24mxa2ab7i6ktjuju4fxiq"; 
        
        // Model ID: Cohere Command R+
        $this->model_id = 'ocid1.generativeaimodel.oc1.eu-frankfurt-1.amaaaaaask7dceyabdu6rjjmg75pixtecqvjen4x4st4mhs2a4zzfx5cgkmq';
        
        // Endpoint Generative AI we Frankfurcie
        $this->inference_endpoint = 'https://inference.generativeai.eu-frankfurt-1.oci.oraclecloud.com/20231130/actions/chat';
    }

    /**
     * Główna metoda obsługi wiadomości
     */
    public function send_message($user_message, $session_id, $context = null) {
        $start_time = microtime(true);
        
        // ---------------------------------------------------------
        // 1. HARD RULES - BEZPIECZNIKI PHP (Działają ZAWSZE)
        // ---------------------------------------------------------
        
        // AWARIE (Priorytet absolutny)
        if ($this->is_emergency($user_message)) {
            return $this->build_response(
                "🛑 **STOP! To jest sprawa wymagająca natychmiastowej interwencji.**\n\n" .
                "W przypadku awarii natychmiast zadzwoń pod bezpłatny numer alarmowy **994**!\n\n" .
                "Wszelkie zgłoszenia tutaj nie są realizowane. Więcej informacji: [AWARIE](https://pewik.gdynia.pl/awarie).",
                $session_id,
                $start_time
            );
        }

        // DANE OSOBOWE (Blokada RODO)
        if ($this->is_sensitive_data($user_message)) {
             return $this->build_response(
                "🛑 **Zatrzymaj się!** Nie podawaj mi swoich danych osobowych (imienia, nazwiska, adresu, numeru umowy).\n\n" .
                "Jestem wirtualnym asystentem i nie przetwarzam takich danych. Mogę pomóc Ci znaleźć formularz do zmiany danych.",
                $session_id,
                $start_time
            );
        }

        // POWITANIE (Sztywny protokół)
        if ($this->is_greeting($user_message)) {
            return $this->build_response(
                self::MANDATORY_GREETING,
                $session_id,
                $start_time
            );
        }

        // ---------------------------------------------------------
        // 2. DOBÓR WIEDZY (Local RAG w PHP)
        // ---------------------------------------------------------
        $knowledge_context = $this->get_knowledge_context($user_message, $context);

        // ---------------------------------------------------------
        // 3. ZAPYTANIE DO COHERE COMMAND R+ (Przez OCI)
        // ---------------------------------------------------------
        try {
            $bot_response = $this->call_cohere_model($user_message, $knowledge_context);
            return $this->build_response($bot_response, $session_id, $start_time);
            
        } catch (Exception $e) {
            error_log('[PEWIK AI CRITICAL ERROR] ' . $e->getMessage());
            
            // Fallback
            return $this->build_response(
                "Przepraszam, wystąpił problem z połączeniem z serwerem AI. Proszę spróbować później lub napisać na bok@pewik.gdynia.pl.",
                $session_id,
                $start_time,
                true
            );
        }
    }

    /**
     * Wykrywanie awarii (Słowa kluczowe)
     */
    private function is_emergency($text) {
        $keywords = ['awaria', 'brak wody', 'nie mam wody', 'wyciek', 'leje się', 'rura pękła', '994', 'zalanie', 'niedrożna', 'wybija'];
        $text_lower = mb_strtolower($text);
        foreach ($keywords as $word) {
            if (strpos($text_lower, $word) !== false) return true;
        }
        return false;
    }

    /**
     * Wykrywanie danych osobowych
     */
    private function is_sensitive_data($text) {
        $keywords = ['nazywam się', 'mieszkam przy', 'mój pesel', 'nr umowy', 'numer umowy'];
        $text_lower = mb_strtolower($text);
        foreach ($keywords as $word) {
            if (strpos($text_lower, $word) !== false) return true;
        }
        return false;
    }

    /**
     * Wykrywanie powitania
     */
    private function is_greeting($text) {
        $greetings = ['cześć', 'czesc', 'cze', 'hej', 'hejka', 'witam', 'siema', 'siemanko', 'elo', 'dzień dobry', 'dzien dobry', 'start', 'halo'];
        
        // Usuwamy znaki interpunkcyjne
        $clean_text = str_replace(['!', '.', ','], '', mb_strtolower(trim($text)));
        
        if (in_array($clean_text, $greetings)) {
            return true;
        }
        return false;
    }

    /**
     * Mechanizm doboru wiedzy (Przeniesiony z Pythona - Wersja EXPERT)
     */
    private function get_knowledge_context($message, $page_context) {
        $msg = mb_strtolower($message);
        $url = isset($page_context['pageUrl']) ? strtolower($page_context['pageUrl']) : '';
        
        $content = "";

        // 1. WYKLUCZENIA (Ciepła woda, Awarie domowe)
        if ($this->contains_any($msg, ['ciepł', 'zimn', 'grzeje', 'kaloryfer', 'kran', 'zlew', 'wanna', 'toaleta', 'spłuczka', 'rura', 'hydraulik', 'sąsiad', 'zalewa', 'awari'])) {
            $content .= "
TEMAT: ZAKRES ODPOWIEDZIALNOŚCI (CIEPŁA WODA I AWARIE DOMOWE)
ZASADA: PEWIK Gdynia dostarcza TYLKO ZIMNĄ WODĘ i odpowiada za sieć miejską.
- Brak ciepłej wody: To awaria po stronie dostawcy ciepła (OPEC) lub Twojej Spółdzielni/Administratora. Nie zgłaszaj tego do PEWIK.
- Cieknący kran, spłuczka, rura w ścianie (w mieszkaniu): To awaria instalacji wewnętrznej. PEWIK tego nie naprawia. Wezwij hydraulika lub zgłoś Zarządcy.
- Gwarantowane ciśnienie wody: min. 0,2 MPa. Słabsze ciśnienie w kranie to zazwyczaj problem instalacji w budynku (np. zapchane sitka), a nie sieci.
- Link: [Zakres odpowiedzialności](https://pewik.gdynia.pl/strefa-klienta/zalatwianie-spraw/awarie-i-uszkodzenia/)
";
        }

        // 2. PLANOWANE WYŁĄCZENIA I POWIADOMIENIA
        if ($this->contains_any($msg, ['wyłącz', 'brak wody', 'kiedy', 'planowan', 'sms', 'powiadom', 'nie ma wody'])) {
            $content .= "
TEMAT: PLANOWANE WYŁĄCZENIA I POWIADOMIENIA
- Gdzie sprawdzić braki wody? Na bieżąco na stronie: [Planowane wyłączenia](https://pewik.gdynia.pl/awarie/planowane-wylaczenia/).
- Powiadomienia SMS: Oferujemy bezpłatną usługę SMS o awariach i planowanych pracach. 
  Zapisz się tutaj: [Formularz SMS](https://app.bluealert.pl/pewikgdynia/users/simple-register/).
";
        }

        // 3. JAKOŚĆ WODY
        if ($this->contains_any($msg, ['jakość', 'tward', 'kamień', 'ph', 'skład', 'pić', 'kranówk', 'smak', 'kolor'])) {
            $content .= "
TEMAT: JAKOŚĆ WODY I PARAMETRY
Woda dostarczana przez PEWIK spełnia wszystkie normy sanitarne i nadaje się do picia z kranu.
Średnie parametry wody w Gdyni:
- Twardość: 60-500 mg/l CaCO3 (woda średniotwarda lub twarda).
- Odczyn pH: 6,5 – 9,5.
- Żelazo: poniżej 200 μg/l.
- Mętność: poniżej 1,0 NTU.
Szczegółowe komunikaty o jakości: [Jakość Wody](https://pewik.gdynia.pl/strefa-mieszkanca/jakosc-wody/).
";
        }

        // 4. KANALIZACJA - CZEGO NIE WRZUCAĆ
        if ($this->contains_any($msg, ['toalet', 'wrzuca', 'śmieci', 'zator', 'zapcha', 'olej', 'chustecz'])) {
            $content .= "
TEMAT: ZASADY KORZYSTANIA Z KANALIZACJI (Czego nie wrzucać)
Aby uniknąć zatorów, do toalety NIGDY nie wrzucaj:
- Artykułów higienicznych: nawilżanych chusteczek (nie rozpuszczają się!), patyczków do uszu, podpasek, wacików.
- Tłuszczów i olejów: Tężeją w rurach jak beton. Zlej olej do słoika i wyrzuć do śmieci.
- Resztek jedzenia: Wyrzuć do bio lub kompostownika.
- Materiałów budowlanych: Farby, gips, lakiery.
";
        }

        // 5. WNIOSKI, FORMULARZE (Pełna lista)
        if ($this->contains_any($msg, ['wniosek', 'przyłącz', 'formularz', 'numer', 'przepis', 'właściciel', 'nazwisko', 'małżeństwo', 'dane', 'umow', 'budow', 'projekt', 'odbiór']) || strpos($url, 'wnioski') !== false) {
            $content .= "
TEMAT: WNIOSKI I FORMULARZE (Pełna lista)
Strona z wnioskami: [https://pewik.gdynia.pl/wnioski](https://pewik.gdynia.pl/wnioski)

A. PRZYŁĄCZENIE DO SIECI:
- Wniosek nr 1: O sprawdzenie MOŻLIWOŚCI przyłączenia.
- Wniosek nr 2: O wydanie WARUNKÓW technicznych.
- Wniosek nr 3: Uzgodnienie projektu.
- Wniosek nr 4: Wykonanie włączenia / kontrola przyłącza.
- Wniosek nr 5: Protokół odbioru przyłącza.
- Wniosek nr 7: Zmiana lokalizacji wodomierza / warunków.

B. UMOWY I DANE:
- Wniosek nr 10: Nowa umowa / Przepisanie licznika (dołącz Załącznik nr 1 - Protokół).
  * WAŻNE: Do wniosku nr 10 NIE musisz dołączać aktu notarialnego ani dokumentu własności. Wystarczy sam wniosek i Protokół.
- Wniosek nr 11: Rozwiązanie umowy.
- Wniosek nr 18: Zmiana danych (nazwisko, adres).

C. WODOMIERZE LOKALOWE I OGRODOWE:
- Wniosek nr 21: Warunki dla wodomierzy lokalowych (składa Zarządca).
- Wniosek nr 22: Kontrola montażu wodomierzy lokalowych.
- Wniosek nr 23: Wodomierz OGRODOWY (podlicznik) - kontrola montażu.

D. USŁUGI DODATKOWE:
- Wniosek nr 24: Zlecenie usługi nietaryfowej.
- Wniosek nr 26: Kopie map/dokumentacji archiwalnej.
- Wniosek nr 27: Pobór wody z hydrantu.
";
        }

        // 6. CENY, FAKTURY, PŁATNOŚCI
        if ($this->contains_any($msg, ['cen', 'koszt', 'taryf', 'faktur', 'płatnoś', 'ile płacę', 'rachun', 'korekt', 'błąd', 'reklamac', 'wezwan', 'windykac', 'ryczałt', 'samofakturowan', 'polecenie zapłaty'])) {
            $content .= "
TEMAT: FINANSE I ROZLICZENIA
- Cennik: Nie podawaj kwot! Prawidłowy link to: [CENY I TARYFY](https://pewik.gdynia.pl/ceny). (Użyj dokładnie tego linku!).
- e-BOK: Wszystkie faktury są tu: [e-BOK](https://pewik.gdynia.pl/ebok).
- Korekta/Błąd na fakturze: Nie trzeba wniosku. Napisz e-mail na bok@pewik.gdynia.pl (podaj nr faktury i stan licznika).
- Reklamacja: Wniosek nr 15 lub e-mail. Termin odpowiedzi: 30 dni.
- Wezwanie do zapłaty: Faktura źródłowa jest w e-BOK.
- Polecenie zapłaty: Wniosek nr 12 (start), Wniosek nr 13 (stop).
- Rozliczenie Rzeczywiste (Samofakturowanie): [Jak aktywować](https://pewik.gdynia.pl/strefa-klienta/zalatwianie-spraw/sf/).
";
        }

        // 7. WODOMIERZE, ODCZYTY, OGRÓD
        if ($this->contains_any($msg, ['licznik', 'wodomierz', 'odczyt', 'stan', 'ogród', 'ogrodow', 'podlewa', 'trawnik', 'legalizac', 'wymian', 'zamarz', 'sms'])) {
            $content .= "
TEMAT: WODOMIERZE I ODCZYTY
- Podanie odczytu: 4 sposoby:
  1. Przez stronę [e-Odczyt](https://pewik.gdynia.pl/e-odczyt).
  2. Przez konto [e-BOK](https://pewik.gdynia.pl/ebok).
  3. Wysyłając SMS (Instrukcja: [SMS](https://pewik.gdynia.pl/strefa-klienta/podaj-wskazanie-wodomierza-poprzez-sms)).
  4. Dzwoniąc na Teleodczyt (Voicebot).
- Wodomierz GŁÓWNY: Własność PEWIK. Wymiana bezpłatna (pilnuje PEWIK).
- Wodomierz OGRODOWY (Podlicznik):
  * Własność Klienta (Ty kupujesz, montujesz i pilnujesz legalizacji co 5 lat).
  * Po wymianie/montażu wyślij Wniosek nr 23 (lub e-mail) o oplombowanie.
- Zamarznięcie: Klient płaci za wymianę zamarzniętego licznika.
";
        }
        
        // 8. POMOC E-BOK
        if ($this->contains_any($msg, ['logow', 'rejestrac', 'hasł', 'mail', 'konto', 'e-bok', 'ebok', 'faktura elektroniczn', 'nie działa', 'błąd'])) {
            $content .= "
TEMAT: POMOC E-BOK
- Rejestracja: [Formularz rejestracyjny](https://ebok.pewik.gdynia.pl/public/rejestracja).
- Logowanie: [Zaloguj się](https://ebok.pewik.gdynia.pl/login).
- Błąd 'Błędne dane': Oznacza brak PESEL/NIP w systemie -> Napisz do BOK.
- Brak e-faktury: Musisz ją aktywować w zakładce 'Klient' w e-BOK.
";
        }
        
        // 9. BAZA KONTAKTOWA (Zawsze dodawana)
        $content .= "
KONTAKT BOK:
- Strona: [KONTAKT](https://pewik.gdynia.pl/kontakt/biuro-obslugi-klienta/)
- E-mail: bok@pewik.gdynia.pl (Preferowany)
- Telefon: 58 66 87 311 (7:00-15:00)
- Adres: ul. Witomińska 21, Gdynia
";
        return $content;
    }

    /**
     * Helper do sprawdzania wielu słów kluczowych
     */
    private function contains_any($haystack, $needles) {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) return true;
        }
        return false;
    }

    /**
     * Wysyła zapytanie do OCI Generative AI (Endpoint Chat)
     */
    private function call_cohere_model($user_message, $knowledge_context) {
        
        // Prompt Systemowy (Preamble)
        $system_preamble = "Jesteś asystentem PEWIK Gdynia. Odpowiadasz na pytania użytkownika.
ZASADY:
1. Odpowiadaj TYLKO na podstawie poniższej WIEDZY.
2. **ZASADA LINKÓW:** Jeśli w WIEDZY znajduje się link URL (np. do wniosku, cennika, e-BOK), MUSISZ go zawrzeć w odpowiedzi.
3. **ZASADA WYKLUCZEŃ:** Jeśli użytkownik pyta o ciepłą wodę lub awarię wewnątrz mieszkania, poinformuj, że PEWIK odpowiada tylko za zimną wodę i sieć miejską. Odeślij do Zarządcy.
4. **ZASADA CEN:** Nigdy nie podawaj kwot (zł). Podawaj tylko link do cennika.
5. Zachowaj formatowanie Markdown.
6. Zwracaj się per 'Ty'.

WIEDZA:
$knowledge_context
";

        // Struktura JSON dla Cohere Command R+ w OCI
        $body = array(
            'compartmentId' => $this->compartment_id,
            'servingMode' => array(
                'servingType' => 'ON_DEMAND',
                'modelId' => $this->model_id
            ),
            'chatRequest' => array(
                'message' => $user_message,
                'preambleOverride' => $system_preamble,
                'maxTokens' => 600,
                'temperature' => 0,
                'topP' => 0.75,
                'frequencyPenalty' => 0,
                'presencePenalty' => 0
            )
        );

        $body_json = json_encode($body);

        // Podpisanie i wysłanie
        $headers = $this->signer->sign_request('POST', $this->inference_endpoint, array(), $body_json);
        $wp_headers = $this->format_headers_for_wp($headers);

        // Timeout 120s
        $response = wp_remote_post($this->inference_endpoint, array(
            'headers' => $wp_headers,
            'body' => $body_json,
            'timeout' => 120,
            'httpversion' => '1.1'
        ));

        if (is_wp_error($response)) {
            throw new Exception('WP Error: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('OCI API Error: ' . $response_body);
            throw new Exception('Błąd API Oracle (Kod ' . $response_code . ')');
        }

        $data = json_decode($response_body, true);

        if (isset($data['chatResponse']['text'])) {
            return $data['chatResponse']['text'];
        }
        
        return "Przepraszam, nie otrzymałem poprawnej odpowiedzi od systemu.";
    }

    // Metoda pomocnicza do budowania odpowiedzi dla JS
    private function build_response($message, $session_id, $start_time, $error = false) {
        $response_time = microtime(true) - $start_time;
        return array(
            'error' => $error,
            'message' => $message,
            'sessionId' => $session_id,
            'messageId' => rand(1000,9999),
            'hasTrace' => false,
            'hasCitations' => false
        );
    }
    
    private function format_headers_for_wp($headers) {
        $wp_headers = array();
        foreach ($headers as $key => $value) {
            $header_name = implode('-', array_map('ucfirst', explode('-', $key)));
            $wp_headers[$header_name] = $value;
        }
        return $wp_headers;
    }
    
    public function create_session() {
        return 'genai_' . uniqid();
    }
}