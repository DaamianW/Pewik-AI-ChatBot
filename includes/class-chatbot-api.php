<?php
/**
 * Klasa do komunikacji z OCI Generative AI (Model Cohere Command R+)
 * Wersja: FIXED (Zgodna z działającym skryptem Python)
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
        // Inicjalizacja Signera
        if (!class_exists('PEWIK_OCI_Request_Signer')) {
            error_log('Krytyczny błąd: Brak klasy PEWIK_OCI_Request_Signer');
            return;
        }
        $this->signer = new PEWIK_OCI_Request_Signer();
        
        // DANE Z TWOJEGO PLIKU PYTHON
        $this->compartment_id = "ocid1.tenancy.oc1..aaaaaaaahakj6sqsxfouv57essllobaj4euh6e24mxa2ab7i6ktjuju4fxiq"; 
        $this->model_id = 'ocid1.generativeaimodel.oc1.eu-frankfurt-1.amaaaaaask7dceyabdu6rjjmg75pixtecqvjen4x4st4mhs2a4zzfx5cgkmq';
        $this->inference_endpoint = 'https://inference.generativeai.eu-frankfurt-1.oci.oraclecloud.com/20231130/actions/chat';
    }

    /**
     * Główna metoda obsługi wiadomości
     */
    public function send_message($user_message, $session_id, $context = null) {
        $start_time = microtime(true);
        
        // ---------------------------------------------------------
        // 1. HARD RULES (Zgodne z Pythonem)
        // ---------------------------------------------------------
        
        if ($this->is_emergency($user_message)) {
            return $this->build_response(
                "🛑 **STOP! To jest sprawa wymagająca natychmiastowej interwencji.**\n\nW przypadku awarii natychmiast zadzwoń pod bezpłatny numer alarmowy **994**!",
                $session_id, 
                $start_time
            );
        }

        if ($this->is_sensitive_data($user_message)) {
             return $this->build_response(
                "🛑 **Zatrzymaj się!** Nie podawaj mi swoich danych osobowych. Jestem wyszukiwarką informacji i nie przetwarzam danych wrażliwych.",
                $session_id, 
                $start_time
            );
        }

        if ($this->is_greeting($user_message)) {
            return $this->build_response(self::MANDATORY_GREETING, $session_id, $start_time);
        }

        // ---------------------------------------------------------
        // 2. DOBÓR WIEDZY (Local RAG)
        // ---------------------------------------------------------
        $knowledge_context = $this->get_knowledge_context($user_message, $context);

        // ---------------------------------------------------------
        // 3. ZAPYTANIE DO ORACLE (Fix Błędu 400)
        // ---------------------------------------------------------
        try {
            $bot_response = $this->call_cohere_model($user_message, $knowledge_context);
            return $this->build_response($bot_response, $session_id, $start_time);
            
        } catch (Exception $e) {
            return $this->build_response(
                "⛔ BŁĄD SYSTEMU: " . $e->getMessage(), 
                $session_id,
                $start_time,
                true
            );
        }
    }

    // --- METODY POMOCNICZE (HARD RULES) ---

    private function is_emergency($text) {
        $keywords = ['wyciek', 'leje się', 'zalewa', 'pękła rura', 'tryska', 'powódź', 'wybija'];
        $text_lower = mb_strtolower($text);
        foreach ($keywords as $word) {
            if (strpos($text_lower, $word) !== false) return true;
        }
        return false;
    }

    private function is_sensitive_data($text) {
        $text_lower = mb_strtolower(trim($text));

        // 1. TWARDE FRAZY (Konkretne zwroty wskazujące na podawanie danych)
        $keywords = [
            'nazywam się', 'mieszkam przy', 'mój pesel', 'nr umowy', 'numer umowy', 
            'dowód osobisty', 'moje nazwisko', 'pesel', 'seria dowodu', 'nr klienta'
        ];
        
        foreach ($keywords as $word) {
            if (strpos($text_lower, $word) !== false) return true;
        }

        // 2. HEURYSTYKA: Wykrywanie samego "Imię Nazwisko" (np. "Jan Kowalski" lub "Kowalski Jan")
        // Działa tylko dla krótkich wiadomości (< 50 znaków), co jest typowe dla przedstawiania się.
        if (mb_strlen($text) < 50) {
            // Regex: Dwa słowa zaczynające się z Wielkiej Litery (uwzględnia polskie znaki i nazwiska z myślnikiem)
            $pattern = '/^[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż]+\s+[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż]+(?:-[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż]+)?$/u';
            
            if (preg_match($pattern, trim($text))) {
                // WYKLUCZENIA (Słowa, które mogą być napisane z dużej litery, ale nie są osobą)
                // Np. "Awaria Wody", "Biuro Obsługi", "Woda Gdynia"
                $safe_words = [
                    'awaria', 'woda', 'ścieki', 'gdynia', 'pewik', 'biuro', 'obsługi', 'klienta', 
                    'adres', 'ulica', 'gdzie', 'kiedy', 'jaka', 'cena', 'koszt', 'faktura', 'taryfa'
                ];
                
                foreach ($safe_words as $safe) {
                    if (strpos($text_lower, $safe) !== false) return false; // To bezpieczna fraza, nie blokuj
                }
                
                return true; // Nie ma bezpiecznych słów, a wygląda jak Imię Nazwisko -> BLOKUJEMY
            }
        }

        return false;
    }

    private function is_greeting($text) {
        $greetings = ['cześć', 'czesc', 'cze', 'hej', 'hejka', 'witam', 'siema', 'siemanko', 'elo', 'dzień dobry', 'dzien dobry', 'start', 'halo'];
        $clean_text = str_replace(['!', '.', ','], '', mb_strtolower(trim($text)));
        return in_array($clean_text, $greetings);
    }

    // --- RAG (WIEDZA) ---

    private function get_knowledge_context($message, $page_context) {
        $msg = mb_strtolower($message);
        $url = isset($page_context['pageUrl']) ? strtolower($page_context['pageUrl']) : '';
        $content = "";

        // 1. AWARIE I ZGŁOSZENIA (ZAKTUALIZOWANE O DANE DYSPOZYTORA)
        // 1. AWARIE, BRAK WODY I DIAGNOSTYKA (ZAKTUALIZOWANE - LEPSZA LOGIKA)
        if ($this->contains_any($msg, ['awari', 'pękł', 'rura', 'rury', 'wyciek', 'leje', 'zalewa', 'brak wody', 'nie mam wody', 'sucho w kranie', 'ciśnieni', 'kran', 'spłuczk', 'hydraulik', '994', 'pogotowi', 'sąsiedzi', 'sąsiad'])) {
            $content .= "TEMAT: DIAGNOSTYKA BRAKU WODY I AWARII\n";
            
            $content .= "--- KROK 1: SPRAWDŹ STRONĘ WWW (CZY TO AWARIA MASOWA?) ---\n";
            $content .= "Zanim zadzwonisz, sprawdź mapę awarii i wyłączeń: [PLANOWANE WYŁĄCZENIA I AWARIE](https://pewik.gdynia.pl/awarie/planowane-wylaczenia/).\n";
            $content .= "Jeśli Twój adres tam jest -> Trwają prace, musisz poczekać.\n";
            
            $content .= "--- KROK 2: DIAGNOZA SĄSIEDZKA (BRAK WODY) ---\n";
            $content .= "Sytuacja A: Sąsiedzi też nie mają wody -> To awaria sieciowa. Sprawdź stronę www lub zadzwoń na 994.\n";
            $content .= "Sytuacja B: Sąsiedzi MAJĄ wodę, a Ty nie -> To awaria Twojej instalacji wewnętrznej (np. zakręcony zawór, zapchany filtr). PEWIK tego nie naprawia. Skontaktuj się z Administratorem Budynku lub hydraulikiem.\n";
            
            $content .= "--- KROK 3: ZGŁASZANIE WYCIEKÓW ---\n";
            $content .= "Wyciek na ulicy/chodniku/przed licznikiem głównym -> Alarm 994 (PEWIK).\n";
            $content .= "Wyciek w domu/za licznikiem -> Hydraulik (KLIENT).\n";
            
            $content .= "--- WAŻNE KONTAKTY ---\n";
            $content .= "Dyspozytor (24h): 994 lub +48 58 66 87 311. E-mail: ed@pewik.gdynia.pl\n";
        }

        // 2. JAKOŚĆ
        if ($this->contains_any($msg, ['jakość', 'tward', 'kamień', 'ph', 'skład', 'pić', 'kranówk'])) {
            $content .= "TEMAT: JAKOŚĆ WODY\nTwardość: 60-500 mg/l CaCO3. pH: 6.5-9.5. Woda nadaje się do picia. Więcej: [Jakość Wody](https://pewik.gdynia.pl/strefa-mieszkanca/jakosc-wody/).\n";
        }
        
        // 3. KANALIZACJA
        if ($this->contains_any($msg, ['toalet', 'wrzuca', 'śmieci', 'zator', 'zapcha', 'olej'])) {
            $content .= "TEMAT: KANALIZACJA\nNie wrzucaj: chusteczek nawilżanych, tłuszczu, resztek jedzenia, materiałów budowlanych.\n";
        }

        // 4. WNIOSKI I FORMULARZE (PRECYZYJNE DEFINICJE)
        if ($this->contains_any($msg, ['wniosek', 'formularz', 'druk', 'dokument', 'gdzie', 'skąd', 'pobrać', 'załatwić', 'przyłącz', 'umow', 'przepis', 'właściciel', 'reklamac', 'rozwiąz', 'zrezygn', 'nazwisk', 'dane', 'projekt', 'mapy', 'hydrant', 'urządzen', 'budow', 'przebudow', 'podłącz'])) {
            $content .= "TEMAT: LISTA WNIOSKÓW I FORMULARZY\n";
            $content .= "Wszystkie druki są tutaj: [Formularze i wnioski](https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/). Nie musisz iść do biura - wyślij skan na e-mail: bok@pewik.gdynia.pl.\n";
            
            $content .= "--- ETAP 1: PLANOWANIE PRZYŁĄCZA (LISTA A) ---\n";
            $content .= "Nr 1: Zapytanie o MOŻLIWOŚĆ przyłączenia (tylko informacja, czy sieć istnieje).\n";
            $content .= "Nr 2: Wniosek o WARUNKI PRZYŁĄCZENIA (niezbędne, aby zlecić projektantowi projekt).\n";
            $content .= "Nr 3: Uzgodnienie PROJEKTU (składasz, gdy masz już gotowy projekt).\n";
            
            $content .= "--- ETAP 2: BUDOWA I ODBIÓR PRZYŁĄCZA (LISTA A) ---\n";
            $content .= "Nr 4: Zgłoszenie budowy/WŁĄCZENIA (gdy chcesz fizycznie wykonać włączenie do sieci i zamówić nadzór).\n";
            $content .= "Nr 5: Protokół ODBIORU (dokument końcowy po budowie).\n";
            $content .= "Nr 6: Zaświadczenie o przyłączeniu (np. do banku lub urzędu).\n";
            $content .= "Nr 7: Zmiana warunków lub przeniesienie wodomierza głównego.\n";
            
            $content .= "--- ETAP 3: UMOWY I ROZLICZENIA (LISTA B) ---\n";
            $content .= "Nr 10: ZAWARCIE UMOWY (Nowa umowa lub przepisanie licznika na inną osobę). Wymagany Protokół zdawczo-odbiorczy (Zał. 1).\n";
            $content .= "Nr 11: ROZWIĄZANIE UMOWY (Wypowiedzenie lub Porozumienie stron).\n";
            $content .= "Nr 12: Polecenie zapłaty (Włącz).\n";
            $content .= "Nr 13: Polecenie zapłaty (Odwołaj).\n";
            $content .= "Nr 14: Raport lokalowy (dla zarządców budynków).\n";
            $content .= "Nr 15: REKLAMACJA usług/faktury.\n";
            $content .= "Nr 16/17: Zgłoszenie szkody (ogólne/samochodowe).\n";
            $content .= "Nr 18: AKTUALIZACJA DANYCH (tylko zmiana nazwiska/adresu tej samej osoby, NIE przepisanie umowy!).\n";
            
            $content .= "--- WODOMIERZE LOKALOWE I OGRODOWE (LISTA C) ---\n";
            $content .= "Nr 21: Warunki techniczne na podliczniki w bloku (składa Zarządca).\n";
            $content .= "Nr 22: Kontrola montażu w bloku (składa Zarządca).\n";
            $content .= "Nr 23: WODOMIERZ OGRODOWY (Tylko pierwszy montaż! Wymianę zgłaszasz mailem bez wniosku).\n";
            
            $content .= "--- USŁUGI DODATKOWE (LISTA D i E) ---\n";
            $content .= "Nr 24: Zlecenie usługi nietaryfowej (płatnej).\n";
            $content .= "Nr 25: Umowa na projekt/budowę kanalizacji.\n";
            $content .= "Nr 26: Kopie map archiwalnych.\n";
            $content .= "Nr 27: Pobór wody z HYDRANTU.\n";
            $content .= "Nr 32: Uzgodnienie projektu URZĄDZEŃ (sieci, nie przyłączy).\n";
            $content .= "Nr 33/34: Odbiór techniczny URZĄDZEŃ.\n";
        }

        // 5. FINANSE - CENY I RYCZAŁT (DODANO 'STAWKI' I 'OPŁATY')
        if ($this->contains_any($msg, ['cen', 'koszt', 'taryf', 'faktur', 'płatnoś', 'ile płacę', 'ryczałt', 'norm', 'bez liczni', 'stawk', 'opłat', 'wysokoś'])) {
            $content .= "TEMAT: CENY, STAWKI I RYCZAŁT\n";
            $content .= "Gdzie znaleźć stawki?: [CENY I TARYFY](https://pewik.gdynia.pl/strefa-klienta/ceny-i-taryfy/).\n";
            $content .= "Dla kogo?: Wybierz 'Lista A' dla Gdyni/Rumi/Redy lub 'Lista C' dla Gminy Puck. Znajdziesz tam szczegółowe tabele opłat.\n";
            $content .= "Ryczałt: Przy braku licznika płacisz wg norm zużycia.\n";
        }

        // 6. WODOMIERZE I ODCZYTY (ZAKTUALIZOWANE)
        if ($this->contains_any($msg, ['licznik', 'wodomierz', 'odczyt', 'ogród', 'legalizac', 'wymian', 'mróz', 'zamarz', 'podlicznik', 'studzienk', 'stan', 'podaj', 'przekaz'])) {
            $content .= "TEMAT: WODOMIERZE I ODCZYTY\n";
            
            // Metody podawania odczytu
            $content .= "--- JAK PODAĆ ODCZYT? ---\n";
            $content .= "Masz 4 sposoby:\n";
            $content .= "1. [e-Odczyt](https://pewik.gdynia.pl/e-odczyt) (bez logowania).\n";
            $content .= "2. [e-BOK](https://pewik.gdynia.pl/ebok).\n";
            $content .= "3. SMS (instrukcja na stronie).\n";
            $content .= "4. Teleodczyt (Voicebot): zadzwoń i podaj stan głosowo.\n";
            
            // Odpowiedzialność
            $content .= "--- ODPOWIEDZIALNOŚĆ ---\n";
            $content .= "Główny: Wymiana/Legalizacja przez PEWIK (bezpłatnie).\n";
            $content .= "Ogrodowy (Podlicznik): Własność KLIENTA. Zakup, montaż, legalizacja (co 5 lat) i wymiana na koszt KLIENTA.\n";
            $content .= "Zima: Jeśli licznik pęknie od mrozu -> PŁACI KLIENT.\n";
        }
        
        // 7. E-BOK (ZNACZNIE ROZBUDOWANA SEKCJA)
        if ($this->contains_any($msg, ['logow', 'rejestrac', 'hasł', 'e-bok', 'ebok', 'problem', 'e-faktur', 'efaktur', 'na maila', 'sms', 'powiadom', 'saldo', 'konto', 'internetow'])) {
            $content .= "TEMAT: E-BOK (Elektroniczne Biuro Obsługi Klienta)\n";
            
            $content .= "--- CO TO JEST? ---\n";
            $content .= "Bezpłatny serwis do: sprawdzania salda, pobierania faktur, płatności online i składania wniosków.\n";
            
            $content .= "--- REJESTRACJA I LOGOWANIE ---\n";
            $content .= "Rejestracja: [Wniosek](https://ebok.pewik.gdynia.pl/public/rejestracja). Po wysłaniu kliknij link w mailu (sprawdź SPAM!). Konto aktywne po otrzymaniu DRUGIEGO maila.\n";
            $content .= "Logowanie: [https://ebok.pewik.gdynia.pl/login](https://ebok.pewik.gdynia.pl/login)\n";
            $content .= "Błąd 'Błędne dane'?: Oznacza brak PESEL/NIP w naszej bazie. Skontaktuj się z BOK, aby uzupełnić dane.\n";
            
            $content .= "--- E-FAKTURA (Faktura na maila) ---\n";
            $content .= "Jak włączyć?: Zaloguj się -> Zakładka 'Klient' -> Sekcja 'e-faktura' -> Kliknij 'ZMIEŃ'.\n";
            
            $content .= "--- POWIADOMIENIA SMS ---\n";
            $content .= "Chcesz SMS o fakturze?: Wypełnij osobny formularz: [Formularz SMS](https://app.bluealert.pl/pewikgdynia/users/simple-register/).\n";
        }

        // 8. DANE ADRESOWE I KONTAKT (ZAKTUALIZOWANE)
if ($this->contains_any($msg, ['adres', 'siedzib', 'gdzie', 'dojazd', 'ulic', 'biur', 'lokalizacj', 'kontakt', 'telefon', 'godziny', 'otwarte', 'czynne', 'mail', 'poczt', 'numer', 'zadzwonić', 'infolinia', 'rozmow', 'email' , 'wrzutnia'])) {
            $content .= "TEMAT: DANE KONTAKTOWE I ADRESOWE\n";
            
            $content .= "--- TELEFON (Infolinia) ---\n";
            $content .= "Numer: +48 58 66 87 311\n";
            $content .= "Godziny: Pn-Pt 7:00 – 15:00\n";
            
            $content .= "--- WIZYTA OSOBISTA (Biuro Obsługi Klienta) ---\n";
            $content .= "Adres: ul. Witomińska 21, 81-311 Gdynia\n";
            $content .= "Godziny: Pn-Pt 8:00 – 15:00\n";

            $content .= "--- WIZYTA WRZUTNIA DOKUMENTÓW (Biuro Obsługi Klienta) ---\n";
            $content .= "Wrzutnia dokumentów (przy wejściu): Pn-Pt 6:30 – 16:30.\n";
            
            $content .= "--- KANAŁY ELEKTRONICZNE (ZALECANE) ---\n";
            $content .= "E-mail: bok@pewik.gdynia.pl\n";
            $content .= "e-BOK: https://pewik.gdynia.pl/ebok\n";
            $content .= "Zasada: Zachęcamy do korzystania z e-maila i e-BOK zamiast wizyt papierowych.\n";
        }

        // 9. WAŻNOŚĆ DOKUMENTÓW (NOWE - Decyzje, Warunki przyłączenia)
        if ($this->contains_any($msg, ['ważn', 'termin', 'decyzj', 'warunk', 'wygas', 'ile czas', 'daty', 'kiedy kończy', 'papiery'])) {
            $content .= "TEMAT: WAŻNOŚĆ DOKUMENTÓW\nZASADA: Termin ważności każdego dokumentu (np. decyzji, warunków przyłączenia) jest szczegółowo określony w treści tego dokumentu. Proszę sprawdzić datę i termin bezpośrednio w posiadanym dokumencie.\n";
        }

        // 10. WEZWANIA I BRAK DOKUMENTÓW (NOWE - FIX)
        if ($this->contains_any($msg, ['wezwan', 'monit', 'zapłat', 'brak faktur', 'nie widzę', 'nie mam dokument', 'zgubiłem', 'zniszcz', 'duplikat', 'kopia'])) {
            $content .= "TEMAT: WEZWANIA DO ZAPŁATY I BRAKUJĄCE FAKTURY\n";
            $content .= "ZASADA: Wszystkie faktury (również te, których dotyczy wezwanie do zapłaty) są ZAWSZE dostępne w e-BOK.\n";
            $content .= "ROZWIĄZANIE: Zaloguj się do [e-BOK](https://pewik.gdynia.pl/ebok) i pobierz dokument. Nie musisz dzwonić do biura.\n";
        }

        // 11. KOREKTA FAKTURY (NOWE - FIX DLA "JAK ZROBIĆ KOREKTĘ")
        if ($this->contains_any($msg, ['korekt', 'skoryg', 'błąd', 'pomyłk', 'zły odczyt', 'zła faktur', 'reklamac'])) {
            $content .= "TEMAT: KOREKTA FAKTURY / REKLAMACJA\n";
            $content .= "PROCEDURA: Wyślij e-mail na bok@pewik.gdynia.pl. W wiadomości musisz podać 3 rzeczy:\n";
            $content .= "1. Numer faktury pierwotnej (tej z błędem).\n";
            $content .= "2. Twój punkt rozliczeniowy.\n";
            $content .= "3. Aktualne wskazanie wodomierza (stan licznika).\n";
        }

        // 12. ROZLICZENIA I SZACUNKI (ROZBUDOWANE)
        if ($this->contains_any($msg, ['rozlicz', 'szacunk', 'prognoz', 'dlaczego tak dużo', 'stan licznik', 'nie było mnie'])) {
            $content .= "TEMAT: ROZLICZENIA I FAKTURY SZACUNKOWE\n";
            $content .= "Dlaczego szacunek? Bo nie znamy Twojego odczytu (brak dostępu pracownika).\n";
            $content .= "Rozwiązanie: Przekaż odczyt samodzielnie (przez e-BOK, e-Odczyt, SMS) w swoim okresie obrachunkowym.\n";
        }

        // 13. POLECENIE ZAPŁATY (NOWE)
        if ($this->contains_any($msg, ['polecen', 'zapłat', 'automatycz', 'z konta', 'samo się', 'anulow'])) {
            $content .= "TEMAT: POLECENIE ZAPŁATY\n";
            $content .= "Aktywacja (Włącz): Wyślij do nas Wniosek nr 12. My załatwimy autoryzację w banku (trwa do 30 dni).\n";
            $content .= "Rezygnacja (Wyłącz): Wyślij Wniosek nr 13 (min. 14 dni przed terminem).\n";
        }

        // 14. SAMODZIELNE FAKTUROWANIE (NOWE)
        if ($this->contains_any($msg, ['sam wystaw', 'samodzieln', 'rzeczywist', 'fakturowa'])) {
            $content .= "TEMAT: SAMODZIELNE FAKTUROWANIE (ROZLICZENIA RZECZYWISTE)\n";
            $content .= "Co to jest? Usługa w e-BOK pozwalająca samemu wystawiać faktury (unikasz szacunków).\n";
            $content .= "Jak włączyć? W e-BOK zakładka 'Klient' -> 'Rozliczenia Rzeczywiste' -> 'ZMIEŃ'.\n";
            $content .= "Wymagania: Musisz mieć aktywne konto e-BOK i zgodę na e-fakturę.\n";
        }

        // 15. WŁADZE SPÓŁKI I STRUKTURA WŁASNOŚCIOWA (NOWE - BIP)
        if ($this->contains_any($msg, ['zarząd', 'prezes', 'dyrektor', 'kierownik', 'władz', 'nadzorcz', 'rady', 'radą', 'rada', 'właściciel', 'udziałow', 'wspólni', 'gmin', 'kto rządzi', 'skład', 'osoby'])) {
            $content .= "TEMAT: WŁADZE SPÓŁKI I STRUKTURA WŁASNOŚCIOWA (BIP)\n";
            
            $content .= "--- ZARZĄD SPÓŁKI ---\n";
            $content .= "Prezes Zarządu: Jacek Kieloch (od 15.09.2025).\n";
            $content .= "Wiceprezes Zarządu: Wiesław Kujawski.\n";
            
            $content .= "--- RADA NADZORCZA (XII Kadencja) ---\n";
            $content .= "1. Marcin Zawisza – Przewodniczący Rady Nadzorczej\n";
            $content .= "2. Anna Lewandowska – Zastępczyni Przewodniczącego\n";
            $content .= "3. Kamila Kraszkiewicz – Członkini\n";
            $content .= "4. Karolina Maciąg – Członkini\n";
            $content .= "5. Łukasz Galiński – Członek\n";
            $content .= "6. Radosław Skwarło – Członek\n";
            
            $content .= "--- ZGROMADZENIE WSPÓLNIKÓW (WŁAŚCICIELE) ---\n";
            $content .= "Udziałowcy: Gmina Miasta Gdyni, Rumia, Reda, Wejherowo (Miasto i Gmina), Kosakowo.\n";
            $content .= "Inni: KZG 'Dolina Redy i Chylonki', PFR (Fundusz Inwestycji Samorządowych).\n";
        }

        // 16. DANE REJESTROWE I RACHUNEK BANKOWY (NOWE - BIP)
        if ($this->contains_any($msg, ['nip', 'regon', 'krs', 'konto', 'bank', 'numer konta', 'przelew', 'dane firmy', 'faktur', 'pkd', 'działalnoś', 'czym się zajmuje'])) {
            $content .= "TEMAT: DANE REJESTROWE I BANKOWE (BIP)\n";
            $content .= "Nazwa: Przedsiębiorstwo Wodociągów i Kanalizacji Sp. z o.o. w Gdyni.\n";
            $content .= "Siedziba: ul. Witomińska 29, 81-311 Gdynia.\n";
            $content .= "NIP: 586-010-44-34 | REGON: 190563879 | KRS: 0000126973.\n";
            $content .= "Konto Bankowe: Citibank Handlowy 89 1030 1120 0000 0000 0340 6701.\n";
            $content .= "PKD (Główne): 36.00.Z (Woda), 37.00.Z (Ścieki). Pełna lista w BIP.\n";
        }

        // 17. SCHEMAT ORGANIZACYJNY (NOWE - VISIO)
        if ($this->contains_any($msg, ['schemat', 'struktur', 'organizac', 'dział', 'pion', 'podlega', 'dyrektor', 'kierownik'])) {
            $content .= "TEMAT: SCHEMAT ORGANIZACYJNY SPÓŁKI\n";
            $content .= "ZARZĄD: Prezes (PZ), Wiceprezes (WZ).\n";
            
            $content .= "--- PIONY BEZPOŚREDNIE ---\n";
            $content .= "Podległe Zarządowi: Biuro Obsługi Klienta (ZOK), Biuro Prawne, Biuro Personalne, Informatyka, Główny Księgowy, Dział Zamówień.\n";
            
            $content .= "--- PION EKSPLOATACJI (Dyr. DE) ---\n";
            $content .= "Jednostki: Dyspozytornia (ED), Produkcja Wody, Sieć Wodociągowa, Sieć Kanalizacyjna, Oczyszczalnia Ścieków, Ochrona Środowiska.\n";
            
            $content .= "--- PION TECHNICZNY I ROZWOJU (Dyr. DT) ---\n";
            $content .= "Jednostki: Dział Techniczny, Obsługa Inwestycji i Remontów, Laboratorium Wody i Ścieków, Dział Sprzętu, Utrzymanie Ruchu.\n";
        }

        // 18. MAJĄTEK I FINANSE SPÓŁKI (KOMPLETNE DANE: Majątek + Wyniki + Podział zysku)
        if ($this->contains_any($msg, ['kapitał', 'majątek', 'wartość', 'finans', 'pieniądz', 'środki trwałe', 'grunty', 'budynki', 'infrastruktura', 'ile warta', 'aktywa', 'zysk', 'dochód', 'strat', 'wynik finansow', 'ile zarabia', 'czy zarabia', 'kondycja', 'podział', 'przeznaczen', 'pokryci', 'zapasow', 'dywidend'])) {
            $content .= "TEMAT: MAJĄTEK, WYNIKI FINANSOWE I PODZIAŁ ZYSKU\n";
            
            $content .= "--- KAPITAŁ ZAKŁADOWY ---\n";
            $content .= "Wysokość kapitału zakładowego Spółki wynosi: 300 214 200,00 zł.\n";
            
            $content .= "--- WYNIKI FINANSOWE (ZYSK NETTO) ---\n";
            $content .= "- Rok 2023: 6 045 304,89 zł\n";
            $content .= "- Rok 2022: 6 424 459,29 zł\n";
            $content .= "- Rok 2021: 7 244 821,54 zł\n";
            $content .= "- Rok 2020: 9 347 635,14 zł\n";
            $content .= "- Rok 2019: 13 263 788,72 zł\n";

            $content .= "--- PRZEZNACZENIE ZYSKU (CO ZROBIONO Z PIENIĘDZMI?) ---\n";
            $content .= "Decyzjami Zgromadzenia Wspólników zysk został rozdysponowany następująco:\n";
            $content .= "- Za rok 2023: W całości na pokrycie strat z lat ubiegłych.\n";
            $content .= "- Za rok 2022: W całości na pokrycie strat z lat ubiegłych.\n";
            $content .= "- Za rok 2021: W całości na pokrycie strat z lat ubiegłych.\n";
            $content .= "- Za rok 2020: W całości na kapitał zapasowy.\n";
            $content .= "- Za rok 2019: W całości na kapitał zapasowy.\n";
            
            $content .= "--- WARTOŚĆ MAJĄTKU TRWAŁEGO (Stan na 31.12.2023 r.) ---\n";
            $content .= "Majątek OGÓŁEM: Wartość Brutto: 1 474 498 183,84 zł | Wartość Netto: 627 423 606,23 zł.\n";
            $content .= "Struktura majątku (Szczegóły):\n";
            $content .= "1. Wartości niematerialne i prawne: Brutto 25,6 mln zł.\n";
            $content .= "2. Środki trwałe (RAZEM): Brutto 1,446 mld zł. W tym główne składniki:\n";
            $content .= "   - Obiekty inżynierii lądowej i wodnej: ~1,05 mld zł (brutto).\n";
            $content .= "   - Budynki i lokale: ~131,7 mln zł (brutto).\n";
            $content .= "   - Urządzenia techniczne i maszyny: ~131,5 mln zł (brutto).\n";
            $content .= "   - Grunty: ~37,8 mln zł (brutto).\n";
            $content .= "3. Niskocenne składniki rzeczowe: Brutto 2,8 mln zł.\n";
        }
        
        // STOPKA - Zawsze dodajemy poprawny adres, aby model nie halucynował innej firmy
        $content .= "\n---\nDANE FIRMY: PEWIK GDYNIA, ul. Witomińska 21, 81-311 Gdynia.\nTELEFON: +48 58 66 87 311 (czynny 7:00-15:00). EMAIL: bok@pewik.gdynia.pl";

        return $content;
    }

    private function contains_any($haystack, $needles) {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) return true;
        }
        return false;
    }

