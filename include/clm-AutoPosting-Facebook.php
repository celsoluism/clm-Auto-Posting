<?php
/**
 * ** CABEÇALHO
 * Plugin Name: CLM Auto Posting Express
 * Plugin URI: https://celsoluism.online/
 * Description: Plugin completo de compartilhamento automatizado de produtos, páginas e posts para Facebook, Instagram, Telegram e WhatsApp com agendamento avançado.
 * Version: 2026.0720.1900
 * Author: Celso Luis Martins
 * Author URI: https://celsoluism.online/
 * License: GPL2
 * Arquivo: includes/clm-AutoPosting-Facebook.php
 * Função: Integração oficial com Facebook Graph API (Feed e Stories), manipulação de tokens e montagem de payload.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_Facebook {
    
    public function __construct() {
        // Hooks acionados pelo Scheduler para realizar as publicações
        add_action( 'clm_autoposting_do_facebook_post', array( $this, 'publish_to_facebook' ), 10, 2 );
        add_action( 'clm_autoposting_do_facebook_stories', array( $this, 'publish_to_facebook' ), 10, 2 ); // Reaproveita o método, podendo ser bifurcado depois
    }

    public function publish_to_facebook( $post_id, $network_type = 'facebook_post' ) {
        $opts = get_option( '_clm_autoposting_' . $network_type, array() );
        
        if ( empty( $opts['active'] ) ) {
            return; // Aborta se foi desativado no admin no meio tempo do agendamento
        }

        // 1. Resgata a URL e aplica o encurtador caso configurado
        $post_url = get_permalink( $post_id );
        
        if ( class_exists( 'CLM_AutoPosting_Shortener' ) ) {
            $final_url = CLM_AutoPosting_Shortener::get_short_url( $post_url, $opts );
        } else {
            $final_url = $post_url;
        }
        
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            CLM_AutoPosting_Logger::log( "Preparando disparo via Graph API no {$network_type} (Post ID: {$post_id}) | Link: {$final_url}", 'FACEBOOK' );
        }
        
        /**
         * 2. LÓGICA DA GRAPH API & APP METHOD
         * Resgatando os parâmetros salvos isoladamente no painel de Configurações
         */
        $api_version    = !empty( $opts['api_version'] ) ? sanitize_text_field( $opts['api_version'] ) : 'v16.0';
        $page_id        = !empty( $opts['app_id'] ) ? sanitize_text_field( $opts['app_id'] ) : ''; // Usado como App ID / Page ID
        
        // Compatibilidade com o novo seletor de Auth Type (Graph API vs App Method)
        $auth_type      = !empty( $opts['auth_type'] ) ? $opts['auth_type'] : 'app_method';
        $access_token   = '';
        if ( $auth_type === 'graph_api' && !empty( $opts['graph_access_token'] ) ) {
            $access_token = sanitize_text_field( $opts['graph_access_token'] );
        } else {
            $access_token = !empty( $opts['access_token'] ) ? sanitize_text_field( $opts['access_token'] ) : '';
            
            // Caso utilize contas conectadas dinamicamente via painel, valida token alternativo se houver
            if ( empty( $access_token ) && !empty( $opts['connected_accounts'] ) && is_array( $opts['connected_accounts'] ) ) {
                foreach ( $opts['connected_accounts'] as $acc ) {
                    if ( !empty( $acc['userid'] ) ) {
                        // Se necessário, utiliza o ID da primeira conta conectada como referência secundária se o Page ID principal estiver vazio
                        if ( empty( $page_id ) ) {
                            $page_id = sanitize_text_field( $acc['userid'] );
                        }
                    }
                }
            }
        }

        $message_format = !empty( $opts['message_format'] ) ? $opts['message_format'] : '{title} - {url}';

        if ( empty( $page_id ) || empty( $access_token ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Disparo abortado para {$network_type}: App ID / Page ID ou Access Token estão vazios no painel.", 'FACEBOOK_ERROR' );
            }
            return;
        }

        // 3. Montagem da Mensagem Dinâmica
        $post_title   = get_the_title( $post_id );
        // Aciona o Core para obter o texto respeitando a regra do 'Enable Excerpt Content'
        $post_content = class_exists( 'CLM_AutoPosting_Core' ) ? CLM_AutoPosting_Core::get_post_text( $post_id ) : '';
        
        $final_message = str_replace(
            array( '{title}', '{url}', '{content}' ),
            array( $post_title, $final_url, $post_content ),
            $message_format
        );

        // 4. Requisição Remota (POST) para a Graph API da Meta
        $endpoint = "https://graph.facebook.com/{$api_version}/{$page_id}/feed";
        
        $api_args = array(
            'method'  => 'POST',
            'timeout' => 15,
            'body'    => array(
                'message'      => $final_message,
                'link'         => $final_url,
                'access_token' => $access_token
            )
        );

        $response = wp_remote_post( $endpoint, $api_args );

        // 5. Tratamento de Resposta e Logs
        if ( is_wp_error( $response ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Erro crítico cURL/Servidor ao contactar Facebook: " . $response->get_error_message(), 'FACEBOOK_ERROR' );
            }
        } else {
            $status_code = wp_remote_retrieve_response_code( $response );
            $body = wp_remote_retrieve_body( $response );
            
            if ( $status_code == 200 || $status_code == 201 ) {
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    $json_resp = json_decode( $body, true );
                    $fb_post_id = isset( $json_resp['id'] ) ? $json_resp['id'] : 'Desconhecido';
                    CLM_AutoPosting_Logger::log( "Postagem publicada no {$network_type} com sucesso. FB Graph ID: {$fb_post_id}", 'FACEBOOK_SUCCESS' );
                }
            } else {
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    CLM_AutoPosting_Logger::log( "Recusa da Graph API (Código {$status_code}): {$body}", 'FACEBOOK_ERROR' );
                }
            }
        }
    }
}
new CLM_AutoPosting_Facebook();