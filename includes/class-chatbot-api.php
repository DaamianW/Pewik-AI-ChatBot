<?php
/**
 * Klasa do komunikacji z OCI Generative AI (Model Cohere Command R+)
 * Wersja: 2.2 - SŁOWNIK SYNONIMÓW POTOCZNYCH
 * 
 * ZMIANY W TEJ WERSJI (2.2):
 * 1. Dodano $customer_synonyms - słownik synonimów potocznych używanych przez klientów
 * 2. Dodano normalize_user_message() - normalizacja języka potocznego na formalny
 * 3. Dodano get_synonyms_context() - kontekst synonimów dla modelu AI
 * 4. Model rozumie teraz potoczne określenia: licznik=wodomierz, przepisanie=zawarcie umowy, itd.
 * 
 * ZMIANY W WERSJI (2.1):
 * 1. Dodano $restricted_business_topics - tablica tematów wrażliwych biznesowo
 * 2. Dodano check_restricted_business_topic() - wykrywanie tematów wymagających oficjalnych źródeł
 * 3. Dodano format_restricted_topic_response() - przyjazne odpowiedzi z linkami do źródeł
 * 
 * WYKLUCZENIA BIZNESOWE (tematy, na które asystent nie odpowiada szczegółowo):
 * - Awaryjne i planowane wyłączenia → https://pewik.gdynia.pl/awarie/
 * - Przetargi, zamówienia publiczne, rekrutacja → https://pewik.gdynia.pl/strefa-partnera/postepowania-2/ | /kariera/
 * - Dofinansowania (WFOŚiGW, UE) → https://pewik.gdynia.pl/projekty-unijne/
 * - Strategia podatkowa, dostępność, sygnaliści → https://pewik.gdynia.pl/o-nas/
 * - Szczegółowe analizy jakości wody, CSR, sponsoring → https://pewik.gdynia.pl/strefa-mieszkanca/jakosc-wody/
 * - Aktualne inwestycje → https://pewik.gdynia.pl/strefa-mieszkanca/inwestycje/
 * - Szczegółowe koszty/wyceny usług → https://pewik.gdynia.pl/strefa-klienta/ceny-i-taryfy/
 * - RODO / Polityka ochrony danych → https://pewik.gdynia.pl/rodo/
 * 
 * POPRZEDNIE ZMIANY (2.0):
 * 1. Dodano is_out_of_scope() - wykrywanie tematów POZA kompetencjami PEWIK
 * 2. Dodano get_out_of_scope_response() - inteligentne odpowiedzi z przekierowaniem
 * 3. Ulepszone matchowanie w RAG - wykluczanie "ciepłej wody" z diagnostyki awarii
 * 4. Rozbudowana preambuła z jasnym zakresem działalności PEWIK
 * 5. Dodano sekcję "CZEGO NIE ROBIMY" do kontekstu wiedzy
 */

if (!defined('ABSPATH')) exit;

class PEWIK_Chatbot_API {
    private $signer;
    private $inference_endpoint;
    private $compartment_id;
    private $model_id;
    
    // PROTOKÓŁ POWITANIA
    const MANDATORY_GREETING = "Cześć! W czym mogę pomóc? Jestem wirtualnym asystentem PEWIK Gdynia. Pomagam w sprawach związanych z **wodą** (zimną) i **kanalizacją**. Mogę pomóc Ci znaleźć formularze, informacje o awariach, cenniki i wiele więcej.";

    // =====================================================
    // DEFINICJE ZAKRESU DZIAŁALNOŚCI (OUT OF SCOPE)
    // =====================================================
    
    /**
     * Tematy POZA kompetencjami PEWIK
     * Klucz = kategoria, wartość = array ze słowami kluczowymi i odpowiedzią
     * UWAGA: Nie podajemy konkretnych nazw firm ani numerów telefonów (mogą się zmienić)
     */
    private $out_of_scope_topics = array(
        'ciepla_woda' => array(
            'keywords' => ['ciepła woda', 'ciepłą wodę', 'ciepłej wody', 'gorąca woda', 'gorącej wody', 'gorącą wodę', 'podgrzewanie wody', 'bojler', 'c.w.u', 'cwu'],
            'response' => "PEWIK Gdynia **nie zajmuje się dostarczaniem ciepłej wody**. Dostarczamy wyłącznie wodę zimną (wodociągi) i odbieramy ścieki (kanalizacja).\n\n**Gdzie zgłosić problem z ciepłą wodą?**\n- **W bloku/mieszkaniu**: Skontaktuj się z **administratorem budynku**, **spółdzielnią** lub **wspólnotą mieszkaniową**\n- **W domu jednorodzinnym**: Problem dotyczy Twojej instalacji wewnętrznej – wezwij **hydraulika** lub sprawdź swoje urządzenie grzewcze (piec, bojler)\n- **Ciepło sieciowe**: Jeśli korzystasz z miejskiej sieci ciepłowniczej, skontaktuj się z **dostawcą ciepła** w Twoim rejonie"
        ),
        'ogrzewanie' => array(
            'keywords' => ['ogrzewani', 'kaloryfer', 'grzejnik', 'piec', 'centralne ogrzewanie', 'c.o.', 'ciepło', 'zimno w mieszkaniu', 'nie grzeje', 'nie działają kaloryfer', 'nie działa kaloryfer', 'nie grzeją', 'zimne kaloryfer', 'zimne grzejnik'],
            'response' => "PEWIK Gdynia **nie zajmuje się ogrzewaniem ani ciepłem**. Dostarczamy wyłącznie wodę zimną i odbieramy ścieki.\n\n**Gdzie zgłosić problem z ogrzewaniem?**\n- **Ciepło sieciowe**: Skontaktuj się z **dostawcą ciepła** w Twoim rejonie\n- **Ogrzewanie w bloku**: **Administrator budynku**, **spółdzielnia** lub **wspólnota mieszkaniowa**\n- **Własny piec/kocioł**: Serwis Twojego urządzenia grzewczego"
        ),
        'gaz' => array(
            'keywords' => ['gaz', 'gazowy', 'gazowa', 'kuchenka gazowa', 'piec gazowy', 'wyciek gazu', 'zapach gazu', 'butla'],
            'response' => "PEWIK Gdynia **nie zajmuje się dostawą gazu**. Dostarczamy wyłącznie wodę zimną i odbieramy ścieki.\n\n**Sprawy gazowe:**\n- **Awaria/wyciek gazu**: Zadzwoń na **numer alarmowy pogotowia gazowego** (natychmiast!)\n- **Dostawy gazu**: Skontaktuj się z **operatorem sieci gazowej** lub **Twoim dostawcą gazu**\n- **Urządzenia gazowe**: Autoryzowany serwis producenta"
        ),
        'prad' => array(
            'keywords' => ['prąd', 'prądu', 'elektryczność', 'energia elektryczna', 'awaria prądu', 'brak prądu', 'licznik prądu', 'blackout'],
            'response' => "PEWIK Gdynia **nie zajmuje się dostawą energii elektrycznej**. Dostarczamy wyłącznie wodę zimną i odbieramy ścieki.\n\n**Sprawy elektryczne:**\n- **Awaria prądu**: Skontaktuj się z **operatorem sieci energetycznej** w Twoim rejonie\n- **Rozliczenia za prąd**: Skontaktuj się z **Twoim sprzedawcą energii**"
        ),
        'smieci' => array(
            'keywords' => ['śmieci', 'odpady', 'wywóz śmieci', 'segregacja', 'kontener', 'kosz na śmieci', 'recykling', 'śmieciarka', 'odpady komunalne'],
            'response' => "PEWIK Gdynia **nie zajmuje się wywozem odpadów**. Dostarczamy wyłącznie wodę zimną i odbieramy ścieki (płynne, przez kanalizację).\n\n**Sprawy odpadów komunalnych:**\n- Skontaktuj się z **Urzędem Miasta** lub **gminą** właściwą dla Twojego miejsca zamieszkania\n- Informacje o harmonogramach wywozu i segregacji znajdziesz na stronie internetowej Twojego urzędu"
        ),
        'internet_tv' => array(
            'keywords' => ['internet', 'telewizja', 'kablówka', 'wifi', 'router', 'światłowód', 'tv'],
            'response' => "PEWIK Gdynia **nie zajmuje się usługami telekomunikacyjnymi**. Dostarczamy wyłącznie wodę zimną i odbieramy ścieki.\n\n**Sprawy internetu/TV:**\nSkontaktuj się bezpośrednio z **Twoim dostawcą usług internetowych lub telewizyjnych**."
        )
    );

