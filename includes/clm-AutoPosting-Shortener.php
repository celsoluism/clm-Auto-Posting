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
 * Arquivo: includes/clm-AutoPosting-Shortener.php
 * Função: Integrações seguras com encurtadores de URL (WordPress, tinyURL, bit.ly, shorte.st).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_Shortener {
    
    /**
     * Retorna a URL encurtada com base nas configurações salvas da rede social
     */
    public static function get_short_url( $url, $network_options ) {
        $type = isset( $network_options['shortener'] ) ? $network_options['shortener'] : 'disable';

        if ( $type === 'disable' || empty( $url ) ) {
            return $url;
        }

        switch ( $type ) {
            case 'wordpress':
                return self::shorten_wordpress( $url );
            case 'tinyurl':
                return self::shorten_tinyurl( $url );
            case 'bitly':
                $token = isset( $network_options['bitly_token'] ) ? sanitize_text_field( $network_options['bitly_token'] ) : '';
                return self::shorten_bitly( $url, $token );
            case 'shortest':
                $token = isset( $network_options['shortest_token'] ) ? sanitize_text_field( $network_options['shortest_token'] ) : '';
                return self::shorten_shortest( $url, $token );
            default:
                return $url;
        }
    }

    private static function shorten_wordpress( $url ) {
        $post_id = url_to_postid( $url );
        if ( $post_id ) {
            $shortlink = wp_get_shortlink( $post_id );
            if ( ! empty( $shortlink ) ) {
                return $shortlink;
            }
        }
        return $url; // Se falhar, retorna o original
    }

    private static function shorten_tinyurl( $url ) {
        $api_url = 'https://tinyurl.com/api-create.php?url=' . urlencode( $url );
        $response = wp_remote_get( $api_url, array( 'timeout' => 10 ) );
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = wp_remote_retrieve_body( $response );
            // Valida se o retorno é uma URL estruturada antes de retornar
            if ( ! empty( $body ) && filter_var( trim($body), FILTER_VALIDATE_URL ) ) {
                return trim($body);
            }
        }
        
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            $error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'Resposta inválida do TinyURL.';
            CLM_AutoPosting_Logger::log( "Falha ao encurtar pelo TinyURL para: {$url}. Detalhe: {$error_msg}", 'SHORTENER_ERROR' );
        }
        return $url;
    }

    private static function shorten_bitly( $url, $token ) {
        if ( empty( $token ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) CLM_AutoPosting_Logger::log( "Bit.ly Token vazio para: {$url}", 'SHORTENER_ERROR' );
            return $url;
        }
        
        $api_url = 'https://api-ssl.bitly.com/v4/shorten';
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ),
            'body'    => wp_json_encode( array( 'long_url' => $url ) ),
            'timeout' => 10
        );
        
        $response = wp_remote_post( $api_url, $args );
        
        if ( ! is_wp_error( $response ) && in_array( wp_remote_retrieve_response_code( $response ), array( 200, 201 ) ) ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['link'] ) && ! empty( $body['link'] ) ) {
                return $body['link'];
            }
        }
        
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            $error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'Erro de autenticação ou limite excedido.';
            CLM_AutoPosting_Logger::log( "Falha ao encurtar pelo Bit.ly. Detalhe: {$error_msg}", 'SHORTENER_ERROR' );
        }
        return $url;
    }

    private static function shorten_shortest( $url, $token ) {
        if ( empty( $token ) ) {
            if ( class_exists( 'CLM_AutoPosting_Logger' ) ) CLM_AutoPosting_Logger::log( "Shorte.st Token vazio para: {$url}", 'SHORTENER_ERROR' );
            return $url;
        }
        
        $api_url = 'https://api.shorte.st/v1/data/url';
        $args = array(
            'method'  => 'PUT',
            'headers' => array(
                'public-api-token' => $token,
            ),
            'body'    => array( 'urlToShorten' => $url ),
            'timeout' => 10
        );
        
        $response = wp_remote_request( $api_url, $args );
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = json_decode( wp_remote_retrieve_body( $response ), true );
            if ( isset( $body['shortenedUrl'] ) && ! empty( $body['shortenedUrl'] ) ) {
                return $body['shortenedUrl'];
            }
        }
        
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            $error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'Token inválido ou limite excedido.';
            CLM_AutoPosting_Logger::log( "Falha ao encurtar pelo Shorte.st. Detalhe: {$error_msg}", 'SHORTENER_ERROR' );
        }
        return $url;
    }
}