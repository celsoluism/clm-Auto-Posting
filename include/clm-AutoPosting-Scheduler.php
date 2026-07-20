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
 * Arquivo: includes/clm-AutoPosting-Scheduler.php
 * Função: Mecanismo de filas, limitador de processamento, delay, distância de tempo, dias excluídos, filtros de taxonomia e logs detalhados do CronJob.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CLM_AutoPosting_Scheduler {
    
    public function __construct() {
        add_action( 'clm_autoposting_add_to_scheduler', array( $this, 'process_post' ) );
    }

    public function process_post( $post_id ) {
        CLM_AutoPosting_Logger::log( "Iniciando processamento das regras (WP-Cron) para o Post ID: {$post_id}", 'SCHEDULER' );
        
        // 0. Verifica o limite máximo de agendamentos no Servidor configurado no painel Admin[cite: 4]
        if ( $this->is_schedule_limit_reached() ) {
            CLM_AutoPosting_Logger::log( "Post ID {$post_id} ignorado: O limite máximo de agendamentos no Cron (Maximum Posting per schedule) foi atingido.", 'SCHEDULER_LIMIT' );
            return; // Aborta processamento para proteger o servidor[cite: 4]
        }

        $networks = [
            'facebook_post'     => 'clm_autoposting_do_facebook_post',
            'instagram_post'    => 'clm_autoposting_do_instagram_post',
            'facebook_stories'  => 'clm_autoposting_do_facebook_stories',
            'instagram_stories' => 'clm_autoposting_do_instagram_stories',
            'telegram'          => 'clm_autoposting_do_telegram',
            'whatsapp_group'    => 'clm_autoposting_do_whatsapp_group',
            'whatsapp_stories'  => 'clm_autoposting_do_whatsapp_stories'
        ];
        
        foreach ( $networks as $net_key => $cron_hook ) {
            $opts = get_option( '_clm_autoposting_' . $net_key, array() );
            
            // 1. Verifica se a rede está ativada[cite: 4]
            if ( empty( $opts['active'] ) ) {
                CLM_AutoPosting_Logger::log( "Rede [{$net_key}] desativada para o Post ID {$post_id}. Pulando.", 'SCHEDULER' );
                continue;
            }

            // 2. Validação de Filtros de Taxonomia (Tags, Cats, Include/Exclude)[cite: 4]
            if ( ! $this->validate_taxonomies( $post_id, $opts ) ) {
                CLM_AutoPosting_Logger::log( "Post ID {$post_id} ignorado em {$net_key} devido às regras de Tags/Categorias/Taxonomias.", 'SCHEDULER' );
                continue; 
            }

            // 3. Calcula Delay[cite: 4]
            $delay_type = isset( $opts['delay_type'] ) ? $opts['delay_type'] : 'instant';
            $delay_value = isset( $opts['delay_value'] ) ? (int) $opts['delay_value'] : 0;
            $timestamp = $this->calculate_base_timestamp( $delay_type, $delay_value );
            CLM_AutoPosting_Logger::log( "Post ID {$post_id} em {$net_key} - Delay calculado ({$delay_type}: {$delay_value}): " . wp_date( 'd/m/Y H:i:s', $timestamp ), 'SCHEDULER_DEBUG' );

            // 4. Distância de Tempo (Distance Time)[cite: 4]
            $distance = isset( $opts['distance_time'] ) ? $opts['distance_time'] : 'disable';
            $timestamp = $this->apply_distance_time( $timestamp, $net_key, $distance );
            CLM_AutoPosting_Logger::log( "Post ID {$post_id} em {$net_key} - Após Distance Time ({$distance}): " . wp_date( 'd/m/Y H:i:s', $timestamp ), 'SCHEDULER_DEBUG' );

            // 5. Janela de Horário[cite: 4]
            $time_start = isset( $opts['time_start'] ) ? $opts['time_start'] : '';
            $time_end = isset( $opts['time_end'] ) ? $opts['time_end'] : '';
            $timestamp = $this->apply_time_window( $timestamp, $time_start, $time_end );
            CLM_AutoPosting_Logger::log( "Post ID {$post_id} em {$net_key} - Após Janela de Horário [{$time_start} - {$time_end}]: " . wp_date( 'd/m/Y H:i:s', $timestamp ), 'SCHEDULER_DEBUG' );

            // 6. Dias Excluídos[cite: 4]
            $timestamp = $this->apply_excluded_days( $timestamp, $opts );

            if ( $timestamp === false ) {
                CLM_AutoPosting_Logger::log( "Post ID {$post_id} cancelado em {$net_key} (Todos os dias bloqueados pelas regras de Exclude Days).", 'SCHEDULER' );
                continue;
            }

            // 7. Agenda o evento WP-Cron final[cite: 4]
            $scheduled_result = wp_schedule_single_event( $timestamp, $cron_hook, array( $post_id, $net_key ) );
            if ( $scheduled_result ) {
                update_option( '_clm_last_scheduled_' . $net_key, $timestamp );
                $data_formatada = wp_date( 'd/m/Y H:i:s', $timestamp );
                CLM_AutoPosting_Logger::log( "SUCESSO: Post ID {$post_id} agendado via WP-Cron em {$net_key} para: {$data_formatada} (Hook: {$cron_hook}).", 'SCHEDULER_CRON' );
            } else {
                CLM_AutoPosting_Logger::log( "FALHA: Não foi possível agendar o WP-Cron para o Post ID {$post_id} em {$net_key}.", 'SCHEDULER_ERROR' );
            }
        }
    }

    /**
     * Valida o Limite de Segurança do Host lendo a fila de Cron do WordPress[cite: 4]
     */
    private function is_schedule_limit_reached() {
        $opts_geral = get_option( '_clm_autoposting_geral', array() );
        $max_limit = (int) ( isset( $opts_geral['max_schedule_limit'] ) ? $opts_geral['max_schedule_limit'] : 50 );
        
        if ( $max_limit <= 0 ) return false;

        $cron_array = _get_cron_array();
        $current_count = 0;
        $our_hooks = [
            'clm_autoposting_do_facebook_post', 'clm_autoposting_do_instagram_post',
            'clm_autoposting_do_facebook_stories', 'clm_autoposting_do_instagram_stories',
            'clm_autoposting_do_telegram', 'clm_autoposting_do_whatsapp_group', 'clm_autoposting_do_whatsapp_stories'
        ];

        if ( ! empty( $cron_array ) ) {
            foreach ( $cron_array as $timestamp => $cron_events ) {
                foreach ( $our_hooks as $hook ) {
                    if ( isset( $cron_events[ $hook ] ) ) {
                        $current_count += count( $cron_events[ $hook ] );
                    }
                }
            }
        }
        
        CLM_AutoPosting_Logger::log( "Verificação de Limite do Cron: {$current_count} agendamentos ativos (Limite configurado: {$max_limit})", 'SCHEDULER_LIMIT' );
        return $current_count >= $max_limit;
    }

    /**
     * Valida se o Post atende às regras de Categorias, Tags e Palavras-chave selecionadas[cite: 4]
     */
    private function validate_taxonomies( $post_id, $opts ) {
        $taxonomies = get_post_taxonomies( $post_id );
        $terms = wp_get_post_terms( $post_id, $taxonomies );
        
        $post_slugs = [];
        $post_names = [];
        
        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $post_slugs[] = strtolower( $t->slug );
                $post_names[] = strtolower( $t->name );
            }
        }

        // 1. Verificação das Checkboxes de Tags/Categorias do Painel (Avalia os SLUGS)[cite: 4]
        $req_tags = isset( $opts['tags'] ) && is_array( $opts['tags'] ) ? $opts['tags'] : [];
        $req_cats = isset( $opts['cats'] ) && is_array( $opts['cats'] ) ? $opts['cats'] : [];
        
        $painel_terms = array_merge( $req_tags, $req_cats );
        $painel_terms = array_map( 'strtolower', $painel_terms );

        // Se o usuário marcou alguma tag/cat no painel, o post DEVE ter pelo menos uma delas[cite: 4]
        if ( ! empty( $painel_terms ) ) {
            $has_match = false;
            foreach ( $painel_terms as $term_slug ) {
                if ( in_array( $term_slug, $post_slugs ) ) {
                    $has_match = true;
                    break;
                }
            }
            if ( ! $has_match ) {
                CLM_AutoPosting_Logger::log( "Taxonomia falhou para Post ID {$post_id}: Termos obrigatórios não encontrados no post.", 'SCHEDULER_TAX' );
                return false;
            }
        }

        // 2. Verificação do Campo Customizado (Include / Exclude)[cite: 4]
        $tax_mode = isset( $opts['tax_mode'] ) ? $opts['tax_mode'] : '';
        $tax_keywords = isset( $opts['tax_keywords'] ) ? array_map('trim', explode(',', strtolower($opts['tax_keywords']))) : [];
        $tax_keywords = array_filter( $tax_keywords );

        if ( ! empty( $tax_keywords ) && ! empty( $tax_mode ) ) {
            $keyword_match = false;
            $post_title = strtolower( get_the_title( $post_id ) );
            $search_pool = array_merge( $post_slugs, $post_names ); // Procura em nome e slug da tag[cite: 4]
            
            foreach ( $tax_keywords as $kw ) {
                // Analisa as tags/categorias ou se o Título do post contém a palavra chave[cite: 4]
                if ( in_array( $kw, $search_pool ) || strpos( $post_title, $kw ) !== false ) {
                    $keyword_match = true;
                    break;
                }
            }

            if ( $tax_mode === 'include' && ! $keyword_match ) {
                CLM_AutoPosting_Logger::log( "Modo INCLUDE falhou para Post ID {$post_id}: Palavra-chave não encontrada.", 'SCHEDULER_TAX' );
                return false; // Era pra incluir, mas não achou a palavra[cite: 4]
            }

            if ( $tax_mode === 'exclude' && $keyword_match ) {
                CLM_AutoPosting_Logger::log( "Modo EXCLUDE bloqueou o Post ID {$post_id}: Palavra-chave proibida encontrada.", 'SCHEDULER_TAX' );
                return false; // Era pra excluir, e a palavra proibida foi encontrada[cite: 4]
            }
        }

        return true; // Passou em todas as validações[cite: 4]
    }

    private function calculate_base_timestamp( $type, $value ) {
        $current_time = current_time( 'timestamp' );
        if ( $type === 'instant' || $value <= 0 ) return $current_time;
        switch ( $type ) {
            case 'min': return $current_time + ( $value * MINUTE_IN_SECONDS );
            case 'hour': return $current_time + ( $value * HOUR_IN_SECONDS );
            case 'day': return $current_time + ( $value * DAY_IN_SECONDS );
            case 'week': return $current_time + ( $value * WEEK_IN_SECONDS );
            default: return $current_time;
        }
    }

    private function apply_distance_time( $timestamp, $network, $distance ) {
        if ( $distance === 'disable' ) return $timestamp;
        $last_scheduled = (int) get_option( '_clm_last_scheduled_' . $network, 0 );
        $distance_seconds = 0;
        switch ( $distance ) {
            case '5min': $distance_seconds = 5 * MINUTE_IN_SECONDS; break;
            case '10min': $distance_seconds = 10 * MINUTE_IN_SECONDS; break;
            case '30min': $distance_seconds = 30 * MINUTE_IN_SECONDS; break;
            case '1hour': $distance_seconds = HOUR_IN_SECONDS; break;
        }
        if ( $last_scheduled > 0 && ( $timestamp < ( $last_scheduled + $distance_seconds ) ) ) {
            return $last_scheduled + $distance_seconds;
        }
        return $timestamp;
    }

    private function apply_time_window( $timestamp, $time_start, $time_end ) {
        if ( empty( $time_start ) || empty( $time_end ) ) return $timestamp;
        $hora_timestamp = wp_date( 'H:i', $timestamp );
        if ( $hora_timestamp < $time_start || $hora_timestamp > $time_end ) {
            $is_past_end = $hora_timestamp > $time_end;
            $target_date = $is_past_end ? wp_date( 'Y-m-d', $timestamp + DAY_IN_SECONDS ) : wp_date( 'Y-m-d', $timestamp );
            $target_datetime = $target_date . ' ' . $time_start . ':00';
            $dt = new DateTime( $target_datetime, wp_timezone() );
            return $dt->getTimestamp();
        }
        return $timestamp;
    }

    private function apply_excluded_days( $timestamp, $opts ) {
        $mapa_dias = ['Mon'=>'seg', 'Tue'=>'ter', 'Wed'=>'qua', 'Thu'=>'qui', 'Fri'=>'sex', 'Sat'=>'sab', 'Sun'=>'dom'];
        $limite_tentativas = 7;
        $tentativas = 0;
        while ( $tentativas < $limite_tentativas ) {
            $dia_slug = $mapa_dias[wp_date( 'D', $timestamp )];
            if ( ! empty( $opts['exclude_day_' . $dia_slug] ) ) {
                $timestamp += DAY_IN_SECONDS;
                $tentativas++;
            } else {
                return $timestamp;
            }
        }
        return false;
    }
}
new CLM_AutoPosting_Scheduler();