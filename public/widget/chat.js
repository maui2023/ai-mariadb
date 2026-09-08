/**
 * AiMariaDb Chatbot Widget - Vanilla JS Drop-in
 * Hanya masukkan 1 baris skrip ke mana-mana laman web PHP / HTML.
 */
(function () {
    // 1. Dapatkan tetapan daripada atribut script tag atau konfigurasi global
    const currentScript = document.currentScript || (function () {
        const scripts = document.getElementsByTagName('script');
        return scripts[scripts.length - 1];
    })();

    const apiUrl = currentScript?.getAttribute('data-api') || '/api/chat.php';
    const widgetTitle = currentScript?.getAttribute('data-title') || 'Pembantu Kedai AI';
    const widgetGreeting = currentScript?.getAttribute('data-greeting') || 'Hai! 👋 Ada apa yang boleh saya bantu mengenai produk, stok atau waktu kedai kami?';

    // 2. Muat turun CSS widget secara automatik sekiranya belum ada
    if (!document.getElementById('ai-chat-css')) {
        const cssLink = document.createElement('link');
        cssLink.id = 'ai-chat-css';
        cssLink.rel = 'stylesheet';
        // Ambil path relatif kepada chat.js
        const scriptSrc = currentScript?.src || '';
        const cssPath = scriptSrc ? scriptSrc.replace(/\.js$/, '.css') : '/widget/chat.css';
        cssLink.href = cssPath;
        document.head.appendChild(cssLink);
    }

    // 3. Bina elemen HTML widget
    const rootContainer = document.createElement('div');
    rootContainer.id = 'ai-chat-root';

    rootContainer.innerHTML = `
        <div id="ai-chat-window">
            <div class="ai-chat-header">
                <div class="ai-chat-header-info">
                    <div class="ai-chat-avatar">🤖</div>
                    <div>
                        <div class="ai-chat-title">${widgetTitle}</div>
                        <div class="ai-chat-status">
                            <span class="ai-chat-status-dot"></span>
                            <span>Berpandukan Database Sahaja</span>
                        </div>
                    </div>
                </div>
                <button class="ai-chat-close-btn" id="ai-chat-close" title="Tutup">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 6L6 18M6 6l12 12"></path>
                    </svg>
                </button>
            </div>

            <div class="ai-chat-messages" id="ai-chat-msg-list">
                <div class="ai-message ai-message-bot">
                    <div class="ai-bubble">${widgetGreeting}</div>
                </div>
                <div class="ai-quick-chips" id="ai-quick-chips">
                    <button class="ai-chip" data-q="Ada stok kasut saiz 42 tak?">👟 Stok Kasut</button>
                    <button class="ai-chip" data-q="Buka kedai hari Ahad tak?">🕒 Waktu Kedai</button>
                    <button class="ai-chip" data-q="Berapa harga baju melayu?">👕 Baju Melayu</button>
                </div>
            </div>

            <div class="ai-chat-input-box">
                <input type="text" class="ai-chat-input" id="ai-chat-input-text" placeholder="Tanya soalan stok, kedai..." autocomplete="off">
                <button class="ai-chat-send-btn" id="ai-chat-send-trigger" title="Hantar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                </button>
            </div>
            <div class="ai-chat-footer-badge">Dikuasakan oleh MariaDB & Ollama embeddinggemma</div>
        </div>

        <button id="ai-chat-bubble" title="Buka Pembantu AI">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
            </svg>
        </button>
    `;

    document.body.appendChild(rootContainer);

    // 4. Logik Interaksi UI & AJAX
    const chatBubble = document.getElementById('ai-chat-bubble');
    const chatWindow = document.getElementById('ai-chat-window');
    const closeBtn = document.getElementById('ai-chat-close');
    const msgList = document.getElementById('ai-chat-msg-list');
    const inputField = document.getElementById('ai-chat-input-text');
    const sendBtn = document.getElementById('ai-chat-send-trigger');
    const quickChips = document.getElementById('ai-quick-chips');

    function toggleChat() {
        chatWindow.classList.toggle('ai-chat-open');
        if (chatWindow.classList.contains('ai-chat-open')) {
            setTimeout(() => inputField.focus(), 300);
        }
    }

    chatBubble.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    function scrollToBottom() {
        msgList.scrollTop = msgList.scrollHeight;
    }

    function addMessage(text, isUser = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-message ${isUser ? 'ai-message-user' : 'ai-message-bot'}`;
        
        const bubble = document.createElement('div');
        bubble.className = 'ai-bubble';
        bubble.textContent = text;

        msgDiv.appendChild(bubble);
        msgList.appendChild(msgDiv);
        scrollToBottom();
    }

    function showTyping() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'ai-message ai-message-bot';
        typingDiv.id = 'ai-typing-active';
        typingDiv.innerHTML = `
            <div class="ai-typing-indicator">
                <span class="ai-typing-dot"></span>
                <span class="ai-typing-dot"></span>
                <span class="ai-typing-dot"></span>
            </div>
        `;
        msgList.appendChild(typingDiv);
        scrollToBottom();
    }

    function hideTyping() {
        const typing = document.getElementById('ai-typing-active');
        if (typing) {
            typing.remove();
        }
    }

    async function handleSend(customText = null) {
        const text = customText || inputField.value.trim();
        if (!text) return;

        if (!customText) {
            inputField.value = '';
        }

        // Sembunyikan quick chips selepas soalan pertama
        if (quickChips) {
            quickChips.style.display = 'none';
        }

        addMessage(text, true);
        showTyping();

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ message: text }),
            });

            const data = await res.json();
            hideTyping();

            if (data.answer) {
                addMessage(data.answer, false);
            } else {
                addMessage('Maaf, tiada jawapan diterima.', false);
            }
        } catch (err) {
            hideTyping();
            addMessage('Ralat sambungan ke pelayan chatbot.', false);
        }
    }

    sendBtn.addEventListener('click', () => handleSend());
    inputField.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            handleSend();
        }
    });

    if (quickChips) {
        quickChips.addEventListener('click', (e) => {
            const chip = e.target.closest('.ai-chip');
            if (chip && chip.dataset.q) {
                handleSend(chip.dataset.q);
            }
        });
    }
})();