    /**
     * Tematy WRAŻLIWE BIZNESOWO - asystent nie powinien udzielać szczegółowych informacji
     * Mogą wprowadzić użytkownika w błąd lub wymagają aktualnych danych ze źródeł oficjalnych
     * 
     * LISTA WYKLUCZEŃ:
     * 1. Awaryjne i planowane wyłączenia (w tym awarie)
     * 2. Informacje o przetargach, zamówieniach publicznych i rekrutacji
     * 3. Dane o pozyskanych dofinansowaniach (WFOŚiGW, UE)
     * 4. Szczegóły strategii podatkowej, deklaracji dostępności i zgłaszania naruszeń prawa
     * 5. Szczegółowe analizy jakości wody, odpowiedzialności społecznej, sponsoringu
     * 6. Informacje o aktualnie prowadzonych inwestycjach
     * 7. Szczegółowe koszty świadczonych usług (wyceny/kalkulacje)
     * 8. Szczegółowe informacje dotyczące Polityki Ochrony Danych Osobowych (RODO)
     */
    private $restricted_business_topics = array(
        'awarie_wylaczenia' => array(
            'keywords' => ['awaria planowana', 'planowane wyłączenie', 'harmonogram wyłączeń', 'kiedy włączą', 'kiedy naprawią', 'jak długo potrwa', 'status awarii', 'ile potrwa naprawa', 'lista awarii', 'mapa awarii', 'gdzie jest awaria', 'aktualne awarie', 'bieżące awarie'],
            'title' => 'Awaryjne i planowane wyłączenia',
            'link' => 'https://pewik.gdynia.pl/awarie/',
            'link_text' => 'Awarie i wyłączenia'
        ),
        'przetargi_rekrutacja' => array(
            'keywords' => ['przetarg', 'zamówienie publiczne', 'oferta przetarg', 'konkurs ofert', 'postępowanie przetargowe', 'rekrutacja', 'praca w pewik', 'oferty pracy', 'zatrudnienie w pewik', 'nabór pracowników', 'wolne stanowisko', 'kariera w pewik', 'szukam pracy'],
            'title' => 'Przetargi, zamówienia publiczne i rekrutacja',
            'link' => 'https://pewik.gdynia.pl/strefa-partnera/postepowania-2/',
            'link_text' => 'Postępowania i przetargi',
            'link2' => 'https://pewik.gdynia.pl/kariera/',
            'link2_text' => 'Kariera w PEWIK'
        ),
        'dofinansowania' => array(
            'keywords' => ['dofinansowanie', 'dotacja', 'fundusze unijne', 'fundusze europejskie', 'wfośigw', 'fundusz ochrony środowiska', 'środki unijne', 'projekt unijny', 'projekty ue', 'dotacje ue', 'ile dostaliście', 'skąd pieniądze'],
            'title' => 'Dofinansowania i projekty UE',
            'link' => 'https://pewik.gdynia.pl/projekty-unijne/',
            'link_text' => 'Projekty unijne'
        ),
        'strategia_prawo' => array(
            'keywords' => ['strategia podatkowa', 'deklaracja dostępności', 'dostępność strony', 'dostępność cyfrowa', 'wcag', 'sygnalista', 'zgłoszenie naruszenia', 'naruszenie prawa', 'whistleblowing', 'polityka podatkowa', 'nieprawidłowości w firmie'],
            'title' => 'Strategia podatkowa, dostępność i zgłaszanie naruszeń',
            'link' => 'https://pewik.gdynia.pl/o-nas/',
            'link_text' => 'O nas'
        ),
        'csr_sponsoring' => array(
            'keywords' => ['sponsoring', 'sponsorujecie', 'wspieracie', 'odpowiedzialność społeczna', 'csr', 'działalność charytatywna', 'darowizna', 'wspieranie'],
            'title' => 'Działalność społeczna i sponsoring',
            'link' => 'https://pewik.gdynia.pl/o-nas/',
            'link_text' => 'O nas'
        ),
        'incydent_jakosc_wody' => array(
            'keywords' => [
                // Konkretne zanieczyszczenia (krótkie rdzenie dla odmian)
                'bakterie', 'bakteria', 'e.coli', 'ecoli', 'e-coli', 'escherichia', 
                'skażen', 'skażon',  // skażenie, skażona, skażonej, skażony
                'zanieczyszcz',      // zanieczyszczenie, zanieczyszczona, zanieczyszczonej
                // Odkażanie i chlorowanie
                'odkażan', 'odkazan', 'dezynfekc', 'chlorowan', 'chlor w wodzie', 'za dużo chloru', 'smakuje chlorem',
                'jak długo odkażan', 'kiedy skończy się odkażan', 'ile trwa odkażan',
                // Instrukcje kryzysowe
                'zakaz picia', 'nie pić', 'przegotować', 'gotować wodę', 'przegotowywać', 'nie nadaje się do picia',
                // Pytania o czas trwania incydentu
                'jak długo potrwa', 'kiedy będzie zdatna', 'kiedy koniec', 'kiedy można pić', 'ile to potrwa',
                'jak długo może trwać', 'ile jeszcze', 'kiedy wróci do normy', 'kiedy normalna',
                // Odniesienia do komunikatów
                'informacja na stronie', 'komunikat o wodzie', 'alert', 'ostrzeżenie o wodzie', 'aktualizacja statusu',
                // Pytania o bieżącą sytuację (bardziej specyficzne - związane z wodą, nie licznikiem)
                'sytuacja z wodą', 'jaka sytuacja z wodą', 'co z wodą', 'aktualny stan wody', 'czy można pić', 'można pić wodę',
                'czy można się kąpać', 'można się kąpać', 'czy można się myć', 'można normalnie', 'czy jest bezpieczna',
                'czy woda jest ok', 'czy woda jest dobra', 'co się dzieje z wodą', 'problem z wodą w',
                'bieżący stan', 'bieżąca sytuacja', 'aktualna sytuacja', 'aktualny status',
                'zdatna do użytkowania', 'zdatna do użytku', 'normalnego użytkowania',
                // Odniesienia do komunikatów/informacji wydanych przez PEWIK
                'wydali informację', 'wydaliście informację', 'informacja o wodzie', 'komunikat dotyczący',
                'wyłączonych z pitnej', 'wyłączon'
            ],
            'title' => 'Bieżące zdarzenia dotyczące jakości wody',
            'link' => 'https://pewik.gdynia.pl/o-nas/aktualnosci/',
            'link_text' => 'Aktualności PEWIK'
        ),
        'weryfikacja_prac' => array(
            'keywords' => [
                // Weryfikacja autentyczności
                'czy ta informacja jest prawdziwa', 'czy to prawda', 'czy to oszustwo', 'czy to fake',
                'zweryfikować', 'potwierdzić', 'sprawdzić czy', 'autentyczn',
                // Planowane wizyty/prace
                'dostałem informację', 'dostałam informację', 'otrzymałem sms', 'otrzymałam sms',
                'będzie wymieniany', 'będzie wymiana', 'przyjdzie monterr', 'przyjdzie pracownik',
                'wizyta montera', 'wizyta pracownika', 'umówiona wizyta', 'termin wizyty',
                'planowana wymiana', 'planowane prace', 'zapowiedział wizytę',
                // Podejrzane kontakty
                'dzwonił ktoś', 'zadzwonił ktoś', 'nieznany numer', 'podejrzany telefon', 'podejrzany sms'
            ],
            'title' => 'Weryfikacja planowanych prac i wizyt',
            'link' => 'mailto:bok@pewik.gdynia.pl',
            'link_text' => 'bok@pewik.gdynia.pl'
        ),
        'inwestycje_aktualne' => array(
            'keywords' => ['aktualne inwestycje', 'bieżące inwestycje', 'co budujecie', 'gdzie budujecie', 'kiedy skończycie budowę', 'harmonogram prac budowlanych', 'etap budowy', 'postęp prac', 'termin zakończenia inwestycji', 'plan inwestycyjny', 'jakie macie inwestycje'],
            'title' => 'Aktualne inwestycje',
            'link' => 'https://pewik.gdynia.pl/strefa-mieszkanca/inwestycje/',
            'link_text' => 'Inwestycje'
        ),
        'koszty_wyceny' => array(
            'keywords' => ['wycena', 'wycenę', 'kalkulacja', 'kalkulację', 'ile kosztuje przyłącze', 'koszt przyłącza', 'koszt przyłączenia', 'cena za metr', 'kosztorys', 'ile zapłacę za przyłącze', 'wylicz koszt', 'policz koszt', 'indywidualna wycena', 'szczegółowy koszt', 'oszacuj koszt', 'podaj cenę'],
            'title' => 'Szczegółowe koszty i wyceny usług',
            'link' => 'https://pewik.gdynia.pl/strefa-klienta/ceny-i-taryfy/',
            'link_text' => 'Ceny i taryfy'
        ),
        'rodo' => array(
            'keywords' => ['rodo ', ' rodo', 'polityka prywatności', 'przetwarzacie moje dane', 'jakie dane przetwarzacie', 'iod', 'inspektor ochrony danych', 'gdpr', 'prawo do bycia zapomnianym', 'usunięcie danych', 'cofnięcie zgody na przetwarzanie', 'kto ma dostęp do danych', 'ochrona danych osobowych', 'przepisy rodo', 'zgodnie z rodo'],
            'title' => 'Polityka Ochrony Danych Osobowych (RODO)',
            'link' => 'https://pewik.gdynia.pl/rodo/',
            'link_text' => 'RODO'
        )
    );

    /**
     * SŁOWNIK SYNONIMÓW POTOCZNYCH
     * Mapowanie potocznych/nieformalnych określeń używanych przez klientów
     * na terminy formalne rozumiane przez system
     * 
     * Format: 'termin_formalny' => ['synonim1', 'synonim2', ...]
     */
    private $customer_synonyms = array(
        // Wodomierz i pomiary
        'wodomierz' => ['licznik', 'liczydło', 'miernik', 'zegar', 'obiekt', 'licznik wody'],
        'wskazanie wodomierza' => ['stan', 'stan licznika', 'zużycie', 'odczyt', 'ile nabił', 'ile pokazuje'],
        
        // Osoby i podmioty
        'usługobiorca' => ['nabywca', 'właściciel nieruchomości', 'mieszkaniec', 'lokator', 'klient', 'odbiorca'],
        
        // Punkty i okresy rozliczeniowe
        'punkt rozliczeniowy' => ['punkt sieci', 'punkt pomiarowy', 'punkt obrachunkowy', 'nr punktu', 'numer punktu'],
        'okres obrachunkowy' => ['cykl rozliczeniowy', 'okres rozliczeniowy', 'cykl', 'okres'],
        'kod usługobiorcy' => ['kod nabywcy', 'kod klienta', 'numer klienta'],
        
        // Usługi
        'usługa zaopatrzenia w wodę' => ['produkt woda', 'dostawa wody', 'woda z sieci'],
        'usługa odprowadzenia ścieków' => ['produkt ścieki', 'odbiór ścieków', 'kanalizacja'],
        
        // Umowy
        'zawarcie umowy' => ['przepisanie umowy', 'przepisanie licznika', 'zmiana usługobiorcy', 'cesja umowy', 'przepisać', 'przenieść umowę'],
        
        // Warunki i dokumenty
        'warunki przyłączenia' => ['warunki techniczne', 'warunki przyłącz', 'warunki podłączenia', 'tu', 'techniczne'],
        'formularz wniosku' => ['druk', 'wniosek', 'dokument', 'papier', 'pismo'],
        
        // e-BOK
        'e-bok' => ['ebok', 'e-bok', 'eBOK', 'E-BOK', 'EBOK', 'serwis e-bok', 'aplikacja e-bok', 'portal klienta', 'konto online'],
        
        // Przyłącza
        'przyłącze' => ['przyłącz', 'przykanalik', 'sięgacz', 'podłączenie'],
        'przyłącze wodociągowe' => ['przyłącze wody', 'przyłącze wodne', 'instalacja wodna', 'rura od wody', 'woda do domu'],
        'przyłącze kanalizacyjne' => ['przyłącze ściekowe', 'przyłącze ścieków', 'przyłącze sanitarne', 'przykanalik', 'sięgacz', 'odgałęzienie', 'rura od ścieków', 'kanalizacja do domu'],
        
        // Sieci
        'sieć wodociągowa' => ['sieć wodna', 'wodociąg', 'rura miejska', 'magistrala', 'główna rura'],
        'sieć kanalizacyjna' => ['kanalizacja miejska', 'kanał', 'kolektor', 'główny kanał'],
        
        // Ścieki
        'ścieki bytowe' => ['ścieki sanitarne', 'ścieki domowe', 'ścieki z domu'],
        
        // Studzienki
        'studzienka kanalizacyjna' => ['studnia kanalizacyjna', 'studzienka', 'właz', 'kratka'],
        'studzienka wodomierzowa' => ['studnia wodomierzowa', 'komora wodomierzowa', 'skrzynka z licznikiem'],
        
        // Inne
        'teren budowy' => ['plac budowy', 'budowa'],
        'awaria' => ['usterka', 'uszkodzenie', 'defekt', 'problem', 'nie działa'],
        'faktura' => ['rachunek', 'rozliczenie', 'płatność', 'należność'],
        'taryfa' => ['cennik', 'ceny', 'stawki', 'opłaty']
    );

    public function __construct() {
        // Inicjalizacja Signera
        if (!class_exists('PEWIK_OCI_Request_Signer')) {
            error_log('Krytyczny błąd: Brak klasy PEWIK_OCI_Request_Signer');
            return;
        }
        $this->signer = new PEWIK_OCI_Request_Signer();
        
        // DANE OCI
        $this->compartment_id = "ocid1.tenancy.oc1..aaaaaaaahakj6sqsxfouv57essllobaj4euh6e24mxa2ab7i6ktjuju4fxiq"; 
        $this->model_id = 'ocid1.generativeaimodel.oc1.eu-frankfurt-1.amaaaaaask7dceyabdu6rjjmg75pixtecqvjen4x4st4mhs2a4zzfx5cgkmq';
        $this->inference_endpoint = 'https://inference.generativeai.eu-frankfurt-1.oci.oraclecloud.com/20231130/actions/chat';
    }

