<?php
declare(strict_types=1);

/**
 * AiMariaDb PHP Plugin Helper
 * 
 * Memudahkan penyematan widget chatbot ke mana-mana sistem PHP
 * (cth: WordPress, Laravel, CodeIgniter, OpenCart, atau Vanilla PHP).
 */

if (!function_exists('render_ai_chatbot')) {
    /**
     * Cetak kod skrip widget chatbot ke halaman web
     * 
     * @param array $options Konfigurasi widget:
     *   - 'api_url': URL endpoint /api/chat.php
     *   - 'widget_url': URL fail /widget/chat.js (pilihan)
     *   - 'title': Judul tajuk chatbot
     *   - 'greeting': Mesej sapaan pertama
     */
    function render_ai_chatbot(array $options = []): string
    {
        $apiUrl = htmlspecialchars($options['api_url'] ?? '/api/chat.php', ENT_QUOTES, 'UTF-8');
        $widgetUrl = htmlspecialchars($options['widget_url'] ?? '/widget/chat.js', ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($options['title'] ?? 'Pembantu Kedai AI', ENT_QUOTES, 'UTF-8');
        $greeting = htmlspecialchars($options['greeting'] ?? 'Hai! Ada apa yang boleh saya bantu mengenai produk atau waktu operasi kami?', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!-- AiMariaDb Chatbot Plugin -->
<script 
    src="{$widgetUrl}" 
    data-api="{$apiUrl}" 
    data-title="{$title}" 
    data-greeting="{$greeting}" 
    async defer>
</script>
<!-- End AiMariaDb Chatbot Plugin -->
HTML;
    }
}
