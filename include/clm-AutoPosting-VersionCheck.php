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
 * Arquivo: includes/clm-AutoPosting-VersionCheck.php
 * Função: Módulo integrado para checagem remota e injeção do update nativo na listagem de plugins do WordPress.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_VersionCheck {
    
    private $remote_url = 'https://celsoluism.online/wp-content/uploads/downloads/wordpress_plugins/clm-AutoPosting-Changelog.txt';
    private $plugin_slug = 'clm-autoposting';
    
    public function __construct() {
        // Filtro para o badge interno do painel do plugin
        add_filter( 'clm_autoposting_version_badge', array( $this, 'render_version_status' ) );
        
        // Hooks nativos do WordPress para exibir a atualização na listagem padrão (plugins.php)
        add_filter( 'site_transient_update_plugins', array( $this, 'check_update' ) );
        add_filter( 'plugins_api', array( $this, 'plugin_popup' ), 10, 3 );
    }

    /**
     * Efetua a leitura do Changelog remoto salvando cache por 12 horas
     */
    public function get_remote_version() {
        $remote_version = get_transient('clm_remote_version_check');
        
        if ( false === $remote_version ) {
            $response = wp_remote_get( $this->remote_url, array('timeout' => 10, 'sslverify' => false) );
            
            if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
                $body = wp_remote_retrieve_body( $response );
                
                if ( preg_match( '/(?:Versão\s*Atual|Version):\s*([0-9\.]+)/ui', $body, $matches ) ) {
                    $remote_version = trim($matches[1]);
                    set_transient('clm_remote_version_check', $remote_version, 12 * HOUR_IN_SECONDS);
                    return $remote_version;
                }
            } else {
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    $error_msg = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP Status Code: ' . wp_remote_retrieve_response_code( $response );
                    CLM_AutoPosting_Logger::log( "Falha ao verificar atualizações no Changelog Remoto. Motivo: {$error_msg}", 'VERSION_CHECK_ERROR' );
                }
            }
            return 'Indisponível';
        }
        
        return $remote_version;
    }

    /**
     * Renderiza o HTML final baseando-se na versão atual da constante
     */
    public function render_version_status( $default_html ) {
        $remote_version = $this->get_remote_version();
        $local_version  = defined('CLM_AUTOPOSTING_VERSION') ? CLM_AUTOPOSTING_VERSION : '0.0.0';

        if ( $remote_version !== 'Indisponível' ) {
            if ( version_compare( $local_version, $remote_version, '<' ) ) {
                return '<span class="clm-update-warning">Atualização Disponível: v' . esc_html($remote_version) . '</span>';
            } else {
                return '<span class="clm-update-notice">Plugin Atualizado</span>';
            }
        }

        return '<span class="clm-update-warning">Erro ao checar versão</span>';
    }

    /**
     * Intercepta a transient de atualizações do WordPress para injetar a notificação na tabela de plugins
     */
    public function check_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        // Limpa o transient de cache para forçar a verificação imediata se necessário
        delete_transient('clm_remote_version_check');

        $remote_version = $this->get_remote_version();
        $local_version  = defined('CLM_AUTOPOSTING_VERSION') ? CLM_AUTOPOSTING_VERSION : '0.0.0';
        
        $plugin_basename = plugin_basename( CLM_AUTOPOSTING_DIR . '../clm-AutoPosting.php' );

        if ( $remote_version !== 'Indisponível' && version_compare( $local_version, $remote_version, '<' ) ) {
            $obj = new stdClass();
            $obj->slug = $this->plugin_slug;
            $obj->plugin = $plugin_basename;
            $obj->new_version = $remote_version;
            $obj->url = 'https://celsoluism.online/';
            // Aponta para o arquivo compactado `.zip` do plugin no servidor para download automático
            $obj->package = 'https://celsoluism.online/wp-content/uploads/downloads/wordpress_plugins/clm-AutoPosting.zip';
            $obj->tested = '6.5';
            $obj->requires_php = '7.4';

            $transient->response[$plugin_basename] = $obj;
        }

        return $transient;
    }

    /**
     * Exibe as informações do changelog no pop-up de detalhes da atualização no WordPress
     */
    public function plugin_popup( $result, $action, $args ) {
        if ( $action !== 'plugin_information' ) {
            return $result;
        }

        if ( isset( $args->slug ) && $args->slug === $this->plugin_slug ) {
            $response = wp_remote_get( $this->remote_url, array('timeout' => 10, 'sslverify' => false) );
            $changelog = ! is_wp_error( $response ) ? wp_remote_retrieve_body( $response ) : 'Informações indisponíveis no momento.';

            $info = new stdClass();
            $info->name = 'CLM Auto Posting Express';
            $info->slug = $this->plugin_slug;
            $info->version = $this->get_remote_version();
            $info->author = '<a href="https://celsoluism.online/">Celso Luis Martins</a>';
            $info->homepage = 'https://celsoluism.online/';
            $info->sections = array(
                'changelog' => nl2br( esc_html( $changelog ) )
            );

            return $info;
        }

        return $result;
    }
}
new CLM_AutoPosting_VersionCheck();