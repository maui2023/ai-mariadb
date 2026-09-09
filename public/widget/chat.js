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
                    <div class="ai-chat-avatar-wrapper">
                        <div class="ai-chat-avatar">👩‍💼</div>
                        <span class="ai-chat-status-dot"></span>
                    </div>
                    <div class="ai-chat-header-text">
                        <div class="ai-chat-title">${widgetTitle}</div>
                        <div class="ai-chat-status">Aktif sekarang</div>
                    </div>
                </div>
                <div class="ai-chat-header-actions">
                    <button class="ai-chat-dots-btn" title="Pilihan">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="5" cy="12" r="2"/>
                            <circle cx="12" cy="12" r="2"/>
                            <circle cx="19" cy="12" r="2"/>
                        </svg>
                    </button>
                    <button class="ai-chat-close-btn" id="ai-chat-close" title="Tutup">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                            <line x1="18" y1="6" x2="6" y2="18"></line>
                            <line x1="6" y1="6" x2="18" y2="18"></line>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="ai-chat-messages" id="ai-chat-msg-list">
                <div class="ai-message ai-message-bot">
                    <div class="ai-msg-row">
                        <div class="ai-msg-avatar">👩‍💼</div>
                        <div class="ai-bubble"><div class="ai-msg-text">${widgetGreeting}</div><span class="ai-msg-time">Baru saja</span></div>
                    </div>
                </div>
                <div class="ai-quick-chips" id="ai-quick-chips">
                    <button class="ai-chip" data-q="Ada stok kasut saiz 42 tak?">👟 Stok Kasut</button>
                    <button class="ai-chip" data-q="Buka kedai hari Ahad tak?">🕒 Waktu Kedai</button>
                    <button class="ai-chip" data-q="Berapa harga baju melayu?">👕 Baju Melayu</button>
                </div>
            </div>

            <div class="ai-chat-input-container">
                <div class="ai-chat-input-box">
                    <input type="text" class="ai-chat-input" id="ai-chat-input-text" placeholder="Tulis mesej anda..." autocomplete="off">
                    <div class="ai-input-actions">
                        <button class="ai-action-icon-btn" id="ai-emoji-btn" type="button" title="Emoji">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                        </button>
                        <button class="ai-chat-send-btn" id="ai-chat-send-trigger" title="Hantar">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="ai-chat-footer-badge">Dilindungi AI MariaDB &bull; Bebas Halusinasi Database</div>
            </div>
        </div>

        <button id="ai-chat-bubble" title="Buka Bantuan AI">
            <span class="ai-bubble-icon-chat">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </span>
            <span class="ai-bubble-icon-close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </span>
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
        chatBubble.classList.toggle('ai-bubble-active');
        if (chatWindow.classList.contains('ai-chat-open')) {
            setTimeout(() => inputField.focus(), 300);
        }
    }

    chatBubble.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() {
        msgList.scrollTop = msgList.scrollHeight;
    }

    function addMessage(text, isUser = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-message ${isUser ? 'ai-message-user' : 'ai-message-bot'}`;
        
        const timeStr = getCurrentTime();

        if (isUser) {
            msgDiv.innerHTML = `<div class="ai-bubble"><div class="ai-msg-text">${escapeHtml(text)}</div><span class="ai-msg-time">${timeStr}</span></div>`;
        } else {
            msgDiv.innerHTML = `<div class="ai-msg-row"><div class="ai-msg-avatar">👩‍💼</div><div class="ai-bubble"><div class="ai-msg-text">${escapeHtml(text)}</div><span class="ai-msg-time">${timeStr}</span></div></div>`;
        }

        msgList.appendChild(msgDiv);
        scrollToBottom();
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    function showTyping() {
        const typingDiv = document.createElement('div');
        typingDiv.className = 'ai-message ai-message-bot';
        typingDiv.id = 'ai-typing-active';
        typingDiv.innerHTML = `
            <div class="ai-msg-row">
                <div class="ai-msg-avatar">👩‍💼</div>
                <div class="ai-typing-indicator">
                    <span class="ai-typing-dot"></span>
                    <span class="ai-typing-dot"></span>
                    <span class="ai-typing-dot"></span>
                </div>
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

    const chatHistory = [];

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

        const historyPayload = chatHistory.slice(-6);
        chatHistory.push({ role: 'user', content: text });

        addMessage(text, true);
        showTyping();

        try {
            const res = await fetch(apiUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    message: text,
                    history: historyPayload
                }),
            });

            const data = await res.json();
            hideTyping();

            if (data.answer) {
                chatHistory.push({ role: 'assistant', content: data.answer });
                if (chatHistory.length > 10) {
                    chatHistory.splice(0, chatHistory.length - 10);
                }
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

    const emojiBtn = document.getElementById('ai-emoji-btn');
    if (emojiBtn) {
        emojiBtn.addEventListener('click', () => {
            inputField.value += ' 😊 ';
            inputField.focus();
        });
    }

    if (quickChips) {
        quickChips.addEventListener('click', (e) => {
            const chip = e.target.closest('.ai-chip');
            if (chip && chip.dataset.q) {
                handleSend(chip.dataset.q);
            }
        });
    }
})();