    /**
     * Główna metoda obsługi wiadomości
     */
    public function send_message($user_message, $session_id, $context = null, $chat_history = array()) {
        $start_time = microtime(true);
        
        // ---------------------------------------------------------
        // 1. HARD RULES - PRIORYTET NAJWYŻSZY
        // ---------------------------------------------------------
        
        // 1A. PYTANIA O STATUS SPRAWY / OCZEKIWANIE NA ODPOWIEDŹ
        // Gdy klient pyta "kiedy dostanę odpowiedź", "czekam na odpowiedź", "jaki jest status" itp.
        $status_check = $this->check_waiting_for_response($user_message);
        if ($status_check !== false) {
            return $this->build_response($status_check, $session_id, $start_time);
        }

        // 1B. Sytuacje awaryjne (PEWIK)
        if ($this->is_emergency($user_message)) {
            return $this->build_response(
                "🛑 **STOP! To jest sprawa wymagająca natychmiastowej interwencji.**\n\nW przypadku awarii wodno-kanalizacyjnej natychmiast zadzwoń pod bezpłatny numer alarmowy **994**!",
                $session_id, 
                $start_time
            );
        }

        // 1C. RESTRICTED BUSINESS TOPICS - Tematy wrażliwe biznesowo
        // Wymagające aktualnych danych z oficjalnych źródeł
        // WAŻNE: Musi być PRZED is_sensitive_data() żeby matchować pytania o RODO
        $restricted_check = $this->check_restricted_business_topic($user_message);
        if ($restricted_check !== false) {
            return $this->build_response($restricted_check, $session_id, $start_time);
        }

        // 1D. OUT OF SCOPE - Tematy POZA kompetencjami PEWIK
        // WAŻNE: Musi być PRZED is_sensitive_data() żeby matchować kaloryfery, gaz, prąd itp.
        $out_of_scope_check = $this->check_out_of_scope($user_message);
        if ($out_of_scope_check !== false) {
            return $this->build_response($out_of_scope_check, $session_id, $start_time);
        }

        // 1E. Dane osobowe - INTELIGENTNA OBSŁUGA
        // Zamiast blokować, rozpoznaj temat i pomóż klientowi
        if ($this->is_sensitive_data($user_message)) {
            $helpful_response = $this->get_sensitive_data_response($user_message);
            return $this->build_response($helpful_response, $session_id, $start_time);
        }

        // 1F. Frustracja / Zdenerwowanie użytkownika - DEESKALACJA
        $frustration_check = $this->check_user_frustration($user_message);
        if ($frustration_check !== false) {
            return $this->build_response($frustration_check, $session_id, $start_time);
        }

        // 1G. Powitania
        if ($this->is_greeting($user_message)) {
            return $this->build_response(self::MANDATORY_GREETING, $session_id, $start_time);
        }

        // ---------------------------------------------------------
        // 2. DOBÓR WIEDZY (Local RAG)
        // ---------------------------------------------------------
        $knowledge_context = $this->get_knowledge_context($user_message, $context);

        // ---------------------------------------------------------
        // 4. ZAPYTANIE DO ORACLE (z historią konwersacji)
        // ---------------------------------------------------------
        try {
            $bot_response = $this->call_cohere_model($user_message, $knowledge_context, $chat_history);
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

    // =====================================================
    // METODY HARD RULES
    // =====================================================

    /**
     * Sprawdź czy użytkownik pyta o status sprawy / czeka na odpowiedź
     * Wykrywa pytania typu: "kiedy dostanę odpowiedź", "czekam na odpowiedź", "jaki jest status mojej sprawy"
     * 
     * @param string $text Wiadomość użytkownika
     * @return string|false Odpowiedź lub false jeśli nie dotyczy
     */
    private function check_waiting_for_response($text) {
        $text_lower = mb_strtolower($text);
        
        // Słowa kluczowe wskazujące na oczekiwanie na odpowiedź / status sprawy
        $waiting_keywords = [
            // Oczekiwanie na odpowiedź
            'kiedy dostanę odpowiedź',
            'kiedy otrzymam odpowiedź',
            'czekam na odpowiedź',
            'oczekuję na odpowiedź',
            'nie dostałem odpowiedzi',
            'nie otrzymałem odpowiedzi',
            'brak odpowiedzi',
            'ile czekać na odpowiedź',
            'jak długo czekać',
            'kiedy odezwiecie',
            'kiedy odpowiecie',
            'kiedy się odezwiecie',
            'dlaczego nie odpowiadacie',
            'czemu nie odpowiadacie',
            'nikt nie odpowiada',
            'nikt mi nie odpowiedział',
            
            // Status sprawy
            'jaki jest status',
            'status mojej sprawy',
            'status zgłoszenia',
            'co z moją sprawą',
            'co z moim zgłoszeniem',
            'co z moim wnioskiem',
            'na jakim etapie',
            'etap realizacji',
            'etap sprawy',
            
            // Wcześniejszy kontakt
            'pisałem wcześniej',
            'pisałam wcześniej',
            'wysłałem wcześniej',
            'wysłałam wcześniej',
            'kontaktowałem się',
            'kontaktowałam się',
            'zgłaszałem',
            'zgłaszałam',
            'składałem wniosek',
            'składałam wniosek',
            'złożyłem wniosek',
            'złożyłam wniosek'
        ];
        
        foreach ($waiting_keywords as $keyword) {
            if (strpos($text_lower, $keyword) !== false) {
                return $this->get_waiting_response();
            }
        }
        
        // Dodatkowa heurystyka: "kiedy" + słowa związane z odpowiedzią/kontaktem
        if (strpos($text_lower, 'kiedy') !== false) {
            $response_words = ['odpowiedź', 'odpowiecie', 'odezwiecie', 'kontakt', 'informacj'];
            foreach ($response_words as $word) {
                if (strpos($text_lower, $word) !== false) {
                    return $this->get_waiting_response();
                }
            }
        }
        
        return false;
    }

    /**
     * Odpowiedź dla osób czekających na odpowiedź / pytających o status sprawy
     */
    private function get_waiting_response() {
        $response = "Nie mam dostępu do historii korespondencji ani statusu indywidualnych spraw.\n\n";
        $response .= "**Aby sprawdzić status swojej sprawy:**\n";
        $response .= "Wyślij e-mail na adres **bok@pewik.gdynia.pl** z zapytaniem o etap realizacji. ";
        $response .= "W treści wiadomości podaj szczegóły poprzedniego zgłoszenia (datę, temat, numer sprawy jeśli posiadasz).\n\n";
        $response .= "**Kontakt bezpośredni:**\n";
        $response .= "Telefon: **+48 58 66 87 311** (pn-pt 7:00-15:00)\n\n";
        $response .= "Pracownicy BOK sprawdzą status Twojej sprawy i udzielą informacji.";
        
        return $response;
    }

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

        // Lista znanych osób z PEWIK (imiona i nazwiska w lowercase)
        $known_pewik_people = [
            'jacek kieloch', 'wiesław kujawski',  // Zarząd
            'marcin zawisza', 'anna lewandowska', 'kamila kraszkiewicz', 
            'karolina maciąg', 'łukasz galiński', 'radosław skwarło'  // Rada Nadzorcza
        ];
        
        // Frazy wskazujące na PYTANIE o osobę (nie przedstawianie się)
        $question_patterns = [
            'kim jest', 'kto to', 'kto to jest', 'czy znasz', 'znasz', 
            'powiedz mi o', 'opowiedz o', 'informacje o', 'info o',
            'prezes', 'wiceprezes', 'dyrektor', 'kierownik', 'członek', 
            'zarząd', 'rada', 'nadzorcza', 'przewodniczący'
        ];
        
        // Sprawdź czy pytanie dotyczy znanej osoby z PEWIK
        foreach ($known_pewik_people as $person) {
            if (strpos($text_lower, $person) !== false) {
                return false; // To pytanie o osobę z firmy - PRZEPUŚĆ
            }
        }
        
        // Sprawdź czy to pytanie o osobę (nie przedstawianie się)
        foreach ($question_patterns as $pattern) {
            if (strpos($text_lower, $pattern) !== false) {
                return false; // To pytanie - PRZEPUŚĆ
            }
        }

        // 1. TWARDE FRAZY - użytkownik podaje swoje dane
        $sensitive_keywords = [
            'nazywam się', 'mieszkam przy', 'mój pesel', 'nr umowy', 'numer umowy', 
            'dowód osobisty', 'moje nazwisko', 'pesel', 'seria dowodu', 'nr klienta',
            'mam na imię', 'moje imię', 'moje dane', 'jestem klientem', 'jestem właścicielem'
        ];
        
        foreach ($sensitive_keywords as $word) {
            if (strpos($text_lower, $word) !== false) return true;
        }

        // 2. Heurystyka: Samo "Imię Nazwisko" bez kontekstu = prawdopodobnie przedstawianie się
        if (mb_strlen($text) < 50) {
            $pattern = '/^[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż]+\s+[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż]+(?:-[A-ZĄĆĘŁŃÓŚŹŻ][a-ząćęłńóśźż]+)?$/u';
            
            if (preg_match($pattern, trim($text))) {
                // Bezpieczne słowa - jeśli są, to nie jest przedstawianie się
                $safe_words = [
                    'awaria', 'woda', 'ścieki', 'gdynia', 'pewik', 'biuro', 'obsługi', 'klienta', 
                    'adres', 'ulica', 'gdzie', 'kiedy', 'jaka', 'cena', 'koszt', 'faktura', 'taryfa',
                    'kim', 'kto', 'czy', 'prezes', 'dyrektor', 'zarząd', 'rada'
                ];
                
                foreach ($safe_words as $safe) {
                    if (strpos($text_lower, $safe) !== false) return false;
                }
                
                // Sprawdź czy to nie jest znana osoba z PEWIK (pełne dopasowanie)
                $text_normalized = trim($text_lower);
                foreach ($known_pewik_people as $person) {
                    if ($text_normalized === $person) {
                        return false; // To imię i nazwisko osoby z firmy - PRZEPUŚĆ
                    }
                }
                
                return true; // Samo imię i nazwisko bez kontekstu = BLOKUJ
            }
        }

        return false;
    }

    /**
     * Inteligentna odpowiedź na wiadomości zawierające dane osobowe
     * Zamiast tylko blokować - rozpoznaje temat i podaje konkretną instrukcję
     */
    private function get_sensitive_data_response($text) {
        $text_lower = mb_strtolower($text);
        
        // Wspólny nagłówek ostrzegawczy
        $warning = "⚠️ **Uwaga:** Nie podawaj mi swoich danych osobowych (imię, nazwisko, adres, PESEL, numery faktur). Jestem tylko wyszukiwarką informacji i nie przetwarzam takich danych.\n\n";
        
        // ROZPOZNANIE TEMATU I KONKRETNA POMOC
        
        // 1. RATY / SPŁATA NALEŻNOŚCI
        if ($this->contains_any($text_lower, ['rata', 'raty', 'ratach', 'rozłoż', 'spłat', 'dług', 'należnoś', 'zaległ', 'nie zapłac', 'faktur'])) {
            return $warning . "**Jak złożyć wniosek o rozłożenie płatności na raty:**\n\n" .
                "1. Napisz **pisemną prośbę** opisującą Twoją sytuację\n" .
                "2. Wyślij ją na e-mail: **bok@pewik.gdynia.pl**\n" .
                "3. Odpowiedź otrzymasz w terminie do 14 dni\n\n" .
                "Każdy wniosek rozpatrywany jest indywidualnie.\n\n" .
                "📄 Szczegóły procedury: [Spłata należności](https://pewik.gdynia.pl/strefa-klienta/splata-naleznosci/)";
        }
        
        // 2. REKLAMACJA
        if ($this->contains_any($text_lower, ['reklamac', 'błąd', 'pomyłk', 'nieprawidłow', 'za dużo', 'źle nalicz'])) {
            return $warning . "**Jak złożyć reklamację:**\n\n" .
                "1. Pobierz **Wniosek nr 15** (Zgłoszenie reklamacji)\n" .
                "2. Wypełnij i wyślij na: **bok@pewik.gdynia.pl**\n\n" .
                "📄 Formularze: [Pobierz wniosek](https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#umowy)";
        }
        
        // 3. UMOWA / PRZEPISANIE
        if ($this->contains_any($text_lower, ['umow', 'przepis', 'właściciel', 'nowy', 'zmian', 'dane'])) {
            return $warning . "**Jak załatwić sprawę związaną z umową:**\n\n" .
                "1. Pobierz odpowiedni wniosek ze strony\n" .
                "2. Wypełnij i wyślij na: **bok@pewik.gdynia.pl**\n\n" .
                "📄 Formularze: [Wnioski dot. umów](https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#umowy)";
        }
        
        // 4. AWARIA / ZGŁOSZENIE
        if ($this->contains_any($text_lower, ['awari', 'wyciek', 'brak wody', 'nie ma wody', 'pękł', 'zalew'])) {
            return $warning . "**Zgłoszenie awarii:**\n\n" .
                "🚨 Zadzwoń na numer alarmowy: **994** (całodobowo)\n\n" .
                "Dyżurny przyjmie zgłoszenie i wyśle ekipę.";
        }
        
        // 5. WODOMIERZ
        if ($this->contains_any($text_lower, ['wodomierz', 'licznik', 'odczyt', 'wymian', 'plomb', 'legalizac', 'oplomb'])) {
            return $warning . "**Sprawy wodomierzowe (wymiana/oplombowanie):**\n\n" .
                "📋 Szczegółowa instrukcja krok po kroku: [Wymiana wodomierza](https://pewik.gdynia.pl/wymiana)\n\n" .
                "Wyślij e-mail na: **biuro@pewik.gdynia.pl** zgłaszając gotowość do oplombowania.\n\n" .
                "📄 Formularze: [Wnioski dot. wodomierzy](https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#wodomierze)";
        }
        
        // 6. DOMYŚLNA ODPOWIEDŹ (gdy nie rozpoznano tematu)
        return $warning . "**Jak mogę Ci pomóc?**\n\n" .
            "Aby załatwić sprawę w PEWIK:\n" .
            "📧 E-mail: **bok@pewik.gdynia.pl**\n" .
            "📞 Telefon: **+48 58 66 87 311** (pn-pt 7:00-15:00)\n" .
            "🏢 Osobiście: ul. Witomińska 21, Gdynia\n\n" .
            "📄 Formularze i wnioski: [Pobierz](https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/)";
    }

    private function is_greeting($text) {
        $greetings = ['cześć', 'czesc', 'cze', 'hej', 'hejka', 'witam', 'siema', 'siemanko', 'elo', 'dzień dobry', 'dzien dobry', 'start', 'halo', 'hello', 'hi'];
        $clean_text = str_replace(['!', '.', ',', '?'], '', mb_strtolower(trim($text)));
        return in_array($clean_text, $greetings);
    }

    // =====================================================
    // WYKRYWANIE FRUSTRACJI / DEESKALACJA
    // =====================================================

    /**
     * Sprawdź czy użytkownik jest sfrustrowany/zdenerwowany
     * Jeśli tak - odpowiedz empatycznie i podaj KONKRETNE dane kontaktowe
     * 
     * @param string $text Wiadomość użytkownika
     * @return string|false Empatyczna odpowiedź lub false
     */
    private function check_user_frustration($text) {
        $text_lower = mb_strtolower($text);
        
        // Poziom 1: WYSOKA FRUSTRACJA - groźby, eskalacja, media
        $high_frustration = [
            'skandal', 'telewizj', 'dzwonię do', 'zgłoszę', 'skarga', 'sąd', 'prawnik', 
            'adwokat', 'pozwę', 'media', 'gazeta', 'facebook', 'napiszę o was',
            'dyrektor', 'nazwisko dyrektora', 'kto tu rządzi', 'kto jest szefem',
            'kompromitacja', 'wstyd', 'hańba', 'oszuści', 'złodzieje', 'banda'
        ];
        
        // Poziom 2: ŚREDNIA FRUSTRACJA - niezadowolenie, złość
        $medium_frustration = [
            'nie pomaga', 'bezużyteczn', 'do niczego', 'nie działa', 'głupi bot',
            'beznadziejn', 'fataln', 'żenada', 'kpina', 'absurd', 'nonsens',
            'nie rozumiesz', 'powtarzam', 'ile razy', 'znowu to samo', 'w kółko',
            'nikt mi nie pomoże', 'olali mnie', 'ignorujecie', 'macie gdzieś'
        ];
        
        // Poziom 3: LEKKA FRUSTRACJA - zniecierpliwienie
        $light_frustration = [
            'zdenerwował', 'wkurz', 'wnerw', 'irytuj', 'frustruj', 'męcz',
            'nie chcecie pomóc', 'utrudniacie', 'komplikujecie'
        ];
        
        // Sprawdź wysoki poziom frustracji
        foreach ($high_frustration as $word) {
            if (strpos($text_lower, $word) !== false) {
                return $this->get_deescalation_response('high');
            }
        }
        
        // Sprawdź średni poziom frustracji
        foreach ($medium_frustration as $word) {
            if (strpos($text_lower, $word) !== false) {
                return $this->get_deescalation_response('medium');
            }
        }
        
        // Sprawdź lekki poziom frustracji
        foreach ($light_frustration as $word) {
            if (strpos($text_lower, $word) !== false) {
                return $this->get_deescalation_response('light');
            }
        }
        
        // Dodatkowa heurystyka: dużo wykrzykników lub caps lock
        $exclamation_count = substr_count($text, '!');
        $caps_ratio = strlen(preg_replace('/[^A-ZĄĆĘŁŃÓŚŹŻ]/u', '', $text)) / max(strlen($text), 1);
        
        if ($exclamation_count >= 3 || $caps_ratio > 0.5) {
            return $this->get_deescalation_response('medium');
        }
        
        return false;
    }

    /**
     * Generuj empatyczną odpowiedź deeskalacyjną
     * KLUCZOWE: Zawsze podaj KONKRETNE dane kontaktowe, nie odsyłaj "na stronę"
     */
    private function get_deescalation_response($level) {
        // Zawsze dołączamy pełne dane kontaktowe
        $contact_info = "\n\n**Oto dane kontaktowe, żebyś mógł/mogła porozmawiać z pracownikiem:**\n\n";
        $contact_info .= "📞 **Telefon:** +48 58 66 87 311 (poniedziałek-piątek, 7:00-15:00)\n";
        $contact_info .= "📧 **E-mail:** bok@pewik.gdynia.pl\n";
        $contact_info .= "🏢 **Osobiście:** ul. Witomińska 21, Gdynia (poniedziałek-piątek, 8:00-15:00)\n";
        $contact_info .= "🚨 **Awarie całodobowo:** 994";
        
        switch ($level) {
            case 'high':
                $empathy = "Rozumiem, że ta sytuacja jest dla Ciebie bardzo frustrująca i przepraszam, że moje odpowiedzi nie były pomocne. ";
                $empathy .= "Twoja sprawa wymaga rozmowy z pracownikiem, który będzie mógł Ci realnie pomóc i wyjaśnić wszystkie wątpliwości.";
                break;
                
            case 'medium':
                $empathy = "Przykro mi, że nie udało mi się Ci pomóc tak, jak tego potrzebujesz. ";
                $empathy .= "Jestem asystentem cyfrowym i moje możliwości są ograniczone. Twoja sprawa wymaga kontaktu z pracownikiem.";
                break;
                
            case 'light':
            default:
                $empathy = "Rozumiem, że to może być frustrujące. Postaram się pomóc, ale jeśli moje odpowiedzi nie rozwiązują problemu, ";
                $empathy .= "najlepiej skontaktuj się bezpośrednio z naszym biurem.";
                break;
        }
        
        return $empathy . $contact_info;
    }

    // =====================================================
    // OUT OF SCOPE - KLUCZOWA NOWA FUNKCJONALNOŚĆ
    // =====================================================

    /**
     * Sprawdź czy temat jest POZA kompetencjami PEWIK
     * 
     * @param string $text Wiadomość użytkownika
     * @return string|false Odpowiedź out-of-scope lub false jeśli temat jest OK
     */
    private function check_out_of_scope($text) {
        $text_lower = mb_strtolower($text);
        
        // WYJĄTKI - gdy słowo kluczowe występuje w kontekście naszych usług, NIE blokuj
        // Np. "nie mam internetu" + "wodomierz/wniosek/zgłosić" = pytanie o alternatywną formę kontaktu
        $pewik_context_words = ['wodomierz', 'wod', 'kanal', 'ściek', 'faktur', 'wnios', 'zgłos', 'umow', 'przyłącz', 'licznik', 'rur', 'awari'];
        $has_pewik_context = false;
        foreach ($pewik_context_words as $context_word) {
            if (strpos($text_lower, $context_word) !== false) {
                $has_pewik_context = true;
                break;
            }
        }
        
        foreach ($this->out_of_scope_topics as $category => $data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    // Wyjątek dla "internet" - jeśli jest kontekst PEWIK, to pytanie o alternatywny kontakt
                    if ($category === 'internet_tv' && $has_pewik_context) {
                        return false; // Przepuść do normalnej obsługi
                    }
                    // Wyjątek dla "nie mam internetu" - to nie jest pytanie o usługi internetowe
                    if ($category === 'internet_tv' && strpos($text_lower, 'nie mam') !== false) {
                        return false; // Przepuść do normalnej obsługi
                    }
                    
                    // Znaleziono temat out-of-scope
                    return $this->format_out_of_scope_response($data['response'], $category);
                }
            }
        }
        
