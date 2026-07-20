<?php
/**
 * ** CABEÇALHO
 * Plugin Name: CLM Auto Posting Express
 * Plugin URI: https://celsoluism.online/
 * Description: Plugin completo de compartilhamento automatizado de produtos, páginas e posts para Facebook, Instagram, Telegram e WhatsApp com agendamento avançado.
 * Version: 2026.0720.2000
 * Author: Celso Luis Martins
 * Author URI: https://celsoluism.online/
 * License: GPL2
 * Arquivo: includes/clm-AutoPosting-Core.php
 * Função: Engine central de triggers (interceptação de novos posts/produtos) e processamento de Resumo (Excerpt).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_Core {
    
    public function __construct() {
        // Intercepta a mudança de status de qualquer post/produto no WordPress
        add_action( 'transition_post_status', array( $this, 'trigger_new_post' ), 10, 3 );
    }

    public function trigger_new_post( $new_status, $old_status, $post ) {
        $opts = get_option( '_clm_autoposting_geral', array() );
        
        // 1. Verifica se o plugin geral está ativado
        if ( empty( $opts['plugin_enabled'] ) ) {
            return;
        }

        // 2. Filtro: Allow autopost from thirdparty plugins
        // Se estiver desativado, barra custom post types de outros plugins, mantendo os nativos.
        if ( empty( $opts['thirdparty_plugins'] ) ) {
            $allowed_core_types = array( 'post', 'page', 'product' );
            if ( ! in_array( $post->post_type, $allowed_core_types ) ) {
                return; 
            }
        }

        // Bloqueia auto-saves e revisões para não poluir a fila
        if ( wp_is_post_autosave( $post->ID ) || wp_is_post_revision( $post->ID ) ) {
            return;
        }

        // 3. Verifica se a regra é "Apenas para Novos Posts" (ignora atualizações de posts já publicados)
        if ( ! empty( $opts['only_new_posts'] ) && $old_status === 'publish' ) {
            return;
        }

        // 4. Se acabou de ser publicado, inicia o gatilho
        if ( $new_status === 'publish' && $old_status !== 'publish' ) {
            CLM_AutoPosting_Logger::log( "Novo conteúdo detectado (ID: {$post->ID} | Tipo: {$post->post_type})", 'CORE' );
            
            // Dispara a Ação para o Scheduler capturar e calcular a fila
            do_action( 'clm_autoposting_add_to_scheduler', $post->ID );
        }
    }

    /**
     * Função estática para fornecer o texto final do post para as redes sociais.
     * Retorna o Excerpt se a opção estiver ativa no painel e ele existir, 
     * caso contrário retorna o Content padrão do post.
     * 
     * @param int $post_id
     * @return string
     */
    public static function get_post_text( $post_id ) {
        $opts = get_option( '_clm_autoposting_geral', array() );
        $post = get_post( $post_id );
        
        if ( ! $post ) return '';

        $text = '';

        // Validação da regra: Enable Excerpt Content?
        if ( ! empty( $opts['enable_excerpt'] ) && ! empty( $post->post_excerpt ) ) {
            $text = $post->post_excerpt;
        } else {
            // Publica normalmente com o conteúdo padrão do post
            $text = $post->post_content;
        }

        // Higieniza o texto removendo HTML, shortcodes indesejados e espaços extras
        $text = strip_shortcodes( $text );
        $text = wp_strip_all_tags( $text );
        
        return trim( $text );
    }
}
new CLM_AutoPosting_Core();