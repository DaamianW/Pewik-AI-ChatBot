/**
 * PEWIK AI Chatbot - Z automatycznym czyszczeniem sesji i ocenianiem odpowiedzi
 */

;(function ($) {
	'use strict'

	const STORAGE_SESSION_KEY = 'pewik_chatbot_session'
	const STORAGE_MESSAGES_KEY = 'pewik_chatbot_messages'
	const STORAGE_SESSION_TIME_KEY = 'pewik_chatbot_session_time'
	const MAX_MESSAGE_LENGTH = 500
	const SESSION_TIMEOUT = 10 * 60 * 1000 // 10 minut (bezpieczniej niż 15)
	const DEBUG = true

	let sessionId = localStorage.getItem(STORAGE_SESSION_KEY) || null
	let chatOpen = false
	let isWaiting = false
	let sessionCreationPromise = null
	let messageCounter = 0 // ✅ NOWE: Licznik wiadomości

	function log(message, data = null) {
		if (!DEBUG) return
		const timestamp = new Date().toISOString().split('T')[1].slice(0, -1)
		if (data !== null && data !== undefined) {
			console.log(`[${timestamp}] [PEWIK Chatbot] ${message}`, data)
		} else {
			console.log(`[${timestamp}] [PEWIK Chatbot] ${message}`)
		}
	}

	function logError(message, error = null) {
		const timestamp = new Date().toISOString().split('T')[1].slice(0, -1)
		if (error !== null && error !== undefined) {
			console.error(`[${timestamp}] [PEWIK Chatbot ERROR] ${message}`, error)
		} else {
			console.error(`[${timestamp}] [PEWIK Chatbot ERROR] ${message}`)
		}
	}

	$(document).ready(function () {
		log('=== Inicjalizacja chatbota ===')
		log('Session ID z localStorage:', sessionId)

		initializeChatbot()

		// Sprawdź ważność sesji tylko lokalnie (bez tworzenia nowej)
		if (sessionId && !checkSessionValidity()) {
			log('Sesja w localStorage jest nieważna, czyszczenie...')
			clearSession()
		}

		// Automatyczne otwarcie chatbota gdy URL zawiera #ai
		if (window.location.hash === '#ai') {
			log('Wykryto hash #ai - automatyczne otwarcie chatbota z efektem poświaty')
			// Małe opóźnienie żeby DOM się w pełni załadował
			setTimeout(function() {
				openChat()
				// Dodaj efekt pulsującej poświaty
				$('#pewik-chatbot-window').addClass('glow-attention')
				// Usuń efekt po 8 sekundach lub po pierwszej interakcji
				setTimeout(function() {
					$('#pewik-chatbot-window').removeClass('glow-attention')
				}, 8000)
				// Usuń efekt gdy użytkownik zacznie pisać
				$('#pewik-chatbot-input').one('focus', function() {
					$('#pewik-chatbot-window').removeClass('glow-attention')
				})
				// Opcjonalnie usuń hash z URL (bez przeładowania strony)
				if (history.replaceState) {
					history.replaceState(null, null, window.location.pathname + window.location.search)
				}
			}, 300)
		}
	})

	function checkSessionValidity() {
		log('Sprawdzam ważność sesji...')

		if (!sessionId) {
			log('Brak sessionId')
			return false
		}

		const sessionTime = localStorage.getItem(STORAGE_SESSION_TIME_KEY)
		if (!sessionTime) {
			log('Brak session time, sesja nieważna')
			return false
		}

		const elapsed = Date.now() - parseInt(sessionTime)
		const elapsedMinutes = Math.floor(elapsed / 60000)

		log(`Sesja ma ${elapsedMinutes} minut (limit: ${SESSION_TIMEOUT / 60000} minut)`)

		if (elapsed > SESSION_TIMEOUT) {
			log('Sesja przekroczyła limit czasu, czyszczenie...')
			clearSession()
			return false
		}

		log('Sesja jest ważna')
		return true
	}

	function updateSessionTime() {
		if (sessionId) {
			const now = Date.now()
			localStorage.setItem(STORAGE_SESSION_TIME_KEY, now.toString())
			log('Zaktualizowano timestamp sesji:', new Date(now).toLocaleTimeString())
		}
	}

	function clearSession() {
		log('Czyszczenie sesji:', sessionId)
		sessionId = null
		sessionCreationPromise = null
		localStorage.removeItem(STORAGE_SESSION_KEY)
		localStorage.removeItem(STORAGE_SESSION_TIME_KEY)
	}

	function initializeChatbot() {
		$('#pewik-chatbot-button').on('click', toggleChat)
		$('#pewik-chatbot-close').on('click', closeChat)
		$('#pewik-chatbot-reset').on('click', resetConversation)
		$('#pewik-chatbot-send').on('click', handleSendMessage)

		$('#pewik-chatbot-input').on('keypress', function (e) {
			if (e.which === 13 && !e.shiftKey) {
				e.preventDefault()
				handleSendMessage()
			}
		})

		$('#pewik-chatbot-input').on('input', function () {
			const length = $(this).val().length
			if (length > MAX_MESSAGE_LENGTH) {
				$(this).val($(this).val().substring(0, MAX_MESSAGE_LENGTH))
			}
		})

		$(document).on('click', '.rating-btn', handleRatingClick)

		setTimeout(function () {
			$('#pewik-chatbot-button').addClass('pulse-animation')
		}, 3000)

		log('Chatbot zainicjalizowany')
	}

	function loadPreviousMessages() {
		if (!sessionId) return

		try {
			const savedMessages = localStorage.getItem(STORAGE_MESSAGES_KEY)
			if (savedMessages) {
				const messages = JSON.parse(savedMessages)

				// Sprawdź czy są już jakieś wiadomości (poza powitalną)
				const currentMessages = $('#pewik-chatbot-messages .message').not('.initial-message')
				if (currentMessages.length > 0) {
					log('Wiadomości już załadowane, pomijam')
					return
				}

				log(`Ładuję ${messages.length} poprzednich wiadomości`)

				// Usuń tylko wiadomość powitalną, jeśli istnieje
				$('#pewik-chatbot-messages .initial-message').remove()

				messages.forEach(function (msg) {
					addMessageToUI(msg.type, msg.text, false, msg.messageId || null)
				})
				scrollToBottom()
			}
		} catch (e) {
			logError('Błąd ładowania wiadomości:', e)
		}
	}

	function saveMessage(type, text, messageId = null) {
		try {
			let messages = []
			const saved = localStorage.getItem(STORAGE_MESSAGES_KEY)
			if (saved) {
				messages = JSON.parse(saved)
			}

			// ✅ NOWE: Zapisuj również messageId
			messages.push({
				type: type,
				text: text,
				messageId: messageId,
				timestamp: Date.now(),
			})

			if (messages.length > 50) {
				messages = messages.slice(-50)
			}

			localStorage.setItem(STORAGE_MESSAGES_KEY, JSON.stringify(messages))
		} catch (e) {
			logError('Błąd zapisywania wiadomości:', e)
		}
	}

	function toggleChat() {
		if (chatOpen) {
			closeChat()
		} else {
			openChat()
		}
	}

	function openChat() {
		chatOpen = true
		log('Otwieranie chatbota')

		$('#pewik-chatbot-window').fadeIn(300)
		$('#pewik-chatbot-button').addClass('active')
		$('#pewik-chatbot-input').focus()

		// Załaduj poprzednie wiadomości jeśli sesja istnieje
		if (sessionId && checkSessionValidity()) {
			loadPreviousMessages()
		}

		scrollToBottom()

		// Utwórz sesję tylko jeśli nie istnieje lub jest nieważna
		ensureValidSession()
	}

	function closeChat() {
		chatOpen = false
		log('Zamykanie chatbota (sesja i historia zachowane)')

		$('#pewik-chatbot-window').fadeOut(300)
		$('#pewik-chatbot-button').removeClass('active')

		// NIE czyścimy sesji - zostaje zachowana przez SESSION_TIMEOUT (10 minut)
		// NIE czyścimy wiadomości - użytkownik zobaczy historię przy ponownym otwarciu
	}

	function resetConversation() {
		log('=== RESETOWANIE KONWERSACJI ===')

		// Wyczyść sesję i historię
		clearSession()
		localStorage.removeItem(STORAGE_MESSAGES_KEY)
		messageCounter = 0

		// Wyczyść UI
		$('#pewik-chatbot-messages').html(`
			<div class="message bot-message initial-message">
				Cześć! W czym mogę pomóc? Jestem wirtualnym asystentem, korzystającym z informacji zawartych na stronie. Mogę pomóc Ci w odnalezieniu poszukiwanych informacji.
			</div>
		`)

		// Zresetuj input
		$('#pewik-chatbot-input').val('').prop('disabled', false)
		$('#pewik-chatbot-send').prop('disabled', false)
		isWaiting = false

		log('Konwersacja zresetowana, tworzę nową sesję...')

		// Utwórz nową sesję
		ensureValidSession()
	}

	async function ensureValidSession() {
		log('=== Sprawdzam sesję ===')
		log('Obecny sessionId:', sessionId)

		if (sessionId && checkSessionValidity()) {
			log('Sesja OK, używam istniejącej')
			return true
		}

		log('Potrzebna nowa sesja')

		if (sessionCreationPromise) {
			log('Sesja jest już tworzona, czekam na zakończenie...')
			try {
				await sessionCreationPromise
				log('Sesja utworzona podczas oczekiwania:', sessionId)
				return sessionId !== null
			} catch (error) {
				logError('Błąd podczas oczekiwania na sesję:', error)
				return false
			}
		}

		sessionCreationPromise = createNewSession()

		try {
			await sessionCreationPromise
			log('Po utworzeniu, sessionId:', sessionId)
			return sessionId !== null
		} catch (error) {
			logError('Błąd tworzenia sesji:', error)
			return false
		} finally {
			sessionCreationPromise = null
		}
	}

	function createNewSession() {
		log('=== Tworzenie nowej sesji ===')
		log('URL:', pewikChatbot.sessionCreateUrl)

		return new Promise((resolve, reject) => {
			$.ajax({
				url: pewikChatbot.sessionCreateUrl,
				method: 'POST',
				contentType: 'application/json',
				timeout: 30000,
				beforeSend: function (xhr) {
					log('Wysyłam request utworzenia sesji...')
				},
				success: function (response) {
					log('Odpowiedź z create session:', response)

					if (response.success && response.sessionId) {
						sessionId = response.sessionId
						localStorage.setItem(STORAGE_SESSION_KEY, sessionId)
						updateSessionTime()
						log('✓ Utworzono nową sesję:', sessionId)
						resolve(sessionId)
					} else {
						logError('Brak sessionId w odpowiedzi:', response)
						reject('Brak sessionId w odpowiedzi')
					}
				},
				error: function (xhr, status, error) {
					logError('Błąd tworzenia sesji:', {
						status: status,
						error: error,
						responseText: xhr.responseText,
						statusCode: xhr.status,
					})
					reject(error)
				},
				complete: function () {
					log('Request utworzenia sesji zakończony')
				},
			})
		})
	}

	async function handleSendMessage() {
		const $input = $('#pewik-chatbot-input')
		const message = $input.val().trim()

		log('=== Wysyłanie wiadomości ===')
		log('Treść:', message)
		log('isWaiting:', isWaiting)

		if (!message || isWaiting) {
			log('Pusta wiadomość lub już czekam, pomijam')
			return
		}

		addMessageToUI('user', message)
		saveMessage('user', message)

		$input.val('').prop('disabled', true)
		$('#pewik-chatbot-send').prop('disabled', true)

		showTypingIndicator()
		isWaiting = true

		log('Sprawdzam sesję w tle...')

		const hasSession = await ensureValidSession()
		log('hasSession:', hasSession, 'sessionId:', sessionId)

		if (!hasSession) {
			logError('Nie można utworzyć sesji')
			hideTypingIndicator()
			addMessageToUI('bot', 'Przepraszam, nie mogę teraz połączyć się z serwerem. Spróbuj ponownie za chwilę.', true)
			isWaiting = false
			$input.prop('disabled', false).focus()
			$('#pewik-chatbot-send').prop('disabled', false)
			return
		}

		sendMessageToAPI(message)
	}

	function sendMessageToAPI(message, retryCount = 0) {
		log('=== Wysyłanie do API ===')
		log('SessionId:', sessionId)
		log('Message:', message)
		log('Retry count:', retryCount)

		const contextData = {
			pageTitle: document.title,
			pageUrl: window.location.href,
		}

		const requestData = {
			message: message,
			sessionId: sessionId,
			context: contextData,
		}

		log('Request data:', requestData)

		$.ajax({
			url: pewikChatbot.chatUrl,
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify(requestData),
			timeout: 45000,
			beforeSend: function (xhr) {
				log('Wysyłam request do API...')
			},
			success: function (response) {
				// Nie logujemy tutaj - handleAPISuccess to zrobi
				handleAPISuccess(response, message, retryCount)
			},
			error: function (xhr, status, error) {
				logError('Błąd API:', {
					status: status,
					error: error,
					statusCode: xhr.status,
					responseText: xhr.responseText,
				})
				handleAPIError(xhr, status, error, message, retryCount)
			},
			complete: function () {
				log('Request do API zakończony')
				hideTypingIndicator()
				isWaiting = false
				$('#pewik-chatbot-input').prop('disabled', false).focus()
				$('#pewik-chatbot-send').prop('disabled', false)
			},
		})
	}

	function handleAPISuccess(response, originalMessage, retryCount) {
		log('=== Odpowiedź z API ===')
		log('Response:', response)

		if (response.session_expired || response.code === 404) {
			log('⚠️ SESJA WYGASŁA - automatyczne odnawianie')

			if (retryCount >= 2) {
				logError('Za dużo prób odnowienia sesji')
				addMessageToUI('bot', 'Przepraszam, wystąpił problem z połączeniem. Zamknij i otwórz chatbot ponownie.', true)
				return
			}

			clearSession()

			log('Tworzę nową sesję w tle...')
			ensureValidSession()
				.then(hasSession => {
					log('Nowa sesja utworzona:', sessionId)
					if (hasSession && sessionId) {
						log('Ponawiam wysyłanie wiadomości automatycznie')
						sendMessageToAPI(originalMessage, retryCount + 1)
					} else {
						logError('Nie udało się utworzyć nowej sesji')
						addMessageToUI(
							'bot',
							'Przepraszam, wystąpił problem z połączeniem. Zamknij i otwórz chatbot ponownie.',
							true
						)
					}
				})
				.catch(error => {
					logError('Promise rejected:', error)
					addMessageToUI('bot', 'Przepraszam, wystąpił problem z połączeniem.', true)
				})
			return
		}

		if (response.error) {
			logError('Response zawiera błąd:', response.message)
			addMessageToUI('bot', response.message, true)
			showRetryButton(originalMessage)
		} else {
			log('✓ Odpowiedź OK')
			updateSessionTime()

			if (response.sessionId) {
				sessionId = response.sessionId
				localStorage.setItem(STORAGE_SESSION_KEY, sessionId)
			}

			const botMessage = response.message || 'Przepraszam, nie otrzymałem odpowiedzi.'

			// ✅ NOWE: Przekaż messageId do addMessageToUI
			const messageId = response.messageId || null
			addMessageToUI('bot', botMessage, false, messageId)
			saveMessage('bot', botMessage, messageId)

			if (response.hasCitations) {
				addCitationsIndicator()
			}
		}
	}

	function handleAPIError(xhr, status, error, originalMessage, retryCount) {
		logError('=== Handle API Error ===')
		logError('XHR status:', xhr.status)
		logError('Status:', status)

		if (xhr.status === 404 && retryCount === 0) {
			log('404 - automatyczne odnawianie sesji')
			clearSession()

			ensureValidSession().then(hasSession => {
				if (hasSession && sessionId) {
					log('Sesja odnowiona, ponawiam zapytanie')
					sendMessageToAPI(originalMessage, retryCount + 1)
				}
			})
			return
		}

		let errorMessage = 'Przepraszam, wystąpił problem z połączeniem.'

		if (status === 'timeout') {
			errorMessage = 'Przekroczono czas oczekiwania na odpowiedź. Spróbuj ponownie.'
		} else if (xhr.status === 429) {
			errorMessage = 'Przekroczono limit zapytań. Spróbuj za chwilę.'
		} else if (xhr.status === 401) {
			errorMessage = 'Błąd autoryzacji. Skontaktuj się z administratorem.'
		} else if (xhr.status === 500) {
			errorMessage = 'Błąd serwera. Spróbuj ponownie za chwilę.'
		}

		addMessageToUI('bot', errorMessage, true)
		showRetryButton(originalMessage)
	}

	// ✅ ZMODYFIKOWANA: Dodano parametr messageId
	function addMessageToUI(type, text, isError = false, messageId = null) {
		messageCounter++
		const uniqueId = 'msg-' + messageCounter + '-' + Date.now()
		const messageClass = type === 'user' ? 'user-message' : 'bot-message'
		const errorClass = isError ? ' error-message' : ''

		const $message = $('<div>')
			.attr('id', uniqueId)
			.addClass('message ' + messageClass + errorClass)
			.html(formatMessage(text))
			.hide()
			.fadeIn(300)

		// ✅ NOWE: Dodaj przyciski oceny dla wiadomości bota (jeśli ma messageId)
		if (type === 'bot' && !isError && messageId) {
			$message.attr('data-message-id', messageId)

			const $ratingDiv = $('<div>').addClass('message-rating').html(`
					<span class="rating-label">Czy ta odpowiedź była pomocna?</span>
					<button class="rating-btn thumbs-up" data-rating="1" title="Pomocna odpowiedź">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
							<path d="M1 21h4V9H1v12zm22-11c0-1.1-.9-2-2-2h-6.31l.95-4.57.03-.32c0-.41-.17-.79-.44-1.06L14.17 1 7.59 7.59C7.22 7.95 7 8.45 7 9v10c0 1.1.9 2 2 2h9c.83 0 1.54-.5 1.84-1.22l3.02-7.05c.09-.23.14-.47.14-.73v-2z"/>
						</svg>
					</button>
					<button class="rating-btn thumbs-down" data-rating="-1" title="Niepomocna odpowiedź">
						<svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
							<path d="M15 3H6c-.83 0-1.54.5-1.84 1.22l-3.02 7.05c-.09.23-.14.47-.14.73v2c0 1.1.9 2 2 2h6.31l-.95 4.57-.03.32c0 .41.17.79.44 1.06L9.83 23l6.59-6.59c.36-.36.58-.86.58-1.41V5c0-1.1-.9-2-2-2zm4 0v12h4V3h-4z"/>
						</svg>
					</button>
				`)

			$message.append($ratingDiv)
			log('Dodano przyciski oceny do wiadomości ID:', messageId)
		}

		$('#pewik-chatbot-messages').append($message)
		scrollToBottom()
	}

	// ✅ NOWA FUNKCJA: Obsługa kliknięcia przycisku oceny
	function handleRatingClick(e) {
		e.preventDefault()

		const $btn = $(this)
		const $message = $btn.closest('.message')
		const messageId = $message.data('message-id')
		const rating = $btn.data('rating')

		log('=== Ocena wiadomości ===')
		log('Message ID:', messageId)
		log('Rating:', rating)

		if (!messageId) {
			logError('Brak messageId w wiadomości')
			return
		}

		// Wyłącz przyciski
		$message.find('.rating-btn').prop('disabled', true)

		// Wyślij ocenę do API
		$.ajax({
			url: pewikChatbot.chatUrl.replace('/chat', '/rate'),
			method: 'POST',
			contentType: 'application/json',
			data: JSON.stringify({
				messageId: messageId,
				rating: rating,
			}),
			timeout: 10000,
			success: function (response) {
				log('Odpowiedź z rate API:', response)

				if (response.success) {
					// Zamień przyciski na podziękowanie
					$message
						.find('.message-rating')
						.html('<span class="rating-success">✓ ' + (response.message || 'Dziękujemy za opinię!') + '</span>')

					// Jeśli negatywna ocena, poproś o feedback
					if (rating === -1) {
						setTimeout(function () {
							addMessageToUI(
								'bot',
								'Przykro mi, że odpowiedź nie była pomocna. Czy możesz napisać, co mogłoby być lepsze? Twoja opinia pomoże mi się poprawić! 😊',
								false,
								null
							)
						}, 1500)
					} else {
						log('✓ Pozytywna ocena zapisana')
					}
				} else {
					logError('Błąd w odpowiedzi rate:', response)
					$message.find('.message-rating').html('<span class="rating-error">✗ Nie udało się zapisać oceny</span>')
					$message.find('.rating-btn').prop('disabled', false)
				}
			},
			error: function (xhr, status, error) {
				logError('Błąd wysyłania oceny:', {
					status: status,
					error: error,
					statusCode: xhr.status,
				})

				$message.find('.message-rating').html('<span class="rating-error">✗ Nie udało się zapisać oceny</span>')
				$message.find('.rating-btn').prop('disabled', false)
			},
		})
	}

	function formatMessage(text) {
		// 1. Najpierw czyścimy HTML dla bezpieczeństwa
		text = $('<div>').text(text).html()

		// 2. NOWE: Obsługa linków Markdown [Tekst](URL)
		// To zamienia [e-BOK](https://...) na klikalny link
		text = text.replace(
			/\[([^\]]+)\]\((https?:\/\/[^\)]+)\)/g,
			'<a href="$2" target="_blank" rel="noopener"><strong>$1</strong></a>'
		)

		// 3. Obsługa nowych linii
		text = text.replace(/\n/g, '<br>')

		// 4. Obsługa surowych linków (takich, które nie były w nawiasach)
		// Regex ignoruje linki, które są już wewnątrz atrybutu href (czyli te zrobione w pkt 2)
		text = text.replace(/(?<!href="|">)(https?:\/\/[^\s<"'\)]+)/g, '<a href="$1" target="_blank" rel="noopener">$1</a>')

		// 5. Formatowanie tekstu (pogrubienia, kursywa)
		text = text.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
		text = text.replace(/\*([^*]+)\*/g, '<em>$1</em>')
		text = text.replace(/`([^`]+)`/g, '<code>$1</code>')

		// 6. Numery telefonów
		text = text.replace(/(\d{2}\s?\d{2}\s?\d{2}\s?\d{3})/g, '<a href="tel:$1">$1</a>')
		text = text.replace(/\b994\b/g, '<a href="tel:994"><strong>994</strong></a>')

		return text
	}

	function showTypingIndicator() {
		const $indicator = $('<div>')
			.addClass('message bot-message typing-indicator')
			.html('<span class="typing-dots"><span>.</span><span>.</span><span>.</span></span> Pisze...')
			.hide()
			.fadeIn(200)

		$('#pewik-chatbot-messages').append($indicator)
		scrollToBottom()
	}

	function hideTypingIndicator() {
		$('.typing-indicator').fadeOut(200, function () {
			$(this).remove()
		})
	}

	function showRetryButton(lastMessage) {
		const $retryBtn = $('<button>')
			.addClass('retry-button')
			.html('🔄 Spróbuj ponownie')
			.on('click', function () {
				$(this).remove()
				if (lastMessage) {
					$('#pewik-chatbot-input').val(lastMessage)
					handleSendMessage()
				}
			})

		$('#pewik-chatbot-messages').append($retryBtn)
		scrollToBottom()
	}

	function addCitationsIndicator() {
		const $citation = $('<div>')
			.addClass('citation-indicator')
			.html('📚 <small>Odpowiedź oparta na dokumentacji PEWIK</small>')

		$('.message:last').append($citation)
	}

	function scrollToBottom() {
		const $messages = $('#pewik-chatbot-messages')
		$messages.animate(
			{
				scrollTop: $messages[0].scrollHeight,
			},
			300
		)
	}

	// ✅ NOWA: Funkcja globalna do debugowania
	window.pewikChatbotStatus = function () {
		console.log('=== STATUS CHATBOTA ===')
		console.log('Session ID:', sessionId)
		console.log('Session time:', localStorage.getItem(STORAGE_SESSION_TIME_KEY))
		console.log('Is valid:', checkSessionValidity())
		console.log('Chat open:', chatOpen)
		console.log('Is waiting:', isWaiting)
		console.log('Message counter:', messageCounter)
	}

	window.resetPewikChatbot = function () {
		log('=== RESET CHATBOTA ===')
		clearSession()
		localStorage.removeItem(STORAGE_MESSAGES_KEY)
		messageCounter = 0
		$('#pewik-chatbot-messages').html(`
			<div class="message bot-message initial-message">
				👋 Witaj! Jestem asystentem PEWIK Gdynia. Jak mogę Ci pomóc?
			</div>
		`)
		ensureValidSession()
	}
})(jQuery)