        return false;
    }

    /**
     * Formatuj odpowiedź out-of-scope z dodatkowym kontekstem
     */
    private function format_out_of_scope_response($response, $category) {
        $header = "ℹ️ **To nie jest sprawa dla PEWIK**\n\n";
        $footer = "\n\n---\n💧 Jeśli masz pytanie dotyczące **wody zimnej** lub **kanalizacji** – chętnie pomogę!";
        
        return $header . $response . $footer;
    }

    // =====================================================
    // RESTRICTED BUSINESS TOPICS - Tematy wymagające oficjalnych źródeł
    // =====================================================

    /**
     * Sprawdź czy temat wymaga przekierowania do oficjalnych źródeł
     * Tematy wrażliwe biznesowo, gdzie asystent mógłby wprowadzić w błąd
     * 
     * @param string $text Wiadomość użytkownika
     * @return string|false Odpowiedź z przekierowaniem lub false jeśli temat jest OK
     */
    private function check_restricted_business_topic($text) {
        $text_lower = mb_strtolower($text);
        
        foreach ($this->restricted_business_topics as $category => $data) {
            foreach ($data['keywords'] as $keyword) {
                if (strpos($text_lower, $keyword) !== false) {
                    return $this->format_restricted_topic_response($data, $category);
                }
            }
        }
        
        return false;
    }

    /**
     * Formatuj przyjazną odpowiedź dla tematów wymagających oficjalnych źródeł
     * Zawiera link do odpowiedniej strony BEZ danych kontaktowych BOK
     */
    private function format_restricted_topic_response($topic_data, $category = '') {
        // Specjalna odpowiedź dla incydentów jakości wody
        if ($category === 'incydent_jakosc_wody') {
            $response = "Jestem wirtualnym asystentem i **nie posiadam aktualnych informacji** o bieżących zdarzeniach, awariach, odkażaniu wody ani przewidywanym czasie ich trwania.\n\n";
            $response .= "**Aktualne informacje o stanie wody** są publikowane na bieżąco na stronie:\n";
            $response .= "🔗 [Aktualności PEWIK](https://pewik.gdynia.pl/o-nas/aktualnosci/)\n\n";
            $response .= "Tam znajdziesz wszystkie komunikaty dotyczące bieżącej sytuacji.\n\n";
            $response .= "**Pytania ogólne o jakość wody** (parametry, twardość, czy można pić na co dzień):\n";
            $response .= "🔗 [Jakość wody](https://pewik.gdynia.pl/strefa-mieszkanca/jakosc-wody/)";
            return $response;
        }

        // Specjalna odpowiedź dla weryfikacji planowanych prac/wizyt
        if ($category === 'weryfikacja_prac') {
            $response = "🔍 **Weryfikacja planowanych prac lub wizyt**\n\n";
            $response .= "Nie posiadam dostępu do harmonogramu planowanych prac ani wizyt monterów. ";
            $response .= "Nie jestem w stanie zweryfikować autentyczności otrzymanej informacji.\n\n";
            $response .= "**Aby potwierdzić lub zweryfikować informację, skontaktuj się z Biurem Obsługi Klienta:**\n";
            $response .= "📧 **bok@pewik.gdynia.pl**\n";
            $response .= "📞 **+48 58 66 87 311** (pn-pt 7:00-15:00)\n\n";
            $response .= "💡 **Wskazówka:** W wiadomości podaj szczegóły otrzymanej informacji (data, godzina, adres, numer telefonu nadawcy) – pracownicy BOK zweryfikują, czy planowana jest wizyta.\n";
            $response .= "\n---\n💧 Dbaj o bezpieczeństwo – w razie wątpliwości zawsze weryfikuj!";
            return $response;
        }
        
        // Standardowa odpowiedź dla pozostałych restricted topics
        $response = "📋 **{$topic_data['title']}**\n\n";
        
        $response .= "To pytanie wykracza poza zakres informacji, które mogę Ci rzetelnie przekazać. ";
        $response .= "Dane w tym obszarze zmieniają się dynamicznie i wymagają dostępu do aktualnych, oficjalnych źródeł.\n\n";
        
        $response .= "**Gdzie znajdziesz aktualne informacje:**\n";
        $response .= "🔗 [{$topic_data['link_text']}]({$topic_data['link']})\n";
        
        // Dodaj drugi link jeśli istnieje (np. dla przetargów + rekrutacji)
        if (isset($topic_data['link2'])) {
            $response .= "🔗 [{$topic_data['link2_text']}]({$topic_data['link2']})\n";
        }
        
        $response .= "\n---\n💧 W innych sprawach dotyczących wody i kanalizacji – chętnie pomogę!";
        
        return $response;
    }

    // =====================================================
    // SŁOWNIK SYNONIMÓW - NORMALIZACJA JĘZYKA POTOCZNEGO
    // =====================================================

    /**
     * Normalizuje wiadomość użytkownika - zamienia synonimy potoczne na terminy formalne
     * Dzięki temu system lepiej rozumie pytania zadawane nieformalnym językiem
     * 
     * @param string $text Oryginalna wiadomość użytkownika
     * @return string Znormalizowana wiadomość
     */
    private function normalize_user_message($text) {
        $text_lower = mb_strtolower($text);
        
        foreach ($this->customer_synonyms as $formal_term => $synonyms) {
            foreach ($synonyms as $synonym) {
                $synonym_lower = mb_strtolower($synonym);
                // Zamieniamy synonim na termin formalny (dla lepszego matchowania w RAG)
                if (strpos($text_lower, $synonym_lower) !== false) {
                    // Dodajemy termin formalny do tekstu (nie zastępujemy, żeby zachować kontekst)
                    $text_lower .= ' ' . $formal_term;
                }
            }
        }
        
        return $text_lower;
    }

    /**
     * Generuje kontekst synonimów do preambuły modelu
     * Informuje model AI o potocznych określeniach używanych przez klientów
     * 
     * @return string Kontekst synonimów dla preambuły
     */
    private function get_synonyms_context() {
        $context = "=== SŁOWNIK SYNONIMÓW POTOCZNYCH ===\n";
        $context .= "Klienci często używają potocznych określeń. Oto mapowanie:\n\n";
        
        $key_synonyms = array(
            'wodomierz' => 'licznik, liczydło, miernik, zegar',
            'wskazanie wodomierza' => 'stan, stan licznika, zużycie, odczyt',
            'zawarcie umowy' => 'przepisanie umowy, przepisanie licznika, cesja',
            'przyłącze' => 'przyłącz, przykanalik, sięgacz',
            'e-bok' => 'ebok, EBOK, portal klienta',
            'faktura' => 'rachunek, rozliczenie',
            'taryfa' => 'cennik, ceny, stawki'
        );
        
        foreach ($key_synonyms as $formal => $synonyms) {
            $context .= "- **$formal** = $synonyms\n";
        }
        
        $context .= "\nGdy klient użyje potocznego określenia, rozumiej je jako termin formalny.\n";
        $context .= "---\n\n";
        
        return $context;
    }

    // =====================================================
    // RAG - DOBÓR WIEDZY (ZOPTYMALIZOWANY)
    // =====================================================

    private function get_knowledge_context($message, $page_context) {
        // Normalizuj wiadomość - zamień synonimy potoczne na terminy formalne
        $msg = $this->normalize_user_message($message);
        $url = isset($page_context['pageUrl']) ? strtolower($page_context['pageUrl']) : '';
        $content = "";

        // =====================================================
        // SEKCJA 0: ZAKRES DZIAŁALNOŚCI (ZAWSZE DODAWANA)
        // =====================================================
        $content .= "=== ZAKRES DZIAŁALNOŚCI PEWIK GDYNIA ===\n";
        $content .= "PEWIK zajmuje się WYŁĄCZNIE:\n";
        $content .= "✓ Dostawą ZIMNEJ wody (wodociągi)\n";
        $content .= "✓ Odbiorem ścieków (kanalizacja sanitarna)\n";
        $content .= "✓ Budową i utrzymaniem sieci wodno-kanalizacyjnej\n\n";
        $content .= "PEWIK NIE ZAJMUJE SIĘ:\n";
        $content .= "✗ Ciepłą wodą (to administrator budynku, spółdzielnia, wspólnota lub dostawca ciepła)\n";
        $content .= "✗ Ogrzewaniem / centralnym ogrzewaniem (administrator, dostawca ciepła)\n";
        $content .= "✗ Gazem (operator sieci gazowej, pogotowie gazowe)\n";
        $content .= "✗ Prądem (operator sieci energetycznej)\n";
        $content .= "✗ Wywozem śmieci (urząd miasta/gminy)\n";
        $content .= "---\n\n";

        // =====================================================
        // SEKCJA 0B: SŁOWNIK SYNONIMÓW POTOCZNYCH
        // =====================================================
        $content .= $this->get_synonyms_context();

        // =====================================================
        // SEKCJA 1: AWARIE I DIAGNOSTYKA (ULEPSZONE MATCHOWANIE)
        // =====================================================
        // WAŻNE: Matchujemy tylko gdy NIE ma słów o ciepłej wodzie
        $is_water_issue = $this->contains_any($msg, ['awari', 'pękł', 'rura', 'rury', 'wyciek', 'leje', 'zalewa', 'brak wody', 'nie mam wody', 'sucho w kranie', 'ciśnieni', 'kran', 'spłuczk', 'hydraulik', '994', 'pogotowi', 'sąsiedzi', 'sąsiad']);
        $is_about_hot_water = $this->contains_any($msg, ['ciepł', 'gorąc', 'cieplej', 'podgrzew', 'bojler', 'cwu', 'c.w.u']);
        
        if ($is_water_issue && !$is_about_hot_water) {
            $content .= "TEMAT: DIAGNOSTYKA BRAKU WODY (ZIMNEJ) I AWARII\n";
            
            $content .= "--- KROK 1: SPRAWDŹ STRONĘ WWW (CZY TO AWARIA MASOWA?) ---\n";
            $content .= "Zanim zadzwonisz, sprawdź mapę awarii i wyłączeń: [PLANOWANE WYŁĄCZENIA I AWARIE](https://pewik.gdynia.pl/awarie/planowane-wylaczenia/).\n";
            $content .= "Jeśli Twój adres tam jest -> Trwają prace, musisz poczekać.\n";
            
            $content .= "--- KROK 2: DIAGNOZA SĄSIEDZKA (BRAK WODY ZIMNEJ) ---\n";
            $content .= "Sytuacja A: Sąsiedzi też nie mają wody -> To awaria sieciowa. Sprawdź stronę www lub zadzwoń na 994.\n";
            $content .= "Sytuacja B: Sąsiedzi MAJĄ wodę, a Ty nie -> To awaria Twojej instalacji wewnętrznej (np. zakręcony zawór, zapchany filtr). PEWIK tego nie naprawia. Skontaktuj się z Administratorem Budynku lub hydraulikiem.\n";
            
            $content .= "--- KROK 3: ZGŁASZANIE WYCIEKÓW ---\n";
            $content .= "Wyciek na ulicy/chodniku/przed licznikiem głównym -> Alarm 994 (PEWIK).\n";
            $content .= "Wyciek w domu/za licznikiem -> Hydraulik (KLIENT).\n";
            
            $content .= "--- WAŻNE KONTAKTY ---\n";
            $content .= "Dyspozytor (24h): 994 lub +48 58 66 87 311. E-mail: ed@pewik.gdynia.pl\n";
        }

        // =====================================================
        // SEKCJA 2: JAKOŚĆ WODY
        // =====================================================
        if ($this->contains_any($msg, ['jakość', 'jakości', 'tward', 'kamień', 'ph', 'skład', 'pić', 'kranówk', 'zdrow', 'bezpieczn', 'czyst', 'czysta', 'badanie', 'badań', 'analiz', 'parametr', 'norma', 'zdatna', 'pitna', 'można pić', 'smak', 'zapach', 'chlor', 'wapń', 'wapno'])) {
            $content .= "TEMAT: JAKOŚĆ WODY\n";
            $content .= "PEWIK Gdynia ZAJMUJE SIĘ jakością wody! Woda z naszej sieci jest zdatna do picia bez przegotowania.\n";
            $content .= "Parametry: Twardość: 60-500 mg/l CaCO3. pH: 6.5-9.5.\n\n";
            $content .= "GDZIE SPRAWDZIĆ JAKOŚĆ WODY:\n";
            $content .= "- Strona główna jakości wody: [Jakość Wody](https://pewik.gdynia.pl/strefa-mieszkanca/jakosc-wody/)\n";
            $content .= "- Aktualności i komunikaty: [Aktualności](https://pewik.gdynia.pl/aktualnosci/)\n";
            $content .= "- Obszary zaopatrzenia: Gdynia, Rumia, Reda, gmina Kosakowo, gmina Puck\n\n";
            $content .= "Jeśli użytkownik pyta o konkretną miejscowość (np. Reda, Rumia), potwierdź że PEWIK dostarcza tam wodę i odsyłaj do strony jakości wody.\n";
        }
        
        // =====================================================
        // SEKCJA 3: KANALIZACJA
        // =====================================================
        if ($this->contains_any($msg, ['toalet', 'wrzuca', 'śmieci', 'zator', 'zapcha', 'olej', 'kanalizacj', 'ściek', 'studzienk'])) {
            $content .= "TEMAT: KANALIZACJA\n";
            $content .= "Co NIE może trafiać do kanalizacji: chusteczki nawilżane, tłuszcz/olej, resztki jedzenia, materiały budowlane, leki, farby.\n";
            $content .= "Zator w instalacji wewnętrznej (w domu) -> Hydraulik.\n";
            $content .= "Zator w sieci miejskiej (na ulicy, wylewa ze studzienki) -> Zgłoś na 994.\n";
        }

        // =====================================================
        // SEKCJA 4: WNIOSKI I FORMULARZE (z linkami do kotwic)
        // =====================================================
        if ($this->contains_any($msg, ['wniosek', 'formularz', 'druk', 'dokument', 'gdzie', 'skąd', 'pobrać', 'załatwić', 'przyłącz', 'umow', 'przepis', 'właściciel', 'reklamac', 'rozwiąz', 'zrezygn', 'nazwisk', 'dane', 'projekt', 'mapy', 'hydrant', 'urządzen', 'przebudow', 'podłącz', 'działk', 'dom', 'nieruchom', 'kanal', 'sieć', 'sieci', 'szko', 'poleceni', 'lokalow', 'ogrogow', 'obiekt', 'budowl', 'zmiana adresu', 'zmiana nazwiska', 'zmiana telefon', 'zmiana mail', 'zmiana e-mail', 'aktualizacja danych', 'adres korespondenc', 'nowy adres', 'zmienić adres', 'zmienić dane', 'rozwód', 'rozwod', 'odłącz', 'odlacz', 'spadek', 'sprzedaż', 'sprzedaz', 'nowy właściciel', 'zmiana właściciela', 'przepisać umowę', 'przepisanie umowy'])) {
            $content .= "TEMAT: WNIOSKI I FORMULARZE\n";
            
            $content .= "STRONA GŁÓWNA FORMULARZY: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/\n\n";
            
            $content .= "=== A. PRZYŁĄCZENIE DO SIECI (wnioski 1-7) ===\n";
            $content .= "LINK: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#przylaczenia\n";
            $content .= "- Nr 1: Zapytanie o możliwość przyłączenia (PIERWSZY KROK!)\n";
            $content .= "- Nr 2: Wniosek o warunki przyłączenia\n";
            $content .= "- Nr 3: Uzgodnienie projektu przyłącza\n";
            $content .= "- Nr 4: Zgłoszenie budowy/włączenia\n";
            $content .= "- Nr 5: Protokół odbioru technicznego\n";
            $content .= "- Nr 6: Zaświadczenie o przyłączeniu\n";
            $content .= "- Nr 7: Zmiana warunków/przeniesienie wodomierza\n\n";
            
            $content .= "=== B. UMOWY, ROZLICZENIA, REKLAMACJE (wnioski 10-18) ===\n";
            $content .= "LINK: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#umowy\n";
            $content .= "- Nr 10: Zawarcie umowy (nowa umowa lub przepisanie na inną osobę) + Zał.1: Protokół zdawczo-odbiorczy\n";
            $content .= "- Nr 11: Rozwiązanie umowy (zakończenie umowy, rezygnacja)\n";
            $content .= "- Nr 12: Polecenie zapłaty (włączenie)\n";
            $content .= "- Nr 13: Odwołanie polecenia zapłaty\n";
            $content .= "- Nr 14: Raport lokalowy\n";
            $content .= "- Nr 15: Zgłoszenie reklamacji\n";
            $content .= "- Nr 16: Zgłoszenie szkody (nie samochód)\n";
            $content .= "- Nr 17: Zgłoszenie szkody samochodowej\n";
            $content .= "- Nr 18: Wniosek o aktualizację danych Usługobiorcy (ZMIANA DANYCH: adres korespondencji, nazwisko, telefon, e-mail)\n\n";
            
            $content .= "WAŻNE - ROZWÓD / PRZEPISANIE UMOWY / ZMIANA WŁAŚCICIELA:\n";
            $content .= "Gdy chcesz przepisać umowę na inną osobę (np. po rozwodzie, sprzedaży, spadku):\n";
            $content .= "1. Obecny usługobiorca składa: Wniosek nr 11 (Rozwiązanie umowy)\n";
            $content .= "2. Nowy usługobiorca składa: Wniosek nr 10 (Zawarcie umowy) + Załącznik 1 (Protokół zdawczo-odbiorczy)\n";
            $content .= "Oba wnioski można złożyć jednocześnie na: bok@pewik.gdynia.pl\n\n";
            
            $content .= "WAŻNE - ZMIANA DANYCH USŁUGOBIORCY (bez zmiany osoby):\n";
            $content .= "Zmiana adresu korespondencji, nazwiska (np. po ślubie), telefonu, e-maila = Wniosek nr 18 (Aktualizacja danych Usługobiorcy)\n";
            $content .= "Link: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#umowy\n\n";
            
            $content .= "=== C. WODOMIERZE LOKALOWE I OGRODOWE (wnioski 21-23) ===\n";
            $content .= "LINK: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#wodomierze\n";
            $content .= "- Nr 21: Warunki montażu wodomierzy lokalowych\n";
            $content .= "- Nr 22: Kontrola montażu wodomierzy lokalowych\n";
            $content .= "- Nr 23: Wodomierz ogrodowy (pierwszy montaż)\n\n";
            
            $content .= "=== D. USŁUGI DODATKOWE (wnioski 24-27) ===\n";
            $content .= "LINK: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#uslugi\n";
            $content .= "- Nr 24: Usługa nie objęta taryfą\n";
            $content .= "- Nr 25: Umowa na budowę przyłącza kanalizacyjnego\n";
            $content .= "- Nr 26: Kopia dokumentacji archiwalnej\n";
            $content .= "- Nr 27: Pobór wody z hydrantu\n\n";
            
            $content .= "=== E. BUDOWA URZĄDZEŃ (wnioski 31-34) ===\n";
            $content .= "LINK: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#budowaUrzadzen\n";
            $content .= "- Nr 31: Warunki techniczne wykonania urządzeń\n";
            $content .= "- Nr 32: Uzgodnienie dokumentacji projektowej urządzeń\n";
            $content .= "- Nr 33: Kontrola i odbiór techniczny urządzeń wod-kan\n";
            $content .= "- Nr 34: Protokół odbioru technicznego urządzeń wod-kan\n\n";
            
            $content .= "=== F. BUDOWA OBIEKTÓW BUDOWLANYCH (wnioski 41-42) ===\n";
            $content .= "LINK: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#budowaObiektow\n";
            $content .= "- Nr 41: Warunki techniczne na przebudowę urządzeń\n";
            $content .= "- Nr 42: Uzgodnienie rozwiązań projektowych\n\n";
            
            $content .= "JAK ZŁOŻYĆ: Wyślij skan na bok@pewik.gdynia.pl lub przez e-BOK. Nie musisz przychodzić!\n";
            $content .= "NIE WIESZ JAKI WNIOSEK? Napisz na bok@pewik.gdynia.pl - pomożemy wybrać właściwy.\n";
        }

        // =====================================================
        // SEKCJA 5: CENY I TARYFY
        // =====================================================
        if ($this->contains_any($msg, ['cen', 'koszt', 'taryf', 'faktur', 'płatnoś', 'ile płacę', 'ryczałt', 'norm', 'bez liczni', 'stawk', 'opłat', 'wysokoś', 'ile kosztuje', 'drogo', 'tanio', 'wod'])) {
            $content .= "TEMAT: CENY WODY I ŚCIEKÓW\n";
            $content .= "LINK DO CEN (użyj tego!): https://pewik.gdynia.pl/strefa-klienta/ceny-i-taryfy/\n";
            $content .= "Taryfy:\n";
            $content .= "- Lista A: Gdynia, Rumia, Reda\n";
            $content .= "- Lista C: Gmina Puck\n";
            $content .= "Bez wodomierza: płatność wg ryczałtu (normy zużycia w taryfie).\n";
            $content .= "UWAGA: Nie mamy kalkulatora online - sprawdź stawki w taryfie.\n";
        }

        // =====================================================
        // SEKCJA 6: INWESTYCJE I BUDOWY SIECI
        // =====================================================
        if ($this->contains_any($msg, ['inwestycj', 'budow', 'buduj', 'kopią', 'kopie', 'wykop', 'roboty', 'prace', 'remont', 'modernizacj', 'rozbudow', 'nowa sieć', 'nową sieć', 'nowej sieci', 'planowane', 'planują', 'będzie', 'kiedy będzie', 'przed domem', 'przy ulicy', 'na ulicy', 'w mojej okolicy', 'sieć wodociągow', 'sieć kanalizacyj'])) {
            $content .= "TEMAT: INWESTYCJE I BUDOWA SIECI WODNO-KANALIZACYJNEJ\n";
            
            $content .= "--- GDZIE SPRAWDZIĆ AKTUALNE INWESTYCJE? ---\n";
            $content .= "Wszystkie informacje o prowadzonych i planowanych inwestycjach znajdziesz na stronie: [INWESTYCJE PEWIK](https://pewik.gdynia.pl/strefa-mieszkanca/inwestycje/)\n";
            $content .= "Na tej stronie możesz sprawdzić:\n";
            $content .= "- Aktualne budowy sieci wodociągowej i kanalizacyjnej\n";
            $content .= "- Planowane inwestycje w poszczególnych miejscowościach\n";
            $content .= "- Harmonogramy prac\n";
            $content .= "- Informacje o utrudnieniach\n";
            
            $content .= "--- ZASIĘG DZIAŁANIA PEWIK ---\n";
            $content .= "PEWIK prowadzi inwestycje na terenie: Gdyni, Rumi, Redy, Wejherowa, Kosakowa i okolic.\n";
            
            $content .= "--- CHCESZ PRZYŁĄCZYĆ SIĘ DO NOWEJ SIECI? ---\n";
            $content .= "Jeśli w Twojej okolicy powstaje nowa sieć i chcesz się przyłączyć, złóż wniosek o warunki przyłączenia: [Formularze](https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/)\n";
            
            $content .= "--- KONTAKT W SPRAWIE INWESTYCJI ---\n";
            $content .= "Szczegółowe pytania o konkretne inwestycje: Dział Techniczny lub BOK tel. +48 58 66 87 311, e-mail: bok@pewik.gdynia.pl\n";
        }

        // =====================================================
        // SEKCJA 7: WODOMIERZE I ODCZYTY
        // =====================================================
        if ($this->contains_any($msg, ['licznik', 'wodomierz', 'odczyt', 'ogród', 'legalizac', 'wymian', 'mróz', 'zamarz', 'podlicznik', 'studzienk', 'stan', 'podaj', 'przekaz', 'remont', 'uszkodz', 'zepsut', 'pęknięt', 'rozbit', 'plomb', 'oplomb', 'umówić'])) {
            $content .= "TEMAT: WODOMIERZE I ODCZYTY\n";
            
            $content .= "--- JAK PODAĆ ODCZYT? ---\n";
            $content .= "Masz 4 sposoby:\n";
            $content .= "1. [e-Odczyt](https://pewik.gdynia.pl/e-odczyt) (bez logowania).\n";
            $content .= "2. [e-BOK](https://pewik.gdynia.pl/ebok).\n";
            $content .= "3. SMS (instrukcja na stronie).\n";
            $content .= "4. Teleodczyt (Voicebot): zadzwoń i podaj stan głosowo.\n";
            
            $content .= "--- WYMIANA WODOMIERZA GŁÓWNEGO ---\n";
            $content .= "Wodomierz główny jest własnością PEWIK. Wymieniamy go BEZPŁATNIE gdy:\n";
            $content .= "- Kończy się okres legalizacji (co 5 lat)\n";
            $content .= "- Jest uszkodzony z przyczyn naturalnych\n";
            $content .= "Nie musisz składać wniosku - sami się z Tobą skontaktujemy przed końcem legalizacji.\n";
            $content .= "Jeśli uważasz że wodomierz źle liczy - zgłoś to mailowo na bok@pewik.gdynia.pl lub telefonicznie: +48 58 66 87 311.\n";
            $content .= "WAŻNE - USZKODZENIE Z WINY KLIENTA:\n";
            $content .= "Jeśli wodomierz został uszkodzony z winy klienta (np. podczas remontu, prac budowlanych, zamarznięcia z powodu braku zabezpieczenia), wymiana jest ODPŁATNA - klient pokrywa koszt nowego wodomierza i robocizny.\n";
            $content .= "W takim przypadku zgłoś uszkodzenie: bok@pewik.gdynia.pl lub tel. +48 58 66 87 311.\n";
            
            $content .= "--- OPLOMBOWANIE / UMÓWIENIE WIZYTY ---\n";
            $content .= "⚠️ WAŻNE: Zgłoszenie gotowości do oplombowania wodomierza odbywa się WYŁĄCZNIE przez e-mail!\n";
            $content .= "Adres: biuro@pewik.gdynia.pl\n";
            $content .= "W wiadomości KONIECZNIE podaj:\n";
            $content .= "- swój KOD NABYWCY,\n";
            $content .= "- imię i nazwisko lub nazwę USŁUGOBIORCY,\n";
            $content .= "- numer PUNKTU ROZLICZENIOWEGO lub adres montażu wodomierza,\n";
            $content .= "- numer UMOWY,\n";
            $content .= "- dane kontaktowe (numer telefonu).\n";
            $content .= "❌ NIE umawiamy wizyt telefonicznie!\n";
            $content .= "❌ NIE dzwoń w sprawie umówienia oplombowania - napisz e-mail!\n";
            $content .= "Dotyczy to: oplombowania, kontroli wodomierza, założenia plomby, wymiany wodomierza ogrodowego.\n";
            
            $content .= "--- WYMIANA WODOMIERZA OGRODOWEGO (UTRATA CECH LEGALIZACYJNYCH) ---\n";
            $content .= "Wodomierz ogrodowy (podlicznik) jest własnością KLIENTA.\n";
            $content .= "Legalizacja wodomierza trwa 5 lat. Po tym czasie należy wymienić wodomierz.\n\n";
            $content .= "⚠️ WAŻNE: Szczegółowa instrukcja krok po kroku znajduje się na stronie: [Wymiana wodomierza](https://pewik.gdynia.pl/wymiana)\n\n";
            $content .= "PROCEDURA WYMIANY - KROK PO KROKU:\n";
            $content .= "1. Sprawdź czy Twój wodomierz ogrodowy ma ważne cechy legalizacyjne.\n";
            $content .= "2. Wymień wodomierz ogrodowy na nowy, a dotychczasowy pozostaw do kontroli.\n";
            $content .= "3. Wyślij do nas e-mail na adres: biuro@pewik.gdynia.pl powiadamiający o gotowości do oplombowania wymienionego wodomierza.\n";
            $content .= "   W treści wiadomości KONIECZNIE podaj:\n";
            $content .= "   - swój KOD NABYWCY,\n";
            $content .= "   - imię i nazwisko lub nazwę USŁUGOBIORCY,\n";
            $content .= "   - numer PUNKTU ROZLICZENIOWEGO lub adres montażu wodomierza,\n";
            $content .= "   - numer UMOWY,\n";
            $content .= "   - dane kontaktowe (numer telefonu).\n";
            $content .= "4. W odpowiedzi przeprowadzimy kontrolę montażu nowego wodomierza i założymy plombę.\n\n";
            $content .= "Koszt zakupu nowego wodomierza ponosi KLIENT.\n";
            
            $content .= "--- ODPOWIEDZIALNOŚĆ ZA WODOMIERZE ---\n";
            $content .= "GŁÓWNY: Własność PEWIK - wymiana/legalizacja BEZPŁATNA.\n";
            $content .= "OGRODOWY: Własność KLIENTA - zakup, montaż, legalizacja na koszt klienta.\n";
            $content .= "UWAGA: Jeśli wodomierz pęknie z powodu mrozu (niezabezpieczony) - klient płaci za naprawę!\n";
        }
        
        // =====================================================
        // SEKCJA 8: E-BOK
        // =====================================================
        if ($this->contains_any($msg, ['logow', 'rejestrac', 'hasł', 'e-bok', 'ebok', 'problem', 'e-faktur', 'efaktur', 'na maila', 'sms', 'powiadom', 'saldo', 'konto', 'internetow', 'przypis', 'dodaj', 'dodać', 'połącz', 'dołącz', 'powiąz', 'scaleni', 'scalić', 'scal'])) {
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
            
            $content .= "--- INNE SPRAWY E-BOK (przypisanie, łączenie kont, itp.) ---\n";
            $content .= "⚠️ UWAGA: NIE MAM INFORMACJI o procedurach takich jak:\n";
            $content .= "- Przypisanie do konta e-BOK (wodomierza, punktu, wspólnoty, spółdzielni itp.)\n";
            $content .= "- Połączenie konta / scalenie konta / łączenie kont\n";
            $content .= "- Przenoszenie między kontami\n";
            $content .= "Dla WSZYSTKICH tych spraw odpowiadaj OGÓLNIE:\n";
            $content .= "'Nie mam informacji na temat tej procedury. Proszę o kontakt z Biurem Obsługi Klienta: e-mail bok@pewik.gdynia.pl lub telefon +48 58 66 87 311 (pn-pt 7:00-15:00).'\n";
            $content .= "NIE ZGADUJ czego dotyczy przypisanie! NIE wymieniaj konkretnie wodomierza/spółdzielni/punktu!\n";
        }

        // =====================================================
        // SEKCJA 9: DANE KONTAKTOWE
        // =====================================================
        if ($this->contains_any($msg, ['adres', 'siedzib', 'gdzie', 'dojazd', 'ulic', 'biur', 'lokalizacj', 'kontakt', 'telefon', 'godziny', 'otwarte', 'czynne', 'mail', 'poczt', 'numer', 'zadzwonić', 'infolinia', 'rozmow', 'email' , 'wrzutnia'])) {
            $content .= "TEMAT: DANE KONTAKTOWE I ADRESOWE\n";
            
            $content .= "--- TELEFON (Infolinia) ---\n";
            $content .= "Numer: +48 58 66 87 311\n";
            $content .= "Godziny: Pn-Pt 7:00 – 15:00\n";
            
            $content .= "--- WIZYTA OSOBISTA (Biuro Obsługi Klienta) ---\n";
            $content .= "Adres: ul. Witomińska 21, 81-311 Gdynia\n";
            $content .= "Godziny: Pn-Pt 8:00 – 15:00\n";

            $content .= "--- WRZUTNIA DOKUMENTÓW ---\n";
            $content .= "Wrzutnia dokumentów (przy wejściu): Pn-Pt 6:30 – 16:30.\n";
            
            $content .= "--- KANAŁY ELEKTRONICZNE (ZALECANE) ---\n";
            $content .= "E-mail: bok@pewik.gdynia.pl\n";
            $content .= "e-BOK: https://pewik.gdynia.pl/ebok\n";
            $content .= "Zasada: Zachęcamy do korzystania z e-maila i e-BOK zamiast wizyt papierowych.\n";
        }

        // =====================================================
        // SEKCJA 10: WAŻNOŚĆ DOKUMENTÓW
        // =====================================================
        if ($this->contains_any($msg, ['ważn', 'termin', 'decyzj', 'warunk', 'wygas', 'ile czas', 'daty', 'kiedy kończy', 'papiery'])) {
            $content .= "TEMAT: WAŻNOŚĆ DOKUMENTÓW\n";
            $content .= "ZASADA: Termin ważności każdego dokumentu (np. decyzji, warunków przyłączenia) jest szczegółowo określony w treści tego dokumentu. Proszę sprawdzić datę i termin bezpośrednio w posiadanym dokumencie.\n";
        }

        // =====================================================
        // SEKCJA 11: WEZWANIA I BRAKUJĄCE DOKUMENTY
        // =====================================================
        if ($this->contains_any($msg, ['wezwan', 'monit', 'zapłat', 'brak faktur', 'nie widzę', 'nie mam dokument', 'zgubiłem', 'zniszcz', 'duplikat', 'kopia'])) {
            $content .= "TEMAT: WEZWANIA DO ZAPŁATY I BRAKUJĄCE FAKTURY\n";
            $content .= "ZASADA: Wszystkie faktury (również te, których dotyczy wezwanie do zapłaty) są ZAWSZE dostępne w e-BOK.\n";
            $content .= "ROZWIĄZANIE: Zaloguj się do [e-BOK](https://pewik.gdynia.pl/ebok) i pobierz dokument. Nie musisz dzwonić do biura.\n";
            $content .= "RATY: W wyjątkowych przypadkach możesz wystąpić o rozłożenie płatności na raty - szczegóły: https://pewik.gdynia.pl/strefa-klienta/splata-naleznosci/\n";
        }

        // =====================================================
        // SEKCJA 11B: SPŁATA NALEŻNOŚCI I RATY
        // =====================================================
        if ($this->contains_any($msg, ['raty', 'rata', 'ratach', 'rataln', 'spłat', 'należnoś', 'zaległ', 'dług', 'długi', 'nie zapłac', 'windykacj', 'odcięci', 'odcięcie', 'odłącz', 'wstrzym', 'blokad', 'zablokow', 'rozłożyć', 'rozłoż', 'rozłożenie', 'nie stać', 'trudna sytuacj', 'problem z płat', 'płatność na raty', 'spłacić', 'spłacać', 'zaległości'])) {
            $content .= "TEMAT: SPŁATA NALEŻNOŚCI I ROZKŁADANIE NA RATY\n\n";
            
            $content .= "ODPOWIEDŹ NA PYTANIE O RATY (użyj tego tekstu w odpowiedzi):\n";
            $content .= "W wyjątkowych przypadkach możesz wystąpić o rozłożenie płatności na raty. ";
            $content .= "Wyślij pisemną prośbę na bok@pewik.gdynia.pl opisując swoją sytuację. ";
            $content .= "Odpowiedź otrzymasz w terminie do 14 dni. ";
            $content .= "Szczegóły procedury i wymagania znajdziesz tutaj: [Spłata należności](https://pewik.gdynia.pl/strefa-klienta/splata-naleznosci/)\n\n";
            
            $content .= "DODATKOWE INFO:\n";
            $content .= "- Każdy wniosek rozpatrywany indywidualnie (historia rozliczeń, sytuacja klienta, zużycie)\n";
            $content .= "- Przed odcięciem wody: powiadomienie min. 20 dni wcześniej\n";
            $content .= "- Opłaty za wezwania i odcięcie: BEZPŁATNIE\n";
        }

        // =====================================================
        // SEKCJA 11C: ZWROT NADPŁATY
        // =====================================================
        if ($this->contains_any($msg, ['nadpłat', 'nadplat', 'zwrot', 'zwrotu', 'przelew', 'oddać', 'oddac', 'za dużo zapłac', 'więcej niż', 'nadwyżk'])) {
            $content .= "TEMAT: ZWROT NADPŁATY\n";
            $content .= "⚠️ UWAGA: NIE MA FORMULARZA do zwrotu nadpłaty! Nie wspominaj o żadnym formularzu!\n\n";
            
            $content .= "--- JAK UZYSKAĆ ZWROT NADPŁATY? ---\n";
            $content .= "Wyślij e-mail na: windykacja@pewik.gdynia.pl (z kopią do bok@pewik.gdynia.pl)\n";
            $content .= "W wiadomości podaj:\n";
            $content .= "- Kwotę nadpłaty do zwrotu\n";
            $content .= "- Numer konta bankowego do przelewu\n";
            $content .= "- Twój kod nabywcy\n\n";
            
            $content .= "--- WAŻNE INFORMACJE ---\n";
            $content .= "- Wysokość nadpłaty widoczna na fakturach oraz w e-BOK (zakładka 'Faktury i salda')\n";
            $content .= "- Termin odpowiedzi: do 14 dni od otrzymania prośby\n";
            $content .= "- Nadpłaty niezwrócone zaliczane są na poczet przyszłych zobowiązań\n";
            $content .= "- Prośbę o zwrot może złożyć Usługobiorca (osoba na umowie)\n";
            $content .= "- Opłaty za zwrot: BEZPŁATNIE\n\n";
            
            $content .= "Szczegółowa instrukcja: [Zwrot nadpłaty](https://pewik.gdynia.pl/strefa-klienta/nadplata/)\n";
        }

        // =====================================================
        // SEKCJA 12: KOREKTA FAKTURY / REKLAMACJA
        // =====================================================
        if ($this->contains_any($msg, ['korekt', 'skoryg', 'błąd', 'pomyłk', 'zły odczyt', 'zła faktur', 'reklamac'])) {
            $content .= "TEMAT: KOREKTA FAKTURY / REKLAMACJA\n";
            $content .= "PROCEDURA: Wyślij e-mail na bok@pewik.gdynia.pl. W wiadomości musisz podać 3 rzeczy:\n";
            $content .= "1. Numer faktury pierwotnej (tej z błędem).\n";
            $content .= "2. Twój punkt rozliczeniowy.\n";
            $content .= "3. Aktualne wskazanie wodomierza (stan licznika).\n";
        }

        // =====================================================
        // SEKCJA 13: ROZLICZENIA I SZACUNKI
        // =====================================================
        if ($this->contains_any($msg, ['rozlicz', 'szacunk', 'prognoz', 'dlaczego tak dużo', 'stan licznik', 'nie było mnie'])) {
            $content .= "TEMAT: ROZLICZENIA I FAKTURY SZACUNKOWE\n";
            $content .= "Dlaczego szacunek? Bo nie znamy Twojego odczytu (brak dostępu pracownika).\n";
            $content .= "Rozwiązanie: Przekaż odczyt samodzielnie (przez e-BOK, e-Odczyt, SMS) w swoim okresie obrachunkowym.\n";
        }

        // =====================================================
        // SEKCJA 14: POLECENIE ZAPŁATY
        // =====================================================
        if ($this->contains_any($msg, ['poleceni', 'polecenie zapłaty', 'automatycz', 'z konta', 'samo się', 'anulow', 'stałe zleceni'])) {
            $content .= "TEMAT: POLECENIE ZAPŁATY\n";
            $content .= "Aktywacja (Włącz): Wyślij do nas Wniosek nr 12. My załatwimy autoryzację w banku (trwa do 30 dni).\n";
            $content .= "Rezygnacja (Wyłącz): Wyślij Wniosek nr 13 (min. 14 dni przed terminem).\n";
        }

        // =====================================================
        // SEKCJA 15: SAMODZIELNE FAKTUROWANIE
        // =====================================================
        if ($this->contains_any($msg, ['sam wystaw', 'samodzieln', 'rzeczywist', 'fakturowa'])) {
            $content .= "TEMAT: SAMODZIELNE FAKTUROWANIE (ROZLICZENIA RZECZYWISTE)\n";
            $content .= "Co to jest? Usługa w e-BOK pozwalająca samemu wystawiać faktury (unikasz szacunków).\n";
            $content .= "Jak włączyć? W e-BOK zakładka 'Klient' -> 'Rozliczenia Rzeczywiste' -> 'ZMIEŃ'.\n";
            $content .= "Wymagania: Musisz mieć aktywne konto e-BOK i zgodę na e-fakturę.\n";
        }

        // =====================================================
        // SEKCJA 16: WŁADZE SPÓŁKI
        // =====================================================
        // Dodano imiona i nazwiska osób z firmy jako słowa kluczowe
        if ($this->contains_any($msg, [
            'zarząd', 'prezes', 'dyrektor', 'kierownik', 'władz', 'nadzorcz', 'rady', 'radą', 'rada', 
            'właściciel', 'udziałow', 'wspólni', 'gmin', 'kto rządzi', 'skład', 'osoby',
            'kim jest', 'kto to',
            // Imiona i nazwiska osób z PEWIK
            'kieloch', 'jacek kieloch',
            'kujawski', 'wiesław kujawski',
            'zawisza', 'marcin zawisza',
            'lewandowska', 'anna lewandowska',
            'kraszkiewicz', 'kamila kraszkiewicz',
            'maciąg', 'karolina maciąg',
            'galiński', 'łukasz galiński',
            'skwarło', 'radosław skwarło'
        ])) {
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

        // =====================================================
        // SEKCJA 17: DANE REJESTROWE
        // =====================================================
        if ($this->contains_any($msg, ['nip', 'regon', 'krs', 'konto', 'bank', 'numer konta', 'przelew', 'dane firmy', 'pkd', 'działalnoś', 'czym się zajmuje'])) {
            $content .= "TEMAT: DANE REJESTROWE I BANKOWE (BIP)\n";
            $content .= "Nazwa: Przedsiębiorstwo Wodociągów i Kanalizacji Sp. z o.o. w Gdyni.\n";
            $content .= "Siedziba: ul. Witomińska 29, 81-311 Gdynia.\n";
            $content .= "NIP: 586-010-44-34 | REGON: 190563879 | KRS: 0000126973.\n";
            $content .= "Konto Bankowe: Citibank Handlowy 89 1030 1120 0000 0000 0340 6701.\n";
            $content .= "PKD (Główne): 36.00.Z (Pobór i uzdatnianie wody), 37.00.Z (Odprowadzanie i oczyszczanie ścieków).\n";
        }

        // =====================================================
        // SEKCJA 18: SCHEMAT ORGANIZACYJNY
        // =====================================================
        if ($this->contains_any($msg, ['schemat', 'struktur', 'organizac', 'dział', 'pion', 'podlega'])) {
            $content .= "TEMAT: SCHEMAT ORGANIZACYJNY SPÓŁKI\n";
            $content .= "ZARZĄD: Prezes (PZ), Wiceprezes (WZ).\n";
            
            $content .= "--- PIONY BEZPOŚREDNIE ---\n";
            $content .= "Podległe Zarządowi: Biuro Obsługi Klienta (ZOK), Biuro Prawne, Biuro Personalne, Informatyka, Główny Księgowy, Dział Zamówień.\n";
            
            $content .= "--- PION EKSPLOATACJI (Dyr. DE) ---\n";
            $content .= "Jednostki: Dyspozytornia (ED), Produkcja Wody, Sieć Wodociągowa, Sieć Kanalizacyjna, Oczyszczalnia Ścieków, Ochrona Środowiska.\n";
            
            $content .= "--- PION TECHNICZNY I ROZWOJU (Dyr. DT) ---\n";
            $content .= "Jednostki: Dział Techniczny, Obsługa Inwestycji i Remontów, Laboratorium Wody i Ścieków, Dział Sprzętu, Utrzymanie Ruchu.\n";
        }

        // =====================================================
        // SEKCJA 19: MAJĄTEK I FINANSE
        // =====================================================
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

            $content .= "--- PRZEZNACZENIE ZYSKU ---\n";
            $content .= "Decyzjami Zgromadzenia Wspólników zysk został rozdysponowany następująco:\n";
            $content .= "- Za rok 2023: W całości na pokrycie strat z lat ubiegłych.\n";
            $content .= "- Za rok 2022: W całości na pokrycie strat z lat ubiegłych.\n";
            $content .= "- Za rok 2021: W całości na pokrycie strat z lat ubiegłych.\n";
            $content .= "- Za rok 2020: W całości na kapitał zapasowy.\n";
            $content .= "- Za rok 2019: W całości na kapitał zapasowy.\n";
            
            $content .= "--- WARTOŚĆ MAJĄTKU TRWAŁEGO (Stan na 31.12.2023 r.) ---\n";
            $content .= "Majątek OGÓŁEM: Wartość Brutto: 1 474 498 183,84 zł | Wartość Netto: 627 423 606,23 zł.\n";
        }
        
        // =====================================================
        // STOPKA - ZAWSZE DODAWANA
        // =====================================================
        $content .= "\n---\n";
        $content .= "OBSŁUGA ELEKTRONICZNA (PRIORYTET): e-mail: bok@pewik.gdynia.pl | e-BOK: https://pewik.gdynia.pl/ebok\n";
        $content .= "Formularze i wnioski: https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/\n";
        $content .= "Telefon (gdy sprawa pilna): +48 58 66 87 311 (Pn-Pt 7:00-15:00)\n";
        $content .= "AWARIE 24h: 994\n";
        $content .= "Adres: ul. Witomińska 21, 81-311 Gdynia";

        return $content;
    }

    private function contains_any($haystack, $needles) {
        foreach ($needles as $needle) {
            if (strpos($haystack, $needle) !== false) return true;
        }
        return false;
    }

    // =====================================================
    // WYWOŁANIE MODELU AI (ULEPSZONA PREAMBUŁA)
    // =====================================================

    private function call_cohere_model($user_message, $knowledge_context, $chat_history = array()) {
    
        // PREAMBUŁA - ROZBUDOWANA O ZAKRES DZIAŁALNOŚCI
        $system_preamble = "Jesteś pomocnym asystentem PEWIK Gdynia - przedsiębiorstwa wodociągów i kanalizacji.

=== TWÓJ ZAKRES KOMPETENCJI ===
Możesz pomagać TYLKO w sprawach dotyczących:
✓ Wody ZIMNEJ (dostawy, awarie, jakość, ciśnienie)
✓ Kanalizacji (ścieki, odprowadzanie, zapchania sieci miejskiej)
✓ Wodomierzy (odczyty, wymiana, legalizacja)
✓ Faktur i płatności za wodę/ścieki
✓ Wniosków i formularzy PEWIK
✓ Przyłączy wodno-kanalizacyjnych

NIE ZAJMUJESZ SIĘ (i nie udzielasz porad w tych sprawach):
✗ Ciepłą wodą (to sprawa administratora budynku, spółdzielni, wspólnoty lub dostawcy ciepła)
✗ Ogrzewaniem / CO (administrator, dostawca ciepła)
✗ Gazem (operator sieci gazowej, pogotowie gazowe)
✗ Prądem (operator sieci energetycznej)
✗ Wywozem śmieci (urząd miasta/gminy)

WAŻNE: Gdy temat jest poza zakresem PEWIK, NIE podawaj konkretnych nazw firm, numerów telefonów ani adresów innych instytucji - używaj ogólnych określeń (administrator, dostawca, operator, urząd).

=== ZASADY KOMUNIKACJI ===
1. Odpowiadaj PEŁNYMI ZDANIAMI, naturalnie i uprzejmie.
2. Bazuj TYLKO na dostarczonej WIEDZY. Jeśli czegoś nie wiesz, napisz to wprost.
3. KRYTYCZNE: NIE wymyślaj linków URL! Używaj TYLKO linków które widzisz w sekcji WIEDZA poniżej. Jeśli nie ma linka w WIEDZY - nie podawaj żadnego linka, tylko nazwij stronę słownie.
4. Jeśli pytanie dotyczy tematu POZA Twoim zakresem, grzecznie wyjaśnij że PEWIK tym się nie zajmuje.

=== BRAK INFORMACJI W WIEDZY - KRYTYCZNE! ===
Gdy użytkownik pyta o procedurę, wniosek lub sprawę której NIE MA w sekcji WIEDZA poniżej:
1. NIE WYMYŚLAJ odpowiedzi!
2. NIE sugeruj wniosków których nie widzisz w WIEDZY!
3. NIE zgaduj jaki formularz może być potrzebny!
4. NIE POWTARZAJ słów użytkownika w odpowiedzi (np. nie pisz 'przypisanie wodomierza' jeśli użytkownik o to pytał)!
5. Zamiast tego ZAWSZE odpowiedz OGÓLNIE:
   'Nie mam informacji na temat tej procedury. Proszę o kontakt z Biurem Obsługi Klienta:
   - e-mail: bok@pewik.gdynia.pl
   - telefon: +48 58 66 87 311 (pn-pt 7:00-15:00)
   Pracownicy BOK udzielą szczegółowych informacji.'

PRZYKŁADY spraw których NIE MA w wiedzy (odpowiadaj OGÓLNIE że nie masz informacji):
- Przypisanie czegokolwiek do konta e-BOK (wodomierza, punktu, wspólnoty, spółdzielni)
- Połączenie/scalenie/łączenie kont w e-BOK
- Inne nietypowe procedury

PAMIĘTAJ: Lepiej powiedzieć 'nie mam informacji na temat tej procedury' niż podać BŁĘDNĄ lub ZGADYWANĄ informację!

=== LINKI - ABSOLUTNY ZAKAZ WYMYŚLANIA ===
DOZWOLONE linki (tylko te!):
- Formularze (strona główna): https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/
- Formularze - Przyłączenia (A): https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#przylaczenia
- Formularze - Umowy/Reklamacje (B): https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#umowy
- Formularze - Wodomierze (C): https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#wodomierze
- Formularze - Usługi dodatkowe (D): https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#uslugi
- Formularze - Budowa urządzeń (E): https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#budowaUrzadzen
- Formularze - Budowa obiektów (F): https://pewik.gdynia.pl/strefa-klienta/formularze-wnioskow/#budowaObiektow
- Ceny i taryfy: https://pewik.gdynia.pl/strefa-klienta/ceny-i-taryfy/
- Spłata należności i raty: https://pewik.gdynia.pl/strefa-klienta/splata-naleznosci/
- Zwrot nadpłaty: https://pewik.gdynia.pl/strefa-klienta/nadplata/
- Awarie: https://pewik.gdynia.pl/awarie/planowane-wylaczenia/
- Inwestycje: https://pewik.gdynia.pl/strefa-mieszkanca/inwestycje/
- e-BOK: https://pewik.gdynia.pl/ebok
- e-Odczyt: https://pewik.gdynia.pl/e-odczyt
- RODO/Prywatność: https://pewik.gdynia.pl/rodo/
Jeśli potrzebujesz innego linka - NIE WYMYŚLAJ GO. Napisz 'szczegóły na stronie PEWIK' bez podawania adresu.

=== ZWIĘZŁOŚĆ ODPOWIEDZI (BARDZO WAŻNE!) ===
1. Odpowiadaj KRÓTKO i KONKRETNIE - maksymalnie 3-5 zdań dla prostych pytań.
2. NIE rozpisuj się - użytkownik chce szybkiej odpowiedzi, nie eseju.
3. Dla procedur wieloetapowych (np. przyłącze) - podaj TYLKO PIERWSZY KROK + link do pełnej listy wniosków.
4. NIE powtarzaj informacji, które już podałeś.
5. NIE dodawaj zbędnych wstępów typu 'Rozumiem, że...', 'Postaram się pomóc...' - CHYBA że użytkownik jest wyraźnie sfrustrowany.
6. NIE wymyślaj usług które nie istnieją (np. 'kalkulator opłat', 'szacunkowe obliczenia').
7. NIE używaj emoji ani emotikon w odpowiedziach! Żadnych 💧📞📧🔗 itp. Pisz tylko czystym tekstem.

=== EMPATIA - TYLKO GDY POTRZEBNA ===
Używaj empatycznych sformułowań TYLKO gdy użytkownik:
- Używa wykrzykników, caps locka, wulgaryzmów
- Pisze że jest zdenerwowany, sfrustrowany, zły
- Grozi skargą, mediami, prawnikiem
W NORMALNYCH pytaniach - odpowiadaj rzeczowo, bez empatycznych wstępów.

=== OBSŁUGA TRUDNYCH SYTUACJI ===
1. NIGDY nie sugeruj składania skarg, kontaktu z mediami, urzędami nadzoru itp.
2. NIGDY nie odsyłaj na stronę internetową osoby która mówi że nie ma internetu - podaj TELEFON i ADRES.
3. Gdy nie możesz pomóc - od razu podaj KONKRETNE dane kontaktowe (telefon: +48 58 66 87 311, adres: ul. Witomińska 21).

=== MIESZANE PYTANIA (zimna + ciepła woda) ===
Gdy użytkownik pyta o brak CAŁEJ wody (zimnej i ciepłej):
- Dla ZIMNEJ: sprawdź czy to awaria sieciowa na https://pewik.gdynia.pl/awarie/ lub zadzwoń 994
- Dla CIEPŁEJ: skontaktuj się z administratorem/wspólnotą/spółdzielnią
NIE odsyłaj do dyspozytora PEWIK w sprawie ciepłej wody!

=== PRIORYTET OBSŁUGI ===
Gdy użytkownik pyta jak coś załatwić, ZAWSZE stosuj tę kolejność:
1. NAJPIERW: Wskaż KONKRETNY wniosek/formularz (numer i nazwa) + link do pobrania
2. POTEM: Wskaż że można wysłać e-mailem na bok@pewik.gdynia.pl lub przez e-BOK
3. OSTATECZNIE: Kontakt telefoniczny/osobisty TYLKO gdy sprawa jest skomplikowana lub awaryjna

NIGDY nie zaczynaj odpowiedzi od 'skontaktuj się z BOK' lub 'zadzwoń'. 
ZAWSZE najpierw podaj konkretny formularz i gdzie go znaleźć!

=== OPLOMBOWANIE / UMÓWIENIE WIZYTY ===
BEZWZGLĘDNA ZASADA: Zgłoszenie gotowości do oplombowania wodomierza odbywa się WYŁĄCZNIE przez e-mail: biuro@pewik.gdynia.pl
NIE podawaj numeru telefonu do umawiania wizyt oplombowania!
NIE sugeruj dzwonienia w sprawie oplombowania!
W e-mailu klient MUSI podać: kod nabywcy, imię i nazwisko/nazwę usługobiorcy, numer punktu rozliczeniowego lub adres, numer umowy, telefon kontaktowy.
Dotyczy: oplombowanie, kontrola wodomierza, założenie plomby, wymiana wodomierza ogrodowego.
Szczegółowa instrukcja: https://pewik.gdynia.pl/wymiana

=== WIEDZA ===
$knowledge_context";

        // Przygotuj chatHistory dla Cohere API
        $cohere_chat_history = array();
        if (!empty($chat_history) && is_array($chat_history)) {
            foreach ($chat_history as $msg) {
                // Cohere wymaga: role = 'USER' lub 'CHATBOT', message = treść
                if (isset($msg['user_message']) && isset($msg['bot_response'])) {
                    $cohere_chat_history[] = array(
                        'role' => 'USER',
                        'message' => $msg['user_message']
                    );
                    $cohere_chat_history[] = array(
                        'role' => 'CHATBOT', 
                        'message' => $msg['bot_response']
                    );
                }
            }
        }

        $chat_request = array(
            'apiFormat' => 'COHERE',
            'message' => $user_message,
            'preambleOverride' => $system_preamble,
            
            'maxTokens' => 400,  // Zmniejszone dla krótszych odpowiedzi
            'temperature' => 0.25,  // Niższa = bardziej deterministyczne, mniej wymyślania
            'topP' => 0.65,
            'frequencyPenalty' => 0.0,
            'presencePenalty' => 0.0
        );

        // Dodaj chatHistory tylko jeśli nie jest pusta
        if (!empty($cohere_chat_history)) {
            $chat_request['chatHistory'] = $cohere_chat_history;
        }

        $body = array(
            'compartmentId' => $this->compartment_id,
            'servingMode' => array(
                'servingType' => 'ON_DEMAND',
                'modelId' => $this->model_id
            ),
            'chatRequest' => $chat_request
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
        
        if (empty($session_id)) {
            $session_id = 'genai_' . uniqid();
        }

        return array(
            'error' => $error,
            'message' => $message,
            'sessionId' => $session_id,
            'messageId' => 0,
            'responseTime' => $response_time,
            'hasTrace' => false,
            'hasCitations' => false
        );
    }
    
    private function format_headers_for_wp($headers) {
        $wp_headers = array();
        foreach ($headers as $key => $value) {
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