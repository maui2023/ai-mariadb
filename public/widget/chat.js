/**
 * AiMariaDb Chatbot Widget - Vanilla JS Drop-in (Premium & Mobile-Friendly)
 * Memuatkan antara muka perbualan moden dengan sokongan rendering Markdown,
 * layout responsif mesra mudah alih, dan perlindungan bebas halusinasi.
 */
(function () {
    // 1. Dapatkan tetapan daripada atribut script tag (menyokong async, defer, dan pelbagai website pelanggan)
    const findWidgetScript = () => {
        if (document.currentScript) return document.currentScript;
        
        const byId = document.getElementById('ai-mariadb-script');
        if (byId) return byId;

        const candidate = document.querySelector('script[src*="chat.js"], script[data-api*="chat.php"]');
        if (candidate) return candidate;

        const scripts = document.getElementsByTagName('script');
        for (let i = scripts.length - 1; i >= 0; i--) {
            if (scripts[i].src && scripts[i].src.includes('chat.js')) {
                return scripts[i];
            }
        }
        return null;
    };

    const currentScript = findWidgetScript();
    let scriptSrc = currentScript?.src || '';

    // Kenal pasti asal pelayan (Origin) daripada script.src atau tetapan global
    let defaultOrigin = 'https://chat.kpst.my';
    if (scriptSrc && (scriptSrc.startsWith('http://') || scriptSrc.startsWith('https://'))) {
        try {
            defaultOrigin = new URL(scriptSrc).origin;
        } catch (e) {}
    }

    // Bina URL API: pastikan sentiasa menghala ke domain pelayan AI (bukan domain pelanggan)
    let apiUrl = (typeof window !== 'undefined' && window.AI_CHAT_API) || currentScript?.getAttribute('data-api');
    if (!apiUrl) {
        apiUrl = defaultOrigin + '/api/chat.php';
    } else if (apiUrl.startsWith('/')) {
        apiUrl = defaultOrigin + apiUrl;
    }

    // Auto Upgrade ke HTTPS sekiranya laman web pelanggan menggunakan HTTPS (mengelakkan sekatan Mixed Content)
    if (typeof window !== 'undefined' && window.location && window.location.protocol === 'https:') {
        if (apiUrl.startsWith('http://')) {
            apiUrl = apiUrl.replace(/^http:\/\//i, 'https://');
        }
        if (scriptSrc.startsWith('http://')) {
            scriptSrc = scriptSrc.replace(/^http:\/\//i, 'https://');
        }
        if (defaultOrigin.startsWith('http://')) {
            defaultOrigin = defaultOrigin.replace(/^http:\/\//i, 'https://');
        }
    }

    const widgetTitle = currentScript?.getAttribute('data-title') || 'Pembantu Kedai AI';
    const widgetGreeting = currentScript?.getAttribute('data-greeting') || 'Hai! 👋 Selamat datang. Ada apa yang boleh saya bantu mengenai produk, harga, atau promosi kami?';
    const customChipsAttr = currentScript?.getAttribute('data-chips');

    // 2. Muat turun CSS widget secara automatik dari domain pelayan AI
    if (!document.getElementById('ai-chat-css')) {
        const cssLink = document.createElement('link');
        cssLink.id = 'ai-chat-css';
        cssLink.rel = 'stylesheet';
        let cssPath = scriptSrc ? scriptSrc.replace(/\.js(\?.*)?$/, '.css$1') : defaultOrigin + '/widget/chat.css';
        if (!cssPath.startsWith('http')) {
            cssPath = defaultOrigin + (cssPath.startsWith('/') ? '' : '/') + cssPath;
        }
        if (typeof window !== 'undefined' && window.location && window.location.protocol === 'https:' && cssPath.startsWith('http://')) {
            cssPath = cssPath.replace(/^http:\/\//i, 'https://');
        }
        cssLink.href = cssPath;
        document.head.appendChild(cssLink);
    }

    function renderQuickChipsHtml() {
        if (customChipsAttr === 'none') return '';
        if (customChipsAttr) {
            const list = customChipsAttr.split(/[,|]/).map(s => s.trim()).filter(Boolean);
            if (list.length > 0) {
                const buttons = list.map(q => `<button class="ai-chip" data-q="${escapeHtmlOnly(q)}">💬 ${escapeHtmlOnly(q)}</button>`).join('');
                return `<div class="ai-quick-chips" id="ai-quick-chips">${buttons}</div>`;
            }
        }
        return `
            <div class="ai-quick-chips" id="ai-quick-chips">
                <button class="ai-chip" data-q="Ada sebarang promosi atau diskaun semasa?">🎉 Promosi & Diskaun</button>
                <button class="ai-chip" data-q="Berapa kos dan tempoh penghantaran pos?">📦 Kos Penghantaran</button>
                <button class="ai-chip" data-q="Bila waktu operasi kedai dan hari apa buka?">⏰ Waktu Operasi</button>
                <button class="ai-chip" data-q="Apakah produk utama yang anda tawarkan?">🛍️ Pilihan Produk</button>
            </div>
        `;
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
                        <div class="ai-chat-title">${escapeHtmlOnly(widgetTitle)}</div>
                        <div class="ai-chat-status">
                            <span>Aktif sekarang</span>
                        </div>
                    </div>
                </div>
                <div class="ai-chat-header-actions">
                    <button class="ai-chat-action-btn" id="ai-chat-reset" title="Mula Semula Perbualan">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"></path>
                            <path d="M3 3v5h5"></path>
                        </svg>
                    </button>
                    <button class="ai-chat-action-btn" id="ai-chat-close" title="Tutup Chat">
                        <svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
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
                        <div class="ai-bubble">
                            <div class="ai-msg-text">${formatChatMessage(widgetGreeting)}</div>
                            <span class="ai-msg-time">Baru saja</span>
                        </div>
                    </div>
                </div>
                ${renderQuickChipsHtml()}
            </div>

            <div class="ai-chat-input-container">
                <div class="ai-chat-input-box">
                    <input type="text" class="ai-chat-input" id="ai-chat-input-text" placeholder="Tanya apa sahaja..." autocomplete="off">
                    <div class="ai-input-actions">
                        <button class="ai-action-icon-btn" id="ai-emoji-btn" type="button" title="Emoji">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="12" r="10"></circle>
                                <path d="M8 14s1.5 2 4 2 4-2 4-2"></path>
                                <line x1="9" y1="9" x2="9.01" y2="9"></line>
                                <line x1="15" y1="9" x2="15.01" y2="9"></line>
                            </svg>
                        </button>
                        <button class="ai-chat-send-btn" id="ai-chat-send-trigger" title="Hantar Mesej">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="22" y1="2" x2="11" y2="13"></line>
                                <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="ai-chat-footer-badge">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                    <span>Dikuasakan oleh AI MariaDB · Bebas Halusinasi</span>
                </div>
            </div>
        </div>

        <div class="ai-chat-trigger-wrap">
            <div class="ai-chat-teaser-badge" id="ai-chat-teaser">
                <span>Tanya AI</span>
                <span>👋</span>
            </div>
            <button id="ai-chat-bubble" title="Buka Bantuan AI" aria-label="Buka Chat AI">
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
        </div>
    `;

    document.body.appendChild(rootContainer);

    // 4. Logik Interaksi UI & AJAX
    const chatBubble = document.getElementById('ai-chat-bubble');
    const chatTeaser = document.getElementById('ai-chat-teaser');
    const chatWindow = document.getElementById('ai-chat-window');
    const closeBtn = document.getElementById('ai-chat-close');
    const resetBtn = document.getElementById('ai-chat-reset');
    const msgList = document.getElementById('ai-chat-msg-list');
    const inputField = document.getElementById('ai-chat-input-text');
    const sendBtn = document.getElementById('ai-chat-send-trigger');
    const quickChips = document.getElementById('ai-quick-chips');

    function toggleChat(forceOpen = null) {
        const isOpen = forceOpen !== null ? forceOpen : !chatWindow.classList.contains('ai-chat-open');
        
        if (isOpen) {
            chatWindow.classList.add('ai-chat-open');
            chatBubble.classList.add('ai-bubble-active');
            if (chatTeaser) chatTeaser.style.display = 'none';
            setTimeout(() => {
                inputField.focus();
                scrollToBottom();
            }, 250);
        } else {
            chatWindow.classList.remove('ai-chat-open');
            chatBubble.classList.remove('ai-bubble-active');
            if (chatTeaser && window.innerWidth > 640) chatTeaser.style.display = 'flex';
        }
    }

    chatBubble.addEventListener('click', () => toggleChat());
    if (chatTeaser) chatTeaser.addEventListener('click', () => toggleChat(true));
    closeBtn.addEventListener('click', () => toggleChat(false));

    // Reset perbualan
    resetBtn.addEventListener('click', () => {
        chatHistory.length = 0;
        msgList.innerHTML = `
            <div class="ai-message ai-message-bot">
                <div class="ai-msg-row">
                    <div class="ai-msg-avatar">👩‍💼</div>
                    <div class="ai-bubble">
                        <div class="ai-msg-text">${formatChatMessage(widgetGreeting)}</div>
                        <span class="ai-msg-time">${getCurrentTime()}</span>
                    </div>
                </div>
            </div>
            ${renderQuickChipsHtml()}
        `;
        const newChips = document.getElementById('ai-quick-chips');
        if (newChips) {
            newChips.addEventListener('click', onChipClick);
        }
    });

    function getCurrentTime() {
        const now = new Date();
        return now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function scrollToBottom() {
        msgList.scrollTop = msgList.scrollHeight;
    }

    function escapeHtmlOnly(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Parser Markdown Mesra Pengguna untuk format Chatbot:
     * - Menukar **bold** kepada <strong>
     * - Menguruskan tajuk emoji (👟 Pilihan Produk, 🎉 Promosi, dll)
     * - Membina senarai kemas (•) berserta sub-penerangan yang teratur
     * - Menghilangkan teks mentah SQL dan tanda kurung teknikal
     */
    function formatChatMessage(rawText) {
        if (!rawText) return '';

        // 1. Escaping asas untuk keselamatan XSS
        let text = rawText
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');

        // 2. Bold (**teks**)
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');

        // 3. Italic (*teks* atau _teks_)
        text = text.replace(/(?<!\*)\*(?!\*)(.+?)(?<!\*)\*(?!\*)/g, '<em>$1</em>');

        // 4. Tag harga Ringgit Malaysia
        text = text.replace(/\b(RM\s*\d+(?:\.\d{2})?)\b/g, '<span class="ai-price-tag">$1</span>');

        // 5. Lencana status / stok
        text = text.replace(/\b(Baki stok:\s*\d+\s*unit)/g, '<span class="ai-badge ai-badge-stock">$1</span>');
        text = text.replace(/\b(Habis stok[^\n<]*)/g, '<span class="ai-badge ai-badge-out">$1</span>');
        text = text.replace(/\b(Diskaun\s*\d+%)/g, '<span class="ai-badge ai-badge-promo">$1</span>');

        // 6. Pisahkan baris dan bina elemen berstruktur
        const lines = text.split('\n');
        const output = [];
        let inList = false;

        for (let i = 0; i < lines.length; i++) {
            const line = lines[i];
            const trimmed = line.trim();

            if (!trimmed) {
                if (inList) {
                    output.push('</ul>');
                    inList = false;
                }
                continue;
            }

            // Semak jika baris adalah item senarai bullet (• atau - atau *)
            const bulletMatch = trimmed.match(/^([•\-\*])\s+(.+)$/);
            if (bulletMatch) {
                if (!inList) {
                    output.push('<ul class="ai-styled-list">');
                    inList = true;
                }
                output.push(`<li><span class="ai-bullet-dot">•</span><div class="ai-list-body"><span class="ai-list-title">${bulletMatch[2]}</span></div></li>`);
                continue;
            }

            // Semak jika baris adalah penerangan sambungan kepada item senarai sebelumnya
            if (inList && (line.startsWith('  ') || line.startsWith('\t'))) {
                const lastIdx = output.length - 1;
                if (lastIdx >= 0 && output[lastIdx].endsWith('</div></li>')) {
                    output[lastIdx] = output[lastIdx].replace('</div></li>', `<span class="ai-list-desc">${trimmed}</span></div></li>`);
                    continue;
                }
            }

            if (inList) {
                output.push('</ul>');
                inList = false;
            }

            // Semak jika baris adalah tajuk seksyen dengan emoji (cth: 👟 Pilihan Produk, 🎉 Tawaran)
            const isEmojiHeader = trimmed.match(/^([\p{Extended_Pictographic}\u{1F300}-\u{1F9FF}\u{2600}-\u{26FF}\u{2700}-\u{27BF}]+)\s*(.*)$/u);
            if (isEmojiHeader && (trimmed.includes('<strong>') || trimmed.includes(':'))) {
                output.push(`<div class="ai-section-heading"><span class="ai-section-icon">${isEmojiHeader[1]}</span> ${isEmojiHeader[2]}</div>`);
            } else {
                output.push(`<p class="ai-msg-para">${trimmed}</p>`);
            }
        }

        if (inList) {
            output.push('</ul>');
        }

        return output.join('');
    }

    function addMessage(text, isUser = false) {
        const msgDiv = document.createElement('div');
        msgDiv.className = `ai-message ${isUser ? 'ai-message-user' : 'ai-message-bot'}`;
        
        const timeStr = getCurrentTime();

        if (isUser) {
            // Mesej pengguna disanitasi
            const safeUserText = escapeHtmlOnly(text).replace(/\n/g, '<br>');
            msgDiv.innerHTML = `
                <div class="ai-bubble">
                    <div class="ai-msg-text">${safeUserText}</div>
                    <span class="ai-msg-time">${timeStr}</span>
                </div>
            `;
        } else {
            // Mesej bot diformat melalui markdown parser
            const formattedContent = formatChatMessage(text);
            msgDiv.innerHTML = `
                <div class="ai-msg-row">
                    <div class="ai-msg-avatar">👩‍💼</div>
                    <div class="ai-bubble">
                        <div class="ai-msg-text">${formattedContent}</div>
                        <span class="ai-msg-time">${timeStr}</span>
                    </div>
                </div>
            `;
        }

        msgList.appendChild(msgDiv);
        scrollToBottom();
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

        // Sembunyikan quick chips selepas mesej pertama dihantar
        const chipsEl = document.getElementById('ai-quick-chips');
        if (chipsEl) {
            chipsEl.style.display = 'none';
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

            if (!res.ok) {
                let errText = '';
                try {
                    const errObj = await res.json();
                    if (errObj && (errObj.error || errObj.answer)) {
                        errText = errObj.error || errObj.answer;
                    }
                } catch (_) {}
                throw new Error(errText || `Ralat sambungan (HTTP ${res.status})`);
            }

            const data = await res.json();
            hideTyping();

            if (data && data.answer) {
                chatHistory.push({ role: 'assistant', content: data.answer });
                if (chatHistory.length > 10) {
                    chatHistory.splice(0, chatHistory.length - 10);
                }
                addMessage(data.answer, false);
            } else if (data && data.error) {
                addMessage(`Ralat: ${data.error}`, false);
            } else {
                addMessage('Maaf, tiada jawapan diterima daripada pelayan.', false);
            }
        } catch (err) {
            console.error('[AiMariaDb Widget Error]', err);
            hideTyping();
            addMessage('Maaf, terdapat ralat semasa menyambung ke perkhidmatan chatbot. Sila pastikan sambungan internet dan URL API adalah sah.', false);
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
        const friendlyEmojis = ['😊', '👟', '👕', '🕒', '👍', '🙏'];
        let emojiIdx = 0;
        emojiBtn.addEventListener('click', () => {
            const em = friendlyEmojis[emojiIdx % friendlyEmojis.length];
            emojiIdx++;
            inputField.value += ' ' + em + ' ';
            inputField.focus();
        });
    }

    function onChipClick(e) {
        const chip = e.target.closest('.ai-chip');
        if (chip && chip.dataset.q) {
            handleSend(chip.dataset.q);
        }
    }

    if (quickChips) {
        quickChips.addEventListener('click', onChipClick);
    }
})();
