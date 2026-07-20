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
 * Arquivo: includes/clm-AutoPosting-Instagram.php
 * Função: Integração oficial com Instagram Graph API (Feed e Stories) com criação de containers de mídia.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_Instagram {
    
    public function __construct() {
        // Hooks que serão chamados pelo Scheduler via Cron
        add_action( 'clm_autoposting_do_instagram_post', array( $this, 'publish_to_instagram_feed' ), 10, 2 );
        add_action( 'clm_autoposting_do_instagram_stories', array( $this, 'publish_to_instagram_stories' ), 10, 2 );
    }

    public function publish_to_instagram_feed( $post_id, $network_type = 'instagram_post' ) {
        $this->process_instagram_publish( $post_id, $network_type, 'FEED' );
    }

    public function publish_to_instagram_stories( $post_id, $network_type = 'instagram_stories' ) {
        $this->process_instagram_publish( $post_id, $network_type, 'STORIES' );
    }

    private function process_instagram_publish( $post_id, $network_type, $type_log ) {
        $opts = get_option( '_clm_autoposting_' . $network_type, array() );
        
        if ( empty( $opts['active'] ) ) return;

        $post_url = get_permalink( $post_id );
        $final_url = class_exists( 'CLM_AutoPosting_Shortener' ) ? CLM_AutoPosting_Shortener::get_short_url( $post_url, $opts ) : $post_url;
        
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            CLM_AutoPosting_Logger::log( "Preparando disparo via API Instagram [{$type_log}] (Post ID: {$post_id}) | Link: {$final_url}", 'INSTAGRAM' );
        }
        
        // LÓGICA DA INSTAGRAM GRAPH API
        $api_version    = !empty( $opts['api_version'] ) ? sanitize_text_field( $opts['api_version'] ) : 'v16.0';
        $ig_user_id     = !empty( $opts['app_id'] ) ? sanitize_text_field( $opts['app_id'] ) : ''; // Instagram Business Account ID
        $access_token   = !empty( $opts['access_token'] ) ? sanitize_text_field( $opts['access_token'] ) : '';
        
        // Compatibilidade com contas conectadas se o token estiver vazio
        if ( empty( $access_token ) && !empty( $opts['connected_accounts'] ) && is_array( $opts['connected_accounts'] ) ) {
            foreach ( $opts['connected_accounts'] as $acc ) {
                if ( empty( $ig_user_id ) && !empty( $acc['userid'] ) ) {
                    $ig_user_id = sanitize_text_field( $acc['userid'] );
                }
            }
        }

        $message_format = !empty( $opts['message_format'] ) ? $opts['message_format'] : '{title} - {url}';

        if ( empty( $ig_user_id ) || empty( $access_token ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Disparo abortado [{$type_log}]: Instagram Account ID ou Access Token vazios no painel.", 'INSTAGRAM_ERROR' );
            }
            return;
        }

        // Resgata a imagem destacada (Instagram exige mídia validada)
        $image_url = get_the_post_thumbnail_url( $post_id, 'full' );
        if ( empty( $image_url ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Disparo abortado [{$type_log}]: O Post ID {$post_id} não possui imagem destacada, requisito obrigatório para o Instagram.", 'INSTAGRAM_ERROR' );
            }
            return;
        }

        // Montagem da Legenda (Caption) dinâmica baseada no Core
        $post_title   = get_the_title( $post_id );
        $post_content = class_exists( 'CLM_AutoPosting_Core' ) ? CLM_AutoPosting_Core::get_post_text( $post_id ) : '';
        
        $caption = str_replace(
            array( '{title}', '{url}', '{content}' ),
            array( $post_title, $final_url, $post_content ),
            $message_format
        );

        // PASSO 1: Criar Container de Mídia na API
        $container_endpoint = "https://graph.facebook.com/{$api_version}/{$ig_user_id}/media";
        $container_body = array(
            'image_url'    => $image_url,
            'caption'      => $caption,
            'access_token' => $access_token
        );
        
        // Injeção de parâmetro específico se for Story
        if ( $type_log === 'STORIES' ) {
            $container_body['media_type'] = 'STORIES';
        }

        $container_response = wp_remote_post( $container_endpoint, array( 'method' => 'POST', 'timeout' => 15, 'body' => $container_body ) );

        if ( is_wp_error( $container_response ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Erro crítico cURL ao criar Container no Instagram: " . $container_response->get_error_message(), 'INSTAGRAM_ERROR' );
            }
            return;
        }

        $container_status = wp_remote_retrieve_response_code( $container_response );
        $container_data   = json_decode( wp_remote_retrieve_body( $container_response ), true );

        if ( $container_status != 200 || empty( $container_data['id'] ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Falha na criação do Container [{$type_log}] (Código {$container_status}): " . wp_remote_retrieve_body( $container_response ), 'INSTAGRAM_ERROR' );
            }
            return;
        }

        $creation_id = $container_data['id'];

        // PASSO 2: Publicar o Container de Mídia gerado
        $publish_endpoint = "https://graph.facebook.com/{$api_version}/{$ig_user_id}/media_publish";
        $publish_body = array(
            'creation_id'  => $creation_id,
            'access_token' => $access_token
        );

        $publish_response = wp_remote_post( $publish_endpoint, array( 'method' => 'POST', 'timeout' => 15, 'body' => $publish_body ) );

        if ( is_wp_error( $publish_response ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Erro crítico cURL ao publicar Container no Instagram: " . $publish_response->get_error_message(), 'INSTAGRAM_ERROR' );
            }
            return;
        }

        $publish_status = wp_remote_retrieve_response_code( $publish_response );
        $publish_data   = json_decode( wp_remote_retrieve_body( $publish_response ), true );

        if ( $publish_status == 200 && !empty( $publish_data['id'] ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Postagem [{$type_log}] publicada com sucesso no Instagram. IG Media ID: {$publish_data['id']}", 'INSTAGRAM_SUCCESS' );
            }
        } else {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                CLM_AutoPosting_Logger::log( "Falha na publicação do Container [{$type_log}] (Código {$publish_status}): " . wp_remote_retrieve_body( $publish_response ), 'INSTAGRAM_ERROR' );
            }
        }
    }
}
new CLM_AutoPosting_Instagram();