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
    const themeAttr = currentScript?.getAttribute('data-theme') || (typeof window !== 'undefined' && window.AI_CHAT_THEME) || 'purple';
    const colorAttr = currentScript?.getAttribute('data-color') || currentScript?.getAttribute('data-primary') || (typeof window !== 'undefined' && window.AI_CHAT_COLOR);
    const headerAttr = currentScript?.getAttribute('data-header') || (typeof window !== 'undefined' && window.AI_CHAT_HEADER);
    const modeAttr = currentScript?.getAttribute('data-mode') || (typeof window !== 'undefined' && window.AI_CHAT_MODE) || 'light';

    // Helper warna untuk menjana variasi tema dinamik
    function adjustHex(hex, percent) {
        hex = hex.replace(/^#/, '');
        if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
        const num = parseInt(hex, 16);
        let r = (num >> 16) + Math.round(255 * (percent / 100));
        let g = ((num >> 8) & 0x00FF) + Math.round(255 * (percent / 100));
        let b = (num & 0x0000FF) + Math.round(255 * (percent / 100));
        r = Math.min(255, Math.max(0, r));
        g = Math.min(255, Math.max(0, g));
        b = Math.min(255, Math.max(0, b));
        return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
    }

    function isColorDark(hex) {
        hex = hex.replace(/^#/, '');
        if (hex.length === 3) hex = hex.split('').map(c => c + c).join('');
        const num = parseInt(hex, 16);
        const r = (num >> 16);
        const g = ((num >> 8) & 0x00FF);
        const b = (num & 0x0000FF);
        const luma = 0.2126 * r + 0.7152 * g + 0.0722 * b;
        return luma < 140;
    }

    const THEME_PRESETS = {
        'purple': {
            '--ai-primary': '#7c3aed',
            '--ai-primary-hover': '#6d28d9',
            '--ai-primary-light': '#f5f3ff',
            '--ai-header-bg': 'linear-gradient(135deg, #9333ea 0%, #7c3aed 55%, #6366f1 100%)',
            '--ai-header-text': '#ffffff',
            '--ai-header-border': 'rgba(255, 255, 255, 0.15)',
            '--ai-header-shadow': '0 4px 16px rgba(109, 40, 217, 0.2)',
            '--ai-bubble-bg': 'linear-gradient(135deg, #a855f7 0%, #7c3aed 50%, #6366f1 100%)',
            '--ai-bubble-shadow': '0 12px 28px -4px rgba(124, 58, 237, 0.5), 0 6px 14px -3px rgba(99, 102, 241, 0.35)',
            '--ai-bubble-hover-shadow': '0 16px 34px -4px rgba(124, 58, 237, 0.65)',
            '--ai-teaser-bg': '#ffffff',
            '--ai-teaser-text': '#6d28d9',
            '--ai-teaser-border': '#ede9fe',
            '--ai-teaser-shadow': '0 8px 24px -4px rgba(109, 40, 217, 0.22), 0 2px 6px rgba(0, 0, 0, 0.04)',
            '--ai-window-bg': '#ffffff',
            '--ai-messages-bg': '#f8fafc',
            '--ai-bot-bubble-bg': '#ffffff',
            '--ai-bot-bubble-text': '#1e293b',
            '--ai-bot-bubble-border': '#e2e8f0',
            '--ai-user-bubble-bg': 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
            '--ai-user-bubble-text': '#ffffff',
            '--ai-user-bubble-shadow': '0 6px 16px -4px rgba(109, 40, 217, 0.35)',
            '--ai-section-bg': '#f5f3ff',
            '--ai-section-border': '#ede9fe',
            '--ai-section-text': '#6d28d9',
            '--ai-bullet-color': '#8b5cf6',
            '--ai-chip-bg': '#ffffff',
            '--ai-chip-text': '#6d28d9',
            '--ai-chip-border': '#ddd6fe',
            '--ai-chip-hover-bg': '#f5f3ff',
            '--ai-chip-hover-text': '#5b21b6',
            '--ai-chip-hover-border': '#c4b5fd',
            '--ai-typing-dot': '#8b5cf6',
            '--ai-input-focus-border': '#8b5cf6',
            '--ai-input-focus-ring': 'rgba(139, 92, 246, 0.15)',
            '--ai-send-btn-bg': 'linear-gradient(135deg, #a855f7, #6d28d9)',
            '--ai-send-btn-shadow': '0 4px 12px rgba(109, 40, 217, 0.35)',
            '--ai-footer-icon': '#8b5cf6',
        },
        'gold-black': {
            '--ai-primary': '#d4af37',
            '--ai-primary-hover': '#b8860b',
            '--ai-primary-light': '#fdf8e6',
            '--ai-header-bg': 'linear-gradient(135deg, #18181b 0%, #09090b 60%, #1c1917 100%)',
            '--ai-header-text': '#fef08a',
            '--ai-header-border': 'rgba(212, 175, 55, 0.4)',
            '--ai-header-shadow': '0 4px 16px rgba(0, 0, 0, 0.4)',
            '--ai-bubble-bg': 'linear-gradient(135deg, #f59e0b 0%, #d97706 40%, #18181b 100%)',
            '--ai-bubble-shadow': '0 12px 28px -4px rgba(217, 119, 6, 0.5), 0 6px 14px -3px rgba(0, 0, 0, 0.5)',
            '--ai-bubble-hover-shadow': '0 16px 34px -4px rgba(217, 119, 6, 0.7)',
            '--ai-teaser-bg': '#18181b',
            '--ai-teaser-text': '#fef08a',
            '--ai-teaser-border': '#ca8a04',
            '--ai-teaser-shadow': '0 8px 24px -4px rgba(202, 138, 4, 0.25)',
            '--ai-window-bg': '#ffffff',
            '--ai-messages-bg': '#fafaf9',
            '--ai-bot-bubble-bg': '#ffffff',
            '--ai-bot-bubble-text': '#1c1917',
            '--ai-bot-bubble-border': '#e7e5e4',
            '--ai-bot-avatar-bg': 'linear-gradient(135deg, #fef9c3, #fde047)',
            '--ai-bot-avatar-border': '#eab308',
            '--ai-bot-avatar-row-bg': 'linear-gradient(135deg, #fef9c3, #fef08a)',
            '--ai-bot-avatar-row-border': '#ca8a04',
            '--ai-user-bubble-bg': 'linear-gradient(135deg, #eab308 0%, #ca8a04 100%)',
            '--ai-user-bubble-text': '#0a0a0a',
            '--ai-user-bubble-shadow': '0 6px 16px -4px rgba(202, 138, 4, 0.45)',
            '--ai-section-bg': '#fefce8',
            '--ai-section-border': '#fef08a',
            '--ai-section-text': '#854d0e',
            '--ai-bullet-color': '#d97706',
            '--ai-chip-bg': '#ffffff',
            '--ai-chip-text': '#854d0e',
            '--ai-chip-border': '#fef08a',
            '--ai-chip-hover-bg': '#fefce8',
            '--ai-chip-hover-text': '#713f12',
            '--ai-chip-hover-border': '#fde047',
            '--ai-typing-dot': '#d97706',
            '--ai-input-focus-border': '#d4af37',
            '--ai-input-focus-ring': 'rgba(212, 175, 55, 0.25)',
            '--ai-send-btn-bg': 'linear-gradient(135deg, #f59e0b, #b45309)',
            '--ai-send-btn-shadow': '0 4px 12px rgba(217, 119, 6, 0.45)',
            '--ai-send-btn-hover-shadow': '0 6px 16px rgba(217, 119, 6, 0.6)',
            '--ai-footer-icon': '#d4af37',
        },
        'black-red': {
            '--ai-primary': '#ef4444',
            '--ai-primary-hover': '#dc2626',
            '--ai-primary-light': '#fef2f2',
            '--ai-header-bg': 'linear-gradient(135deg, #18181b 0%, #09090b 50%, #450a0a 100%)',
            '--ai-header-text': '#ffffff',
            '--ai-header-border': 'rgba(239, 68, 68, 0.4)',
            '--ai-header-shadow': '0 4px 16px rgba(0, 0, 0, 0.4)',
            '--ai-bubble-bg': 'linear-gradient(135deg, #ef4444 0%, #dc2626 50%, #18181b 100%)',
            '--ai-bubble-shadow': '0 12px 28px -4px rgba(220, 38, 38, 0.5), 0 6px 14px -3px rgba(0, 0, 0, 0.5)',
            '--ai-bubble-hover-shadow': '0 16px 34px -4px rgba(220, 38, 38, 0.7)',
            '--ai-teaser-bg': '#18181b',
            '--ai-teaser-text': '#f87171',
            '--ai-teaser-border': '#b91c1c',
            '--ai-teaser-shadow': '0 8px 24px -4px rgba(220, 38, 38, 0.25)',
            '--ai-window-bg': '#ffffff',
            '--ai-messages-bg': '#fdf2f2',
            '--ai-bot-bubble-bg': '#ffffff',
            '--ai-bot-bubble-text': '#1e293b',
            '--ai-bot-bubble-border': '#fee2e2',
            '--ai-bot-avatar-bg': 'linear-gradient(135deg, #fee2e2, #fecaca)',
            '--ai-bot-avatar-border': '#f87171',
            '--ai-bot-avatar-row-bg': 'linear-gradient(135deg, #fee2e2, #fecaca)',
            '--ai-bot-avatar-row-border': '#ef4444',
            '--ai-user-bubble-bg': 'linear-gradient(135deg, #ef4444 0%, #b91c1c 100%)',
            '--ai-user-bubble-text': '#ffffff',
            '--ai-user-bubble-shadow': '0 6px 16px -4px rgba(220, 38, 38, 0.45)',
            '--ai-section-bg': '#fee2e2',
            '--ai-section-border': '#fecaca',
            '--ai-section-text': '#b91c1c',
            '--ai-bullet-color': '#dc2626',
            '--ai-chip-bg': '#ffffff',
            '--ai-chip-text': '#b91c1c',
            '--ai-chip-border': '#fecaca',
            '--ai-chip-hover-bg': '#fef2f2',
            '--ai-chip-hover-text': '#991b1b',
            '--ai-chip-hover-border': '#fca5a5',
            '--ai-typing-dot': '#dc2626',
            '--ai-input-focus-border': '#ef4444',
            '--ai-input-focus-ring': 'rgba(239, 68, 68, 0.2)',
            '--ai-send-btn-bg': 'linear-gradient(135deg, #ef4444, #b91c1c)',
            '--ai-send-btn-shadow': '0 4px 12px rgba(220, 38, 38, 0.45)',
            '--ai-send-btn-hover-shadow': '0 6px 16px rgba(220, 38, 38, 0.6)',
            '--ai-footer-icon': '#ef4444',
        },
        'emerald': {
            '--ai-primary': '#059669',
            '--ai-primary-hover': '#047857',
            '--ai-primary-light': '#ecfdf5',
            '--ai-header-bg': 'linear-gradient(135deg, #059669 0%, #047857 55%, #065f46 100%)',
            '--ai-header-text': '#ffffff',
            '--ai-header-border': 'rgba(255, 255, 255, 0.18)',
            '--ai-bubble-bg': 'linear-gradient(135deg, #34d399 0%, #059669 50%, #047857 100%)',
            '--ai-bubble-shadow': '0 12px 28px -4px rgba(5, 150, 105, 0.5), 0 6px 14px -3px rgba(4, 120, 87, 0.35)',
            '--ai-teaser-text': '#047857',
            '--ai-teaser-border': '#a7f3d0',
            '--ai-user-bubble-bg': 'linear-gradient(135deg, #10b981 0%, #047857 100%)',
            '--ai-user-bubble-text': '#ffffff',
            '--ai-user-bubble-shadow': '0 6px 16px -4px rgba(5, 150, 105, 0.4)',
            '--ai-bot-avatar-bg': 'linear-gradient(135deg, #d1fae5, #a7f3d0)',
            '--ai-bot-avatar-border': '#34d399',
            '--ai-bot-avatar-row-bg': 'linear-gradient(135deg, #d1fae5, #a7f3d0)',
            '--ai-bot-avatar-row-border': '#10b981',
            '--ai-section-bg': '#ecfdf5',
            '--ai-section-border': '#a7f3d0',
            '--ai-section-text': '#047857',
            '--ai-bullet-color': '#059669',
            '--ai-chip-bg': '#ffffff',
            '--ai-chip-text': '#047857',
            '--ai-chip-border': '#a7f3d0',
            '--ai-chip-hover-bg': '#ecfdf5',
            '--ai-chip-hover-text': '#065f46',
            '--ai-typing-dot': '#059669',
            '--ai-input-focus-border': '#059669',
            '--ai-input-focus-ring': 'rgba(5, 150, 105, 0.18)',
            '--ai-send-btn-bg': 'linear-gradient(135deg, #10b981, #047857)',
            '--ai-send-btn-shadow': '0 4px 12px rgba(5, 150, 105, 0.4)',
            '--ai-footer-icon': '#059669',
        },
        'blue': {
            '--ai-primary': '#2563eb',
            '--ai-primary-hover': '#1d4ed8',
            '--ai-primary-light': '#eff6ff',
            '--ai-header-bg': 'linear-gradient(135deg, #3b82f6 0%, #2563eb 55%, #1e40af 100%)',
            '--ai-header-text': '#ffffff',
            '--ai-bubble-bg': 'linear-gradient(135deg, #60a5fa 0%, #2563eb 50%, #1d4ed8 100%)',
            '--ai-bubble-shadow': '0 12px 28px -4px rgba(37, 99, 235, 0.5), 0 6px 14px -3px rgba(29, 78, 216, 0.35)',
            '--ai-teaser-text': '#1d4ed8',
            '--ai-teaser-border': '#bfdbfe',
            '--ai-user-bubble-bg': 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
            '--ai-user-bubble-text': '#ffffff',
            '--ai-user-bubble-shadow': '0 6px 16px -4px rgba(37, 99, 235, 0.4)',
            '--ai-bot-avatar-bg': 'linear-gradient(135deg, #dbeafe, #bfdbfe)',
            '--ai-bot-avatar-border': '#60a5fa',
            '--ai-bot-avatar-row-bg': 'linear-gradient(135deg, #dbeafe, #bfdbfe)',
            '--ai-bot-avatar-row-border': '#3b82f6',
            '--ai-section-bg': '#eff6ff',
            '--ai-section-border': '#bfdbfe',
            '--ai-section-text': '#1d4ed8',
            '--ai-bullet-color': '#2563eb',
            '--ai-chip-bg': '#ffffff',
            '--ai-chip-text': '#1d4ed8',
            '--ai-chip-border': '#bfdbfe',
            '--ai-chip-hover-bg': '#eff6ff',
            '--ai-chip-hover-text': '#1e40af',
            '--ai-typing-dot': '#2563eb',
            '--ai-input-focus-border': '#2563eb',
            '--ai-input-focus-ring': 'rgba(37, 99, 235, 0.18)',
            '--ai-send-btn-bg': 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            '--ai-send-btn-shadow': '0 4px 12px rgba(37, 99, 235, 0.4)',
            '--ai-footer-icon': '#2563eb',
        },
        'orange': {
            '--ai-primary': '#ea580c',
            '--ai-primary-hover': '#c2410c',
            '--ai-primary-light': '#fff7ed',
            '--ai-header-bg': 'linear-gradient(135deg, #f97316 0%, #ea580c 55%, #9a3412 100%)',
            '--ai-header-text': '#ffffff',
            '--ai-bubble-bg': 'linear-gradient(135deg, #fb923c 0%, #ea580c 50%, #c2410c 100%)',
            '--ai-bubble-shadow': '0 12px 28px -4px rgba(234, 88, 12, 0.5)',
            '--ai-teaser-text': '#c2410c',
            '--ai-teaser-border': '#fed7aa',
            '--ai-user-bubble-bg': 'linear-gradient(135deg, #f97316 0%, #c2410c 100%)',
            '--ai-user-bubble-text': '#ffffff',
            '--ai-user-bubble-shadow': '0 6px 16px -4px rgba(234, 88, 12, 0.4)',
            '--ai-section-bg': '#fff7ed',
            '--ai-section-border': '#fed7aa',
            '--ai-section-text': '#c2410c',
            '--ai-bullet-color': '#ea580c',
            '--ai-chip-bg': '#ffffff',
            '--ai-chip-text': '#c2410c',
            '--ai-chip-border': '#fed7aa',
            '--ai-chip-hover-bg': '#fff7ed',
            '--ai-chip-hover-text': '#9a3412',
            '--ai-typing-dot': '#ea580c',
            '--ai-input-focus-border': '#ea580c',
            '--ai-input-focus-ring': 'rgba(234, 88, 12, 0.18)',
            '--ai-send-btn-bg': 'linear-gradient(135deg, #f97316, #c2410c)',
            '--ai-send-btn-shadow': '0 4px 12px rgba(234, 88, 12, 0.4)',
            '--ai-footer-icon': '#ea580c',
        },
        'dark': {
            '--ai-primary': '#60a5fa',
            '--ai-primary-hover': '#3b82f6',
            '--ai-primary-light': '#27272a',
            '--ai-window-bg': '#18181b',
            '--ai-window-shadow': '0 24px 60px -12px rgba(0, 0, 0, 0.75), 0 0 0 1px rgba(255, 255, 255, 0.1)',
            '--ai-messages-bg': '#09090b',
            '--ai-header-bg': 'linear-gradient(135deg, #27272a 0%, #18181b 100%)',
            '--ai-header-text': '#ffffff',
            '--ai-header-border': 'rgba(255, 255, 255, 0.1)',
            '--ai-bubble-bg': 'linear-gradient(135deg, #3f3f46 0%, #27272a 50%, #18181b 100%)',
            '--ai-bubble-shadow': '0 12px 28px -4px rgba(0, 0, 0, 0.6)',
            '--ai-teaser-bg': '#18181b',
            '--ai-teaser-text': '#f4f4f5',
            '--ai-teaser-border': '#3f3f46',
            '--ai-bot-bubble-bg': '#18181b',
            '--ai-bot-bubble-text': '#f4f4f5',
            '--ai-bot-bubble-border': '#27272a',
            '--ai-bot-avatar-bg': '#27272a',
            '--ai-bot-avatar-border': '#3f3f46',
            '--ai-bot-avatar-row-bg': '#27272a',
            '--ai-bot-avatar-row-border': '#3f3f46',
            '--ai-user-bubble-bg': 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
            '--ai-user-bubble-text': '#ffffff',
            '--ai-chip-bg': '#18181b',
            '--ai-chip-text': '#e4e4e7',
            '--ai-chip-border': '#27272a',
            '--ai-chip-hover-bg': '#27272a',
            '--ai-chip-hover-text': '#ffffff',
            '--ai-input-container-bg': '#18181b',
            '--ai-input-container-border': '#27272a',
            '--ai-input-box-bg': '#09090b',
            '--ai-input-box-border': '#27272a',
            '--ai-input-focus-bg': '#09090b',
            '--ai-input-text': '#ffffff',
            '--ai-input-placeholder': '#71717a',
            '--ai-send-btn-bg': 'linear-gradient(135deg, #3b82f6, #1d4ed8)',
            '--ai-section-bg': '#27272a',
            '--ai-section-border': '#3f3f46',
            '--ai-section-text': '#60a5fa',
            '--ai-bullet-color': '#60a5fa',
            '--ai-footer-icon': '#60a5fa',
            '--ai-footer-text': '#71717a',
        }
    };

    function buildCustomTheme(hex, headerColor = null, isDarkMode = false) {
        if (!hex.startsWith('#')) hex = '#' + hex;
        const lighter = adjustHex(hex, 18);
        const darker = adjustHex(hex, -20);
        const ultraLight = isDarkMode ? '#1e293b' : adjustHex(hex, 88);
        const textColor = isColorDark(hex) ? '#ffffff' : '#0a0a0a';
        
        let headerBg = `linear-gradient(135deg, ${lighter} 0%, ${hex} 55%, ${darker} 100%)`;
        let headerText = '#ffffff';
        if (headerColor) {
            headerBg = headerColor.includes('gradient') ? headerColor : `linear-gradient(135deg, ${headerColor} 0%, ${adjustHex(headerColor, -15)} 100%)`;
            headerText = isColorDark(headerColor) ? '#ffffff' : '#0a0a0a';
        }

        const theme = {
            '--ai-primary': hex,
            '--ai-primary-hover': darker,
            '--ai-primary-light': ultraLight,
            '--ai-header-bg': headerBg,
            '--ai-header-text': headerText,
            '--ai-bubble-bg': `linear-gradient(135deg, ${lighter} 0%, ${hex} 50%, ${darker} 100%)`,
            '--ai-bubble-shadow': `0 12px 28px -4px ${hex}80`,
            '--ai-teaser-text': darker,
            '--ai-teaser-border': adjustHex(hex, 60),
            '--ai-user-bubble-bg': `linear-gradient(135deg, ${hex} 0%, ${darker} 100%)`,
            '--ai-user-bubble-text': textColor,
            '--ai-user-bubble-shadow': `0 6px 16px -4px ${hex}60`,
            '--ai-section-bg': ultraLight,
            '--ai-section-border': adjustHex(hex, 60),
            '--ai-section-text': darker,
            '--ai-bullet-color': hex,
            '--ai-chip-text': darker,
            '--ai-chip-border': adjustHex(hex, 50),
            '--ai-chip-hover-bg': ultraLight,
            '--ai-chip-hover-text': darker,
            '--ai-typing-dot': hex,
            '--ai-input-focus-border': hex,
            '--ai-input-focus-ring': `${hex}25`,
            '--ai-send-btn-bg': `linear-gradient(135deg, ${lighter}, ${hex})`,
            '--ai-send-btn-shadow': `0 4px 12px ${hex}55`,
            '--ai-footer-icon': hex,
        };

        if (isDarkMode) {
            theme['--ai-window-bg'] = '#18181b';
            theme['--ai-messages-bg'] = '#09090b';
            theme['--ai-bot-bubble-bg'] = '#18181b';
            theme['--ai-bot-bubble-text'] = '#f4f4f5';
            theme['--ai-bot-bubble-border'] = '#27272a';
            theme['--ai-input-container-bg'] = '#18181b';
            theme['--ai-input-container-border'] = '#27272a';
            theme['--ai-input-box-bg'] = '#09090b';
            theme['--ai-input-box-border'] = '#27272a';
            theme['--ai-input-focus-bg'] = '#09090b';
            theme['--ai-input-text'] = '#ffffff';
            theme['--ai-chip-bg'] = '#18181b';
        }

        return theme;
    }

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

    function applyTheme(themeKeyOrHex, customHeader = null, mode = 'light') {
        if (!rootContainer) return;

        let themeVars = null;
        const normalized = (themeKeyOrHex || '').toLowerCase().trim();

        if (normalized === 'gold-black' || normalized === 'black-gold' || normalized === 'gold' || normalized === 'emas') {
            themeVars = Object.assign({}, THEME_PRESETS['gold-black']);
        } else if (normalized === 'black-red' || normalized === 'red-black' || normalized === 'red' || normalized === 'merah') {
            themeVars = Object.assign({}, THEME_PRESETS['black-red']);
        } else if (normalized === 'emerald' || normalized === 'green' || normalized === 'hijau' || normalized === 'herba') {
            themeVars = Object.assign({}, THEME_PRESETS['emerald']);
        } else if (normalized === 'blue' || normalized === 'biru' || normalized === 'ocean' || normalized === 'corporate') {
            themeVars = Object.assign({}, THEME_PRESETS['blue']);
        } else if (normalized === 'orange' || normalized === 'jingga' || normalized === 'sunset') {
            themeVars = Object.assign({}, THEME_PRESETS['orange']);
        } else if (normalized === 'dark' || normalized === 'gelap' || normalized === 'obsidian' || normalized === 'black') {
            themeVars = Object.assign({}, THEME_PRESETS['dark']);
        } else if (THEME_PRESETS[normalized]) {
            themeVars = Object.assign({}, THEME_PRESETS[normalized]);
        } else if (/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test(themeKeyOrHex)) {
            themeVars = buildCustomTheme(themeKeyOrHex, customHeader, mode === 'dark');
        } else {
            themeVars = Object.assign({}, THEME_PRESETS['purple']);
        }

        if (customHeader) {
            themeVars['--ai-header-bg'] = customHeader.includes('gradient') ? customHeader : `linear-gradient(135deg, ${customHeader} 0%, ${adjustHex(customHeader, -15)} 100%)`;
            themeVars['--ai-header-text'] = isColorDark(customHeader) ? '#ffffff' : '#0a0a0a';
        }

        if (mode === 'dark' && normalized !== 'dark') {
            themeVars['--ai-window-bg'] = '#18181b';
            themeVars['--ai-messages-bg'] = '#09090b';
            themeVars['--ai-bot-bubble-bg'] = '#18181b';
            themeVars['--ai-bot-bubble-text'] = '#f4f4f5';
            themeVars['--ai-bot-bubble-border'] = '#27272a';
            themeVars['--ai-input-container-bg'] = '#18181b';
            themeVars['--ai-input-container-border'] = '#27272a';
            themeVars['--ai-input-box-bg'] = '#09090b';
            themeVars['--ai-input-box-border'] = '#27272a';
            themeVars['--ai-input-focus-bg'] = '#09090b';
            themeVars['--ai-input-text'] = '#ffffff';
            themeVars['--ai-chip-bg'] = '#18181b';
        }

        for (const [prop, val] of Object.entries(themeVars)) {
            rootContainer.style.setProperty(prop, val);
        }

        // Suntik tag <style> dinamik terus ke <head> bagi mengatasi sebarang cache CSS lama
        let dynamicStyle = document.getElementById('ai-chat-dynamic-theme');
        if (!dynamicStyle) {
            dynamicStyle = document.createElement('style');
            dynamicStyle.id = 'ai-chat-dynamic-theme';
            document.head.appendChild(dynamicStyle);
        }

        const isDarkTheme = (mode === 'dark' || normalized === 'dark');
        const headerBg = themeVars['--ai-header-bg'] || '#7c3aed';
        const headerText = themeVars['--ai-header-text'] || '#ffffff';
        const headerBorder = themeVars['--ai-header-border'] || 'rgba(255,255,255,0.15)';
        const bubbleBg = themeVars['--ai-bubble-bg'] || headerBg;
        const bubbleShadow = themeVars['--ai-bubble-shadow'] || '0 10px 25px rgba(0,0,0,0.3)';
        const userBubbleBg = themeVars['--ai-user-bubble-bg'] || headerBg;
        const userBubbleText = themeVars['--ai-user-bubble-text'] || '#ffffff';
        const sendBtnBg = themeVars['--ai-send-btn-bg'] || headerBg;
        const chipText = themeVars['--ai-chip-text'] || '#6d28d9';
        const chipBorder = themeVars['--ai-chip-border'] || '#ddd6fe';
        const chipHoverBg = themeVars['--ai-chip-hover-bg'] || '#f5f3ff';
        const bulletColor = themeVars['--ai-bullet-color'] || themeVars['--ai-primary'] || '#7c3aed';
        const focusBorder = themeVars['--ai-input-focus-border'] || bulletColor;
        const footerIcon = themeVars['--ai-footer-icon'] || bulletColor;

        dynamicStyle.textContent = `
            #ai-chat-root .ai-chat-header {
                background: ${headerBg} !important;
                color: ${headerText} !important;
                border-bottom: 1px solid ${headerBorder} !important;
            }
            #ai-chat-root .ai-chat-title, #ai-chat-root .ai-chat-status {
                color: ${headerText} !important;
            }
            #ai-chat-root #ai-chat-bubble {
                background: ${bubbleBg} !important;
                box-shadow: ${bubbleShadow} !important;
            }
            #ai-chat-root .ai-message-user .ai-bubble {
                background: ${userBubbleBg} !important;
                color: ${userBubbleText} !important;
            }
            #ai-chat-root .ai-chat-send-btn {
                background: ${sendBtnBg} !important;
            }
            #ai-chat-root .ai-chip {
                color: ${chipText} !important;
                border-color: ${chipBorder} !important;
            }
            #ai-chat-root .ai-chip:hover {
                background: ${chipHoverBg} !important;
            }
            #ai-chat-root .ai-bullet-dot {
                color: ${bulletColor} !important;
            }
            #ai-chat-root .ai-chat-input-box:focus-within {
                border-color: ${focusBorder} !important;
            }
            #ai-chat-root .ai-chat-footer-badge svg {
                color: ${footerIcon} !important;
            }
            ${isDarkTheme ? `
            #ai-chat-root #ai-chat-window {
                background: #18181b !important;
            }
            #ai-chat-root .ai-chat-messages {
                background: #09090b !important;
            }
            #ai-chat-root .ai-message-bot .ai-bubble {
                background: #18181b !important;
                color: #f4f4f5 !important;
                border-color: #27272a !important;
            }
            #ai-chat-root .ai-chat-input-container {
                background: #18181b !important;
                border-color: #27272a !important;
            }
            #ai-chat-root .ai-chat-input-box {
                background: #09090b !important;
                border-color: #27272a !important;
            }
            #ai-chat-root .ai-chat-input {
                color: #ffffff !important;
            }
            ` : ''}
        `;
    }

    // Terapkan tema yang dipilih pelanggan serta-merta
    applyTheme(colorAttr || themeAttr, headerAttr, modeAttr);

    // Dedahkan fungsi API global untuk laman web pelanggan menukar tema secara langsung via JS
    if (typeof window !== 'undefined') {
        window.AiMariaDbSetTheme = (t, h, m) => applyTheme(t, h, m);
        window.AiMariaDbSetColor = (c, h, m) => applyTheme(c, h, m);
    }

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
