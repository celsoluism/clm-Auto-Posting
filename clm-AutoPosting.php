<?php
/**
 * ** CABEÇALHO
 * Plugin Name: CLM Auto Posting Express
 * Plugin URI: https://celsoluism.online/
 * Description: Plugin completo de compartilhamento automatizado de produtos, páginas e posts para Facebook, Instagram, Telegram e WhatsApp com agendamento avançado.
 * Version: 2026.0720.2110
 * Author: Celso Luis Martins
 * Author URI: https://celsoluism.online/
 * License: GPL2
 * Arquivo: clm-AutoPosting.php
 * Função: Inicializador principal do plugin, ativação, inicialização de opções e carregamento de módulos.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Proteção contra acesso direto
}

// Constantes Globais do Plugin
define( 'CLM_AUTOPOSTING_VERSION', '2026.0720.2110' );
define( 'CLM_AUTOPOSTING_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLM_AUTOPOSTING_URL', plugin_dir_url( __FILE__ ) );

/**
 * Função de Ativação: Cria/recria e inicializa as opções do plugin no banco de dados sob o prefixo _clm_autoposting_
 */
function clm_autoposting_activate() {
    // Inicializa opções gerais se não existirem
    if ( ! get_option( '_clm_autoposting_geral' ) ) {
        update_option( '_clm_autoposting_geral', array(
            'plugin_enabled'     => '1',
            'enable_logs'        => '1',
            'only_new_posts'     => '0',
            'enable_excerpt'     => '0',
            'max_schedule_limit' => '50',
            'thirdparty_plugins' => '0'
        ) );
    }

    // Inicializa redes sociais separadas sob o prefixo _clm_autoposting_
    $networks = ['facebook_post', 'instagram_post', 'facebook_stories', 'instagram_stories', 'telegram', 'whatsapp_group', 'whatsapp_stories'];
    foreach ( $networks as $net ) {
        $opt_name = '_clm_autoposting_' . $net;
        if ( ! get_option( $opt_name ) ) {
            update_option( $opt_name, array(
                'active'      => '0',
                'delay_type'  => 'instant',
                'delay_value' => '0'
            ) );
        }
    }

    if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
        CLM_AutoPosting_Logger::log( 'Plugin ativado/atualizado com sucesso. Opções e estruturas de banco (_clm_autoposting_) validadas.', 'ACTIVATION' );
    }
}
register_activation_hook( __FILE__, 'clm_autoposting_activate' );

// 1. Carrega Utilitários Base
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-Logger.php';
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-VersionCheck.php';
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-Shortener.php';

// 2. Carrega o Painel Admin (UI)
if ( is_admin() ) {
    require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting_admin.php';
}

// 3. Carrega o Core e Scheduler (Mecanismos de Fundo)
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-Core.php';
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-Scheduler.php';

// 4. Carrega Módulos das Redes Sociais (APIs)
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-Facebook.php';
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-Instagram.php';
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-Telegram.php';
require_once CLM_AUTOPOSTING_DIR . 'includes/clm-AutoPosting-WhatsApp.php';