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
 * Arquivo: includes/clm-AutoPosting-WhatsApp.php
 * Função: Integração com WhatsApp Cloud API (Envios automáticos para Grupos/Stories e contatos).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_WhatsApp {
    
    public function __construct() {
        add_action( 'clm_autoposting_do_whatsapp_group', array( $this, 'publish_to_whatsapp_group' ), 10, 2 );
        add_action( 'clm_autoposting_do_whatsapp_stories', array( $this, 'publish_to_whatsapp_stories' ), 10, 2 );
    }

    public function publish_to_whatsapp_group( $post_id, $network_type = 'whatsapp_group' ) {
        $this->process_whatsapp_publish( $post_id, $network_type, 'GROUP' );
    }

    public function publish_to_whatsapp_stories( $post_id, $network_type = 'whatsapp_stories' ) {
        $this->process_whatsapp_publish( $post_id, $network_type, 'STORIES' );
    }

    private function process_whatsapp_publish( $post_id, $network_type, $type_log ) {
        $opts = get_option( '_clm_autoposting_' . $network_type, array() );
        
        if ( empty( $opts['active'] ) ) return;

        $post_url = get_permalink( $post_id );
        $final_url = class_exists( 'CLM_AutoPosting_Shortener' ) ? CLM_AutoPosting_Shortener::get_short_url( $post_url, $opts ) : $post_url;
        
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            CLM_AutoPosting_Logger::log( "Preparando disparo via API WhatsApp [{$type_log}] (Post ID: {$post_id}) | Link: {$final_url}", 'WHATSAPP' );
        }
        
        // 1. Resgata credenciais e formato do banco de dados (_clm_autoposting_whatsapp_group ou _clm_autoposting_whatsapp_stories)
        $api_version    = !empty( $opts['api_version'] ) ? sanitize_text_field( $opts['api_version'] ) : 'v17.0';
        $phone_id       = !empty( $opts['app_id'] ) ? sanitize_text_field( $opts['app_id'] ) : ''; // Phone Number ID do remetente
        $recipient_id   = !empty( $opts['app_secret'] ) ? sanitize_text_field( $opts['app_secret'] ) : ''; // ID do Grupo ou Destinatário
        $access_token   = !empty( $opts['access_token'] ) ? sanitize_text_field( $opts['access_token'] ) : '';
        
        // Compatibilidade com contas conectadas se faltarem IDs principais
        if ( ( empty( $phone_id ) || empty( $recipient_id ) ) && !empty( $opts['connected_accounts'] ) && is_array( $opts['connected_accounts'] ) ) {
            foreach ( $opts['connected_accounts'] as $acc ) {
                if ( empty( $phone_id ) && !empty( $acc['userid'] ) ) {
                    $phone_id = sanitize_text_field( $acc['userid'] );
                }
                if ( empty( $recipient_id ) && !empty( $acc['name'] ) ) {
                    $recipient_id = sanitize_text_field( $acc['name'] );
                }
            }
        }

        $message_format = !empty( $opts['message_format'] ) ? $opts['message_format'] : '{title} - {url}';

        if ( empty( $phone_id ) || empty( $recipient_id ) || empty( $access_token ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Disparo abortado [{$type_log}]: App ID (Phone ID), App Secret (Recipient ID) ou Access Token estão vazios no painel.", 'WHATSAPP_ERROR' );
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

        // 3. Requisição Remota (POST) para a WhatsApp Cloud API (Padrão Meta)
        $endpoint = "https://graph.facebook.com/{$api_version}/{$phone_id}/messages";
        
        $payload = array(
            'messaging_product' => 'whatsapp',
            'to'                => $recipient_id,
            'type'              => 'text',
            'text'              => array(
                'body' => $final_message
            )
        );

        $api_args = array(
            'method'  => 'POST',
            'timeout' => 15,
            'headers' => array(
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type'  => 'application/json'
            ),
            'body'    => wp_json_encode( $payload )
        );

        $response = wp_remote_post( $endpoint, $api_args );

        // 4. Tratamento de Resposta e Logs
        if ( is_wp_error( $response ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Erro crítico cURL/Servidor ao contactar WhatsApp API [{$type_log}]: " . $response->get_error_message(), 'WHATSAPP_ERROR' );
            }
        } else {
            $status_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            $json_resp = json_decode( $body, true );
            
            // Sucesso na Meta API retorna o array 'messages'
            if ( ( $status_code == 200 || $status_code == 201 ) && isset( $json_resp['messages'] ) ) {
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    $message_id = isset( $json_resp['messages'][0]['id'] ) ? $json_resp['messages'][0]['id'] : 'Desconhecido';
                    CLM_AutoPosting_Logger::log( "Postagem [{$type_log}] publicada no WhatsApp com sucesso. Msg ID: {$message_id}", 'WHATSAPP_SUCCESS' );
                }
            } else {
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    $error_desc = isset( $json_resp['error']['message'] ) ? $json_resp['error']['message'] : $body;
                    CLM_AutoPosting_Logger::log( "Recusa da WhatsApp API [{$type_log}] (Código {$status_code}): {$error_desc}", 'WHATSAPP_ERROR' );
                }
            }
        }
    }
}
new CLM_AutoPosting_WhatsApp();