/**
     * ZAPYTANIE DO ORACLE (Wersja Zbalansowana: Naturalny język + Konkret)
     */
    private function call_cohere_model($user_message, $knowledge_context) {
    
    // PREAMBUŁA - ZBALANSOWANA
    $system_preamble = "Jesteś pomocnym asystentem PEWIK Gdynia.
ZASADY KOMUNIKACJI:
1. Odpowiadaj na pytania PEŁNYMI ZDANIAMI. Unikaj odpowiedzi jednowyrazowych.
2. Bazuj TYLKO na dostarczonej WIEDZY. Jeśli czegoś nie wiesz, napisz to wprost.
3. NIE zmyślaj linków URL.
4. Bądź uprzejmy i rzeczowy.
5. WAŻNE: Nie kończ każdej wypowiedzi formułką 'skontaktuj się z BOK'. Odsyłaj do kontaktu tylko wtedy, gdy problem tego wymaga (np. awaria, skomplikowana sprawa).

WIEDZA:
$knowledge_context";

    $body = array(
        'compartmentId' => $this->compartment_id,
        'servingMode' => array(
            'servingType' => 'ON_DEMAND',
            'modelId' => $this->model_id
        ),
        'chatRequest' => array(
            'apiFormat' => 'COHERE',
            'message' => $user_message,
            'preambleOverride' => $system_preamble,
            
            'maxTokens' => 600,
            
            // Temperature 0.3: Pozwala na budowanie ładnych zdań, ale nadal trzyma się faktów.
            // (Wcześniej było 0.0 - co robiło z niego robota, a 1.0 to poeta-halucynator)
            'temperature' => 0.3, 
            
            // TopP 0.70: Standardowa wartość dla naturalnej rozmowy.
            'topP' => 0.70,
            
            'frequencyPenalty' => 0.0,
            'presencePenalty' => 0.0
        )
    );

        $body_json = json_encode($body);

        // Podpisanie requestu
        $headers = $this->signer->sign_request('POST', $this->inference_endpoint, array(), $body_json);
        $wp_headers = $this->format_headers_for_wp($headers);
        $wp_headers['content-type'] = 'application/json';

        // Wysyłka
        $response = wp_remote_post($this->inference_endpoint, array(
            'headers' => $wp_headers,
            'body' => $body_json,
            'timeout' => 120,
            'httpversion' => '1.1',
            'sslverify' => true
        ));

        if (is_wp_error($response)) {
            throw new Exception('WP Error: ' . $response->get_error_message());
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('OCI API Error Body: ' . $response_body);
            throw new Exception('Błąd API Oracle (Kod ' . $response_code . '): ' . $response_body);
        }

        $data = json_decode($response_body, true);

        if (isset($data['chatResponse']['text'])) {
            return $data['chatResponse']['text'];
        }
        
        throw new Exception("Otrzymano pustą odpowiedź od modelu.");
    }

    private function build_response($message, $session_id, $start_time, $error = false) {
        $response_time = microtime(true) - $start_time;
        
        // Jeśli nie ma session_id (pierwsze zapytanie), generujemy fake ID
        if (empty($session_id)) {
            $session_id = 'genai_' . uniqid();
        }

        return array(
            'error' => $error,
            'message' => $message,
            'sessionId' => $session_id,
            'messageId' => 0, // Placeholder - zostanie nadpisany prawdziwym ID z bazy
            'responseTime' => $response_time, // Dodajemy czas odpowiedzi dla bazy danych
            'hasTrace' => false,
            'hasCitations' => false
        );
    }
    
    private function format_headers_for_wp($headers) {
        $wp_headers = array();
        foreach ($headers as $key => $value) {
            // Zamiana nagłówków na małe litery dla spójności
            $wp_headers[strtolower($key)] = $value;
        }
        return $wp_headers;
    }
    
    public function create_session() {
        return 'genai_' . uniqid();
    }
}

// WYMUSZENIE TIMEOUTÓW (Dla home.pl/nazwa.pl)
add_filter('http_request_args', 'pewik_force_oracle_timeout_final', 999, 2);
add_action('http_api_curl', 'pewik_configure_curl_final', 999);

function pewik_force_oracle_timeout_final($r, $url) {
    if (strpos($url, 'oraclecloud.com') !== false) {
        $r['timeout'] = 120;
    }
    return $r;
}

function pewik_configure_curl_final($handle) {
    curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($handle, CURLOPT_TIMEOUT, 120);
}