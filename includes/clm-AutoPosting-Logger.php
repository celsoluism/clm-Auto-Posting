<?php
/**
 * ** CABEÇALHO
 * Plugin Name: CLM Auto Posting Express
 * Plugin URI: https://celsoluism.online/
 * Description: Plugin completo de compartilhamento automatizado de produtos, páginas e posts para Facebook, Instagram, Telegram e WhatsApp com agendamento avançado.
 * Version: 2026.0720.2100
 * Author: Celso Luis Martins
 * Author URI: https://celsoluism.online/
 * License: GPL2
 * Arquivo: includes/clm-AutoPosting-Logger.php
 * Função: Classe utilitária para gravação detalhada de logs no arquivo txt na raiz do plugin.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_Logger {
    
    /**
     * Grava a mensagem no log detalhado se ativado no painel
     */
    public static function log( $message, $type = 'INFO' ) {
        $opts = get_option( '_clm_autoposting_geral', array() );
        
        // Só grava se o log estiver ativado no painel (checkbox)[cite: 2]
        if ( empty( $opts['enable_logs'] ) ) {
            return;
        }

        // Garante que a constante do diretório existe para evitar Fatal Errors[cite: 2]
        if ( ! defined( 'CLM_AUTOPOSTING_DIR' ) ) {
            return;
        }

        $log_file = CLM_AUTOPOSTING_DIR . 'clm_AutoPoster_logs.txt';
        
        // Converte arrays ou objetos em string legível para logs ultra detalhados
        if ( is_array( $message ) || is_object( $message ) ) {
            $message = print_r( $message, true );
        }

        $time = current_time( 'Y-m-d H:i:s' );
        $formatted_message = "[{$time}] [{$type}] - {$message}" . PHP_EOL;

        // Tenta criar/atualizar o arquivo de log com segurança e exclusividade[cite: 2]
        @file_put_contents( $log_file, $formatted_message, FILE_APPEND | LOCK_EX );
    }
}