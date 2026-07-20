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
 * Arquivo: includes/clm-AutoPosting-Telegram.php
 * Função: Integração oficial com Telegram Bot API para envio de mensagens em Canais e Grupos.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_Telegram {
    
    public function __construct() {
        add_action( 'clm_autoposting_do_telegram', array( $this, 'publish_to_telegram' ), 10, 2 );
    }

    public function publish_to_telegram( $post_id, $network_type = 'telegram' ) {
        $opts = get_option( '_clm_autoposting_' . $network_type, array() );
        
        if ( empty( $opts['active'] ) ) return;

        $post_url = get_permalink( $post_id );
        $final_url = class_exists( 'CLM_AutoPosting_Shortener' ) ? CLM_AutoPosting_Shortener::get_short_url( $post_url, $opts ) : $post_url;
        
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            CLM_AutoPosting_Logger::log( "Preparando disparo via Telegram Bot API (Post ID: {$post_id}) | Link: {$final_url}", 'TELEGRAM' );
        }
        
        // 1. Resgata credenciais e formato do banco de dados _clm_autoposting_telegram
        $bot_token      = !empty( $opts['access_token'] ) ? sanitize_text_field( $opts['access_token'] ) : '';
        $chat_id        = !empty( $opts['app_id'] ) ? sanitize_text_field( $opts['app_id'] ) : ''; // Usado como Chat ID (@canal ou -100xxx)
        
        // Compatibilidade com contas conectadas dinamicamente
        if ( empty( $chat_id ) && !empty( $opts['connected_accounts'] ) && is_array( $opts['connected_accounts'] ) ) {
            foreach ( $opts['connected_accounts'] as $acc ) {
                if ( !empty( $acc['userid'] ) ) {
                    $chat_id = sanitize_text_field( $acc['userid'] );
                    break;
                }
            }
        }

        $message_format = !empty( $opts['message_format'] ) ? $opts['message_format'] : '{title} - {url}';

        if ( empty( $bot_token ) || empty( $chat_id ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Disparo abortado para Telegram: Access Token (Bot Token) ou App ID (Chat ID) estão vazios no painel.", 'TELEGRAM_ERROR' );
            }
            return;
        }

        // 2. Montagem da Mensagem Dinâmica
        $post_title   = get_the_title( $post_id );
        $post_content = class_exists( 'CLM_AutoPosting_Core' ) ? CLM_AutoPosting_Core::get_post_text( $post_id ) : '';
        
        $final_message = str_replace(
            array( '{title}', '{url}', '{content}' ),
            array( $post_title, $final_url, $post_content ),
            $message_format
        );

        // 3. Requisição Remota (POST) para a Telegram API
        $endpoint = "https://api.telegram.org/bot{$bot_token}/sendMessage";
        
        $api_args = array(
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body'    => wp_json_encode( array(
                'chat_id'                  => $chat_id,
                'text'                     => $final_message,
                'parse_mode'               => 'HTML',
                'disable_web_page_preview' => false
            ) )
        );

        $response = wp_remote_post( $endpoint, $api_args );

        // 4. Tratamento de Resposta e Logs
        if ( is_wp_error( $response ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Erro crítico cURL/Servidor ao contactar Telegram: " . $response->get_error_message(), 'TELEGRAM_ERROR' );
            }
        } else {
            $status_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $json_resp = json_decode( $body, true );
            
            // O Telegram retorna 'ok' => true em caso de sucesso
            if ( $status_code == 200 && isset( $json_resp['ok'] ) && $json_resp['ok'] === true ) {
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    $message_id = isset( $json_resp['result']['message_id'] ) ? $json_resp['result']['message_id'] : 'Desconhecido';
                    CLM_AutoPosting_Logger::log( "Postagem publicada no Telegram com sucesso. Msg ID: {$message_id}", 'TELEGRAM_SUCCESS' );
                }
            } else {
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    $error_desc = isset( $json_resp['description'] ) ? $json_resp['description'] : $body;
                    CLM_AutoPosting_Logger::log( "Recusa da Telegram API (Código {$status_code}): {$error_desc}", 'TELEGRAM_ERROR' );
                }
            }
        }
    }
}
new CLM_AutoPosting_Telegram();