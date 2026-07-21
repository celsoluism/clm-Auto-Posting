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
 * Arquivo: includes/clm-AutoPosting_admin.php
 * Função: Controle de chamadas pelo painel admin, renderização do template visual, salvamento seguro e gerenciamento do banco de dados (_clm_autoposting_).
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Proteção contra acesso direto
}

class CLM_AutoPosting_Admin {

    private static $instance = null;
    private $plugin_version = '2026.0720.2110';

    public static function get_instance() {
        if ( self::$instance === null ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
        add_action( 'admin_init', array( $this, 'register_plugin_settings' ) );
        add_action( 'admin_init', array( $this, 'handle_database_actions' ) );
    }

    /**
     * Adiciona o menu principal do plugin ao painel do WordPress
     */
    public function add_admin_menu() {
        add_menu_page(
            'CLM Auto Posting',
            'Auto Posting',
            'manage_options',
            'clm-autoposting',
            array( $this, 'render_admin_page' ),
            'dashicons-share',
            80
        );
    }

    /**
     * Enfileira os estilos e scripts do painel
     */
    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_clm-autoposting' !== $hook ) {
            return;
        }

        wp_register_style( 'clm-autoposting-admin-css', false );
        wp_enqueue_style( 'clm-autoposting-admin-css' );
        
        $custom_css = "
            .clm-wrapper { font-family: 'Segoe UI', Helvetica, Arial, sans-serif; margin: 20px 20px 0 0; color: #333; }
            .clm-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
            .clm-title { font-size: 24px; font-weight: 400; color: #23282d; margin: 0; }
            .clm-version-badge { background-color: #ff5722; color: #fff; padding: 4px 12px; font-size: 12px; font-weight: bold; border-radius: 4px; display: inline-flex; align-items: center; gap: 8px; }
            .clm-update-notice { background: #d4edda; color: #155724; padding: 2px 8px; border-radius: 3px; font-size: 11px; }
            .clm-update-warning { background: #f8d7da; color: #721c24; padding: 2px 8px; border-radius: 3px; font-size: 11px; }
            .clm-tabs-nav { display: flex; gap: 6px; border-bottom: 1px solid #ccc; padding-bottom: 0; margin-bottom: 20px; }
            .clm-tab-btn { background: #e0e0e0; border: 1px solid #ccc; border-bottom: none; padding: 10px 16px; font-size: 13px; font-weight: 600; cursor: pointer; border-radius: 4px 4px 0 0; color: #555; transition: all 0.2s; text-decoration: none; }
            .clm-tab-btn.active { background: #fff; color: #333; border-bottom: 1px solid #fff; margin-bottom: -1px; position: relative; z-index: 2; }
            .clm-container { display: flex; gap: 20px; align-items: flex-start; }
            .clm-sidebar-menu { width: 220px; display: flex; flex-direction: column; gap: 8px; }
            .clm-subtab-btn { background: #fff; border: 1px solid #ddd; padding: 12px 15px; text-align: left; font-weight: 600; font-size: 13px; cursor: pointer; border-radius: 4px; color: #444; transition: all 0.2s; text-decoration: none; display: flex; align-items: center; justify-content: space-between; }
            .clm-subtab-btn:hover { background: #f9f9f9; }
            .clm-subtab-btn.active { background: #ff5722; color: #fff; border-color: #ff5722; }
            .clm-main-content { flex: 1; background: #fff; padding: 30px; border-radius: 6px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); border: 1px solid #e2e8f0; }
            .clm-section-title { font-size: 18px; font-weight: bold; margin-bottom: 20px; padding-bottom: 8px; border-bottom: 1px solid #eee; }
            .clm-form-group { display: flex; align-items: flex-start; margin-bottom: 20px; }
            .clm-form-group label.clm-label-main { width: 250px; font-weight: 600; color: #444; flex-shrink: 0; padding-top: 5px; }
            .clm-form-control { flex: 1; }
            .clm-inline-checkboxes { display: flex; gap: 15px; align-items: center; flex-wrap: wrap; }
            .clm-btn-primary { background: #ff5722; color: white; border: none; padding: 10px 20px; font-size: 14px; font-weight: bold; border-radius: 4px; cursor: pointer; box-shadow: 0 2px 4px rgba(255,87,34,0.3); }
            .clm-btn-primary:hover { background: #e64a19; }
            .clm-btn-secondary { background: #0073aa; color: white; border: none; padding: 8px 15px; font-size: 13px; font-weight: bold; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin-right: 10px; }
            .clm-btn-secondary:hover { background: #005177; color: white; }
            .clm-btn-danger { background: #dc3545; color: white; border: none; padding: 8px 15px; font-size: 13px; font-weight: bold; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
            .clm-btn-danger:hover { background: #bb2d3b; color: white; }
            .clm-server-notice { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 12px; border-radius: 4px; margin-top: 5px; font-weight: 500; display: inline-block; }
            .clm-api-block, .clm-functions-block, .clm-advanced-image3-block { background: #f8fafc; border: 1px dashed #cbd5e1; padding: 20px; margin-top: 30px; border-radius: 6px; }
            .clm-shortener-api-box { display: none; margin-top: 10px; background: #f1f5f9; padding: 10px; border-left: 3px solid #ff5722; }
            .clm-multi-select-box { border: 1px solid #ccc; padding: 10px; border-radius: 4px; max-height: 150px; overflow-y: auto; background: #fff; }
            .clm-multi-select-box strong { display: block; margin: 10px 0 5px 0; color: #333; }
            .clm-multi-select-box strong:first-child { margin-top: 0; }
            /* Estilo Switch Toggle */
            .clm-switch { position: relative; display: inline-block; width: 44px; height: 22px; vertical-align: middle; margin-right: 8px; }
            .clm-switch input { opacity: 0; width: 0; height: 0; }
            .clm-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #ccc; transition: .4s; border-radius: 22px; }
            .clm-slider:before { position: absolute; content: ''; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
            input:checked + .clm-slider { background-color: #ff5722; }
            input:checked + .clm-slider:before { transform: translateX(22px); }
            .clm-tags-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px; font-size: 12px; }
            .clm-tag-item { display: flex; align-items: flex-start; gap: 6px; }
            .clm-tag-badge { background: #e2e8f0; border: 1px solid #cbd5e1; border-radius: 3px; padding: 1px 5px; font-family: monospace; color: #d63384; font-weight: bold; flex-shrink: 0; }
            /* Estilos de Alerta das Imagens de API */
            .clm-alert-warning { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 10px 15px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; }
            .clm-alert-info { background: #e2e8f0; border: 1px solid #cbd5e1; color: #334155; padding: 12px 15px; border-radius: 4px; margin-bottom: 15px; font-size: 13px; display: flex; align-items: flex-start; gap: 10px; }
            .clm-table-accounts { width: 100%; border-collapse: collapse; margin-top: 15px; background: #fff; }
            .clm-table-accounts th, .clm-table-accounts td { border: 1px solid #cbd5e1; padding: 10px; text-align: left; font-size: 13px; }
            .clm-table-accounts th { background: #f1f5f9; font-weight: 600; color: #334155; }
            .clm-btn-delete { background: #dc3545; color: white; border: none; padding: 5px 12px; border-radius: 3px; cursor: pointer; font-weight: bold; font-size: 11px; }
            .clm-btn-delete:hover { background: #bb2d3b; }
            .clm-btn-add-account { background: transparent; color: #0073aa; border: none; font-weight: 600; cursor: pointer; padding: 0; display: inline-flex; align-items: center; gap: 4px; font-size: 13px; margin-top: 10px; text-decoration: none; }
            .clm-btn-add-account:hover { color: #005177; text-decoration: underline; }
        ";
        wp_add_inline_style( 'clm-autoposting-admin-css', $custom_css );
    }

    /**
     * Registra as opções nativas no Banco de Dados sob o prefixo _clm_autoposting_
     */
    public function register_plugin_settings() {
        register_setting( 'clm_autoposting_settings_group', '_clm_autoposting_geral', array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) );
        
        $networks = ['facebook_post', 'instagram_post', 'facebook_stories', 'instagram_stories', 'telegram', 'whatsapp_group', 'whatsapp_stories'];
        foreach ($networks as $net) {
            register_setting( 'clm_autoposting_settings_group', '_clm_autoposting_' . $net, array( 'sanitize_callback' => array( $this, 'sanitize_settings' ) ) );
        }
    }

    /**
     * Callback para garantir que os dados sejam salvos com logs detalhados e sem perda
     */
    public function sanitize_settings( $input ) {
        if ( ! is_array( $input ) ) {
            return array();
        }
        if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
            CLM_AutoPosting_Logger::log( "Configurações salvas e higienizadas com sucesso no banco de dados.", 'SETTINGS_SAVE' );
        }
        return $input;
    }

    /**
     * Manipula ações do banco de dados (Recriar ou Remover) solicitadas pelo painel
     */
    public function handle_database_actions() {
        if ( isset( $_GET['clm_action'] ) && current_user_can( 'manage_options' ) ) {
            $action = sanitize_text_field( $_GET['clm_action'] );
            
            if ( $action === 'recreate_db' ) {
                clm_autoposting_activate();
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    CLM_AutoPosting_Logger::log( "Banco de dados (_clm_autoposting_) recriado/atualizado manualmente pelo painel.", 'DB_RECREATE' );
                }
                wp_redirect( admin_url( 'admin.php?page=clm-autoposting&tab=configuracoes&db_status=recreated' ) );
                exit;
            }
            
            if ( $action === 'drop_db' ) {
                global $wpdb;
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_clm_autoposting_%'" );
                $wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_clm_last_scheduled_%'" );
                delete_transient( 'clm_remote_version_check' );
                
                if ( class_exists( 'CLM_AutoPosting_Logger' ) ) {
                    CLM_AutoPosting_Logger::log( "ALERTA: Banco de dados e opções (_clm_autoposting_) removidos completamente do sistema.", 'DB_DROP' );
                }
                wp_redirect( admin_url( 'admin.php?page=clm-autoposting&tab=configuracoes&db_status=dropped' ) );
                exit;
            }
        }
    }

    /**
     * Função para verificar versão remotamente
     */
    private function check_remote_version() {
        $remote_url = 'https://celsoluism.online/wp-content/uploads/downloads/wordpress_plugins/clm-AutoPosting-Changelog.txt';
        $response = wp_remote_get( $remote_url, array('timeout' => 5, 'sslverify' => false) );
        
        if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
            $body = wp_remote_retrieve_body( $response );
            if ( preg_match( '/(?:Versão\s*Atual|Version):\s*([0-9\.]+)/ui', $body, $matches ) ) {
                return trim($matches[1]);
            }
        }
        return 'Indisponível';
    }

    /**
     * Renderiza o layout estrutural
     */
    public function render_admin_page() {
        $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'configuracoes';
        $active_sub = isset( $_GET['sub'] ) ? sanitize_text_field( $_GET['sub'] ) : 'facebook_post';
        
        // Mensagens de status do banco
        if ( isset( $_GET['db_status'] ) ) {
            if ( $_GET['db_status'] === 'recreated' ) {
                echo '<div class="notice notice-success is-dismissible"><p><strong>Sucesso:</strong> Banco de dados (_clm_autoposting_) recriado e atualizado com sucesso!</p></div>';
            } elseif ( $_GET['db_status'] === 'dropped' ) {
                echo '<div class="notice notice-warning is-dismissible"><p><strong>Aviso:</strong> Todo o banco de dados e opções (_clm_autoposting_) foram removidos com sucesso.</p></div>';
            }
        }
        
        // Checagem de versão com Transient
        $remote_version = get_transient('clm_remote_version_check');
        if ( false === $remote_version ) {
            $remote_version = $this->check_remote_version();
            set_transient('clm_remote_version_check', $remote_version, 12 * HOUR_IN_SECONDS);
        }

        $version_status_html = '';
        if ($remote_version !== 'Indisponível') {
            if (version_compare($this->plugin_version, $remote_version, '<')) {
                $version_status_html = '<span class="clm-update-warning">Atualização Disponível: v' . esc_html($remote_version) . '</span>';
            } else {
                $version_status_html = '<span class="clm-update-notice">Plugin Atualizado</span>';
            }
        }

        $form_action = admin_url( 'options.php' );
        $redirect_url = admin_url( 'admin.php?page=clm-autoposting&tab=' . $active_tab . ( $active_tab === 'autoposting' ? '&sub=' . $active_sub : '' ) );
        ?>
        <div class="wrap clm-wrapper">
            <div class="clm-header">
                <h1 class="clm-title">CLM Auto Posting Express</h1>
                <div class="clm-version-badge">
                    Versão <?php echo esc_html($this->plugin_version); ?>
                    <?php echo $version_status_html; ?>
                </div>
            </div>

            <!-- Navegação Superior -->
            <div class="clm-tabs-nav">
                <a href="?page=clm-autoposting&tab=configuracoes" class="clm-tab-btn <?php echo $active_tab === 'configuracoes' ? 'active' : ''; ?>">CONFIGURAÇÕES</a>
                <a href="?page=clm-autoposting&tab=autoposting&sub=<?php echo esc_attr($active_sub); ?>" class="clm-tab-btn <?php echo $active_tab === 'autoposting' ? 'active' : ''; ?>">AUTO POSTING</a>
                <a href="?page=clm-autoposting&tab=informacoes" class="clm-tab-btn <?php echo $active_tab === 'informacoes' ? 'active' : ''; ?>">INFORMAÇÕES</a>
            </div>

            <form method="post" action="<?php echo esc_url( $form_action ); ?>">
                <?php 
                settings_fields( 'clm_autoposting_settings_group' ); 
                echo '<input type="hidden" name="_wp_http_referer" value="' . esc_attr( $redirect_url ) . '" />';
                ?>

                <div class="clm-container">
                    
                    <!-- Menu Lateral para a aba Auto Posting -->
                    <?php if ( 'autoposting' === $active_tab ) : ?>
                        <div class="clm-sidebar-menu">
                            <?php
                            $menus = [
                                'facebook_post'     => 'Facebook Post',
                                'instagram_post'    => 'Instagram Post',
                                'facebook_stories'  => 'Facebook Stories',
                                'instagram_stories' => 'Instagram Stories',
                                'telegram'          => 'Telegram',
                                'whatsapp_group'    => 'WhatsApp Group',
                                'whatsapp_stories'  => 'WhatsApp Stories'
                            ];
                            foreach ($menus as $key => $label) {
                                $active_class = $active_sub === $key ? 'active' : '';
                                echo '<a href="?page=clm-autoposting&tab=autoposting&sub='. $key .'" class="clm-subtab-btn '. $active_class .'">'. $label .' <span class="dashicons dashicons-arrow-right-alt2"></span></a>';
                            }
                            ?>
                        </div>
                    <?php endif; ?>

                    <!-- Conteúdo Principal -->
                    <div class="clm-main-content">
                        <?php
                        if ( 'configuracoes' === $active_tab ) {
                            $this->render_config_tab();
                        } elseif ( 'informacoes' === $active_tab ) {
                            $this->render_info_tab();
                        } elseif ( 'autoposting' === $active_tab ) {
                            $this->render_scheduler_network_tab( $active_sub );
                        }
                        ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Script Dinâmico para Encurtadores, Alternância de Auth Type e Adição Dinâmica de Contas do Facebook -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var shortenerSelects = document.querySelectorAll('.clm-shortener-select');
                shortenerSelects.forEach(function(select) {
                    select.addEventListener('change', function() {
                        var val = this.value;
                        var container = this.closest('.clm-form-control');
                        var bitlyBox = container.querySelector('.box-bitly');
                        var shortestBox = container.querySelector('.box-shortest');
                        
                        if(bitlyBox) bitlyBox.style.display = (val === 'bitly') ? 'block' : 'none';
                        if(shortestBox) shortestBox.style.display = (val === 'shortest') ? 'block' : 'none';
                    });
                    select.dispatchEvent(new Event('change'));
                });

                var authRadios = document.querySelectorAll('input[name*="[auth_type]"]');
                authRadios.forEach(function(radio) {
                    radio.addEventListener('change', function() {
                        var wrapper = this.closest('.clm-api-block');
                        if (!wrapper) return;
                        var appMethodBlock = wrapper.querySelector('.clm-auth-app-method');
                        var graphApiBlock = wrapper.querySelector('.clm-auth-graph-api');
                        
                        if (this.value === 'app_method') {
                            if(appMethodBlock) appMethodBlock.style.display = 'block';
                            if(graphApiBlock) graphApiBlock.style.display = 'none';
                        } else {
                            if(appMethodBlock) appMethodBlock.style.display = 'none';
                            if(graphApiBlock) graphApiBlock.style.display = 'block';
                        }
                    });
                    if(radio.checked) {
                        radio.dispatchEvent(new Event('change'));
                    }
                });

                var addAccountBtn = document.querySelector('.clm-btn-add-account');
                if (addAccountBtn) {
                    addAccountBtn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var tableBody = document.querySelector('.clm-table-accounts tbody');
                        if (!tableBody) return;
                        
                        var emptyRow = tableBody.querySelector('tr td[colspan="3"]');
                        if (emptyRow) {
                            emptyRow.closest('tr').remove();
                        }
                        
                        var newIndex = tableBody.querySelectorAll('tr').length;
                        var optionKeyPrefix = addAccountBtn.closest('.clm-api-block').querySelector('input[name*="[app_id]"]') ? addAccountBtn.closest('.clm-api-block').querySelector('input[name*="[app_id]"]').getAttribute('name').split('[')[0] : '_clm_autoposting_facebook_post';
                        
                        var newRow = document.createElement('tr');
                        newRow.innerHTML = '<td><input type="text" name="' + optionKeyPrefix + '[connected_accounts][' + newIndex + '][userid]" value="" placeholder="User ID" style="width: 100%;" /></td>' +
                                           '<td><input type="text" name="' + optionKeyPrefix + '[connected_accounts][' + newIndex + '][name]" value="" placeholder="Account Name" style="width: 100%;" /></td>' +
                                           '<td><button type="button" class="clm-btn-delete" onclick="this.closest(\'tr\').remove();">Delete Account</button></td>';
                        tableBody.appendChild(newRow);
                    });
                }
            });

            // Função de Confirmação para Remover o Banco de Dados
            function clmConfirmDropDB(url) {
                if (confirm('ATENÇÃO: Tem certeza absoluta que deseja remover todo o banco de dados e configurações do CLM Auto Posting? Esta ação não pode ser desfeita.')) {
                    window.location.href = url;
                }
            }
        </script>
        <?php
    }

    /**
     * ABA: CONFIGURAÇÕES (Com botões de gerenciamento de banco de dados no rodapé)
     */
    private function render_config_tab() {
        $opts = get_option( '_clm_autoposting_geral', array() );
        $recreate_url = admin_url( 'admin.php?page=clm-autoposting&tab=configuracoes&clm_action=recreate_db' );
        $drop_url = admin_url( 'admin.php?page=clm-autoposting&tab=configuracoes&clm_action=drop_db' );
        ?>
        <div class="clm-section-title">Configurações Gerais do Sistema</div>
        
        <div class="clm-form-group">
            <label class="clm-label-main">Ativar Plugin?</label>
            <div class="clm-form-control">
                <label><input type="checkbox" name="_clm_autoposting_geral[plugin_enabled]" value="1" <?php checked( '1', isset( $opts['plugin_enabled'] ) ? $opts['plugin_enabled'] : '0' ); ?> /> Ativar para permitir compartilhamentos</label>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Ativar Logs:</label>
            <div class="clm-form-control">
                <label><input type="checkbox" name="_clm_autoposting_geral[enable_logs]" value="1" <?php checked( '1', isset( $opts['enable_logs'] ) ? $opts['enable_logs'] : '0' ); ?> /> Habilitar log completo de atividades e falhas no arquivo clm_AutoPoster_logs.txt</label>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Enable Only for new Posts?</label>
            <div class="clm-form-control">
                <label><input type="checkbox" name="_clm_autoposting_geral[only_new_posts]" value="1" <?php checked( '1', isset( $opts['only_new_posts'] ) ? $opts['only_new_posts'] : '0' ); ?> /> Compartilhar apenas novos posts após ativação</label>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Enable Excerpt Content?</label>
            <div class="clm-form-control">
                <label><input type="checkbox" name="_clm_autoposting_geral[enable_excerpt]" value="1" <?php checked( '1', isset( $opts['enable_excerpt'] ) ? $opts['enable_excerpt'] : '0' ); ?> /> Usar o resumo (excerpt) se disponível</label>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Maximum Posting per schedule</label>
            <div class="clm-form-control">
                <input type="number" name="_clm_autoposting_geral[max_schedule_limit]" value="<?php echo esc_attr( isset( $opts['max_schedule_limit'] ) ? $opts['max_schedule_limit'] : '50' ); ?>" style="width: 80px;" />
                <?php 
                $current_queue = 0; 
                $max_limit = (int)( isset( $opts['max_schedule_limit'] ) ? $opts['max_schedule_limit'] : 50 );
                if ( $current_queue >= $max_limit && $max_limit > 0 ) {
                    echo '<br><div class="clm-server-notice">Scheduler chegou ao limite do Servidor configurado, altere os limites no menu Configurações!</div>';
                }
                ?>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Allow autopost from thirdparty plugins:</label>
            <div class="clm-form-control">
                <label><input type="checkbox" name="_clm_autoposting_geral[thirdparty_plugins]" value="1" <?php checked( '1', isset( $opts['thirdparty_plugins'] ) ? $opts['thirdparty_plugins'] : '0' ); ?> /> Permitir capturar posts criados por outros plugins</label>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Debug Log:</label>
            <div class="clm-form-control">
                <a href="<?php echo esc_url( CLM_AUTOPOSTING_URL . 'clm_AutoPoster_logs.txt' ); ?>" target="_blank" class="button">Acessar clm_AutoPoster_logs.txt</a>
            </div>
        </div>
        
        <hr style="margin: 30px 0; border-top: 1px solid #eee; border-bottom: none;" />
        
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
            <div>
                <?php submit_button( 'Salvar Configurações Gerais', 'clm-btn-primary', 'submit', false ); ?>
            </div>
            <div>
                <a href="<?php echo esc_url( $recreate_url ); ?>" class="clm-btn-secondary">Recriar Banco de dados!</a>
                <a href="javascript:void(0);" onclick="clmConfirmDropDB('<?php echo esc_url( $drop_url ); ?>');" class="clm-btn-danger">Remover banco de dados!</a>
            </div>
        </div>
        <?php
    }

    /**
     * ABA: INFORMAÇÕES
     */
    private function render_info_tab() {
        ?>
        <div class="clm-section-title">Informações do Plugin</div>
        <table class="wp-list-table widefat fixed striped">
            <tbody>
                <tr><td><strong>Nome do Plugin:</strong></td><td>CLM Auto Posting Express</td></tr>
                <tr><td><strong>Criador:</strong></td><td>Celso Luis Martins</td></tr>
                <tr><td><strong>Versão Atual Instalada:</strong></td><td><?php echo esc_html($this->plugin_version); ?></td></tr>
                <tr>
                    <td><strong>Changelog Oficial:</strong></td>
                    <td>
                        <a href="https://celsoluism.online/wp-content/uploads/downloads/wordpress_plugins/clm-AutoPosting-Changelog.txt" target="_blank">Acessar Changelog (celsoluism.online)</a><br>
                        <span class="description">Verifique também o arquivo local <code>clm-AutoPosting-Changelog.txt</code> na raiz do plugin.</span>
                    </td>
                </tr>
            </tbody>
        </table>
        <?php
    }

    /**
     * ABA: AUTO POSTING (Redes Específicas)
     */
    private function render_scheduler_network_tab( $network ) {
        $display_name = strtoupper( str_replace('_', ' ', $network) );
        $option_key = '_clm_autoposting_' . $network;
        $opts = get_option( $option_key, array() );

        $post_tags = get_terms( array('taxonomy' => 'post_tag', 'hide_empty' => false) );
        $product_tags = taxonomy_exists('product_tag') ? get_terms( array('taxonomy' => 'product_tag', 'hide_empty' => false) ) : array();
        
        $post_cats = get_terms( array('taxonomy' => 'category', 'hide_empty' => false) );
        $product_cats = taxonomy_exists('product_cat') ? get_terms( array('taxonomy' => 'product_cat', 'hide_empty' => false) ) : array();

        $saved_tags = isset( $opts['tags'] ) && is_array( $opts['tags'] ) ? $opts['tags'] : array();
        $saved_cats = isset( $opts['cats'] ) && is_array( $opts['cats'] ) ? $opts['cats'] : array();
        ?>
        <div class="clm-section-title">Configurações para: <?php echo esc_html( $display_name ); ?></div>

        <div class="clm-form-group">
            <label class="clm-label-main">Ativar este compartilhamento?</label>
            <div class="clm-form-control">
                <label><input type="checkbox" name="<?php echo $option_key; ?>[active]" value="1" <?php checked( '1', isset( $opts['active'] ) ? $opts['active'] : '0' ); ?> /> Ativar Auto Post para <?php echo esc_html($display_name); ?></label>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Schedule Wall Posts Delay:</label>
            <div class="clm-form-control">
                <select name="<?php echo $option_key; ?>[delay_type]">
                    <option value="instant" <?php selected( 'instant', isset( $opts['delay_type'] ) ? $opts['delay_type'] : '' ); ?>>Instantâneo</option>
                    <option value="min" <?php selected( 'min', isset( $opts['delay_type'] ) ? $opts['delay_type'] : '' ); ?>>Minuto(s)</option>
                    <option value="hour" <?php selected( 'hour', isset( $opts['delay_type'] ) ? $opts['delay_type'] : '' ); ?>>Hora(s)</option>
                    <option value="day" <?php selected( 'day', isset( $opts['delay_type'] ) ? $opts['delay_type'] : '' ); ?>>Dia(s)</option>
                    <option value="week" <?php selected( 'week', isset( $opts['delay_type'] ) ? $opts['delay_type'] : '' ); ?>>Semana(s)</option>
                </select>
                <input type="number" name="<?php echo $option_key; ?>[delay_value]" value="<?php echo esc_attr( isset( $opts['delay_value'] ) ? $opts['delay_value'] : '0' ); ?>" style="width: 70px; margin-left: 10px;" min="0" />
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Exclude Posting Days:</label>
            <div class="clm-form-control clm-inline-checkboxes">
                <?php
                $days = ['seg'=>'Segunda', 'ter'=>'Terça', 'qua'=>'Quarta', 'qui'=>'Quinta', 'sex'=>'Sexta', 'sab'=>'Sábado', 'dom'=>'Domingo'];
                foreach ( $days as $key => $name ) {
                    $checked = isset( $opts['exclude_day_' . $key] ) ? '1' : '0';
                    echo '<label><input type="checkbox" name="'. $option_key .'[exclude_day_'. $key .']" value="1" '. checked( '1', $checked, false ) .' /> ' . $name . '</label>';
                }
                ?>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Schedule Time:</label>
            <div class="clm-form-control">
                <input type="time" name="<?php echo $option_key; ?>[time_start]" value="<?php echo esc_attr( isset( $opts['time_start'] ) ? $opts['time_start'] : '' ); ?>" />
                <span style="margin: 0 10px;">até</span>
                <input type="time" name="<?php echo $option_key; ?>[time_end]" value="<?php echo esc_attr( isset( $opts['time_end'] ) ? $opts['time_end'] : '' ); ?>" />
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Scheduler Distance Time:</label>
            <div class="clm-form-control">
                <select name="<?php echo $option_key; ?>[distance_time]">
                    <option value="disable" <?php selected( 'disable', isset( $opts['distance_time'] ) ? $opts['distance_time'] : '' ); ?>>Disable</option>
                    <option value="5min" <?php selected( '5min', isset( $opts['distance_time'] ) ? $opts['distance_time'] : '' ); ?>>5 min</option>
                    <option value="10min" <?php selected( '10min', isset( $opts['distance_time'] ) ? $opts['distance_time'] : '' ); ?>>10 min</option>
                    <option value="30min" <?php selected( '30min', isset( $opts['distance_time'] ) ? $opts['distance_time'] : '' ); ?>>30 min</option>
                    <option value="1hour" <?php selected( '1hour', isset( $opts['distance_time'] ) ? $opts['distance_time'] : '' ); ?>>1 hour</option>
                </select>
                <br><span class="description">Garante intervalo entre disparos para não sobrecarregar o Host.</span>
            </div>
        </div>

        <!-- Seletor Dinâmico de Tags -->
        <div class="clm-form-group">
            <label class="clm-label-main">Select Tags for hashtags:</label>
            <div class="clm-form-control">
                <div class="clm-multi-select-box">
                    <strong>Posts (Tags)</strong>
                    <?php 
                    if ( !empty($post_tags) && !is_wp_error($post_tags) ) {
                        foreach ( $post_tags as $term ) {
                            $checked = in_array( $term->slug, $saved_tags ) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="'. $option_key .'[tags][]" value="'. esc_attr($term->slug) .'" '. $checked .'> '. esc_html($term->name) .'</label><br>';
                        }
                    } else { echo '<span class="description">Nenhuma Tag de Post encontrada.</span><br>'; }
                    ?>
                    
                    <strong>Produtos (Tags)</strong>
                    <?php 
                    if ( !empty($product_tags) && !is_wp_error($product_tags) ) {
                        foreach ( $product_tags as $term ) {
                            $checked = in_array( $term->slug, $saved_tags ) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="'. $option_key .'[tags][]" value="'. esc_attr($term->slug) .'" '. $checked .'> '. esc_html($term->name) .'</label><br>';
                        }
                    } else { echo '<span class="description">Nenhuma Tag de Produto encontrada.</span><br>'; }
                    ?>
                </div>
            </div>
        </div>

        <!-- Seletor Dinâmico de Categorias -->
        <div class="clm-form-group">
            <label class="clm-label-main">Select Categories for hashtags:</label>
            <div class="clm-form-control">
                <div class="clm-multi-select-box">
                    <strong>Posts (Categorias)</strong>
                    <?php 
                    if ( !empty($post_cats) && !is_wp_error($post_cats) ) {
                        foreach ( $post_cats as $term ) {
                            $checked = in_array( $term->slug, $saved_cats ) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="'. $option_key .'[cats][]" value="'. esc_attr($term->slug) .'" '. $checked .'> '. esc_html($term->name) .'</label><br>';
                        }
                    } else { echo '<span class="description">Nenhuma Categoria de Post encontrada.</span><br>'; }
                    ?>
                    
                    <strong>Produtos (Categorias)</strong>
                    <?php 
                    if ( !empty($product_cats) && !is_wp_error($product_cats) ) {
                        foreach ( $product_cats as $term ) {
                            $checked = in_array( $term->slug, $saved_cats ) ? 'checked="checked"' : '';
                            echo '<label><input type="checkbox" name="'. $option_key .'[cats][]" value="'. esc_attr($term->slug) .'" '. $checked .'> '. esc_html($term->name) .'</label><br>';
                        }
                    } else { echo '<span class="description">Nenhuma Categoria de Produto encontrada.</span><br>'; }
                    ?>
                </div>
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">Select Taxonomies:</label>
            <div class="clm-form-control">
                <label><input type="radio" name="<?php echo $option_key; ?>[tax_mode]" value="include" <?php checked( 'include', isset( $opts['tax_mode'] ) ? $opts['tax_mode'] : '' ); ?> /> Include ( Post only with )</label><br>
                <label><input type="radio" name="<?php echo $option_key; ?>[tax_mode]" value="exclude" <?php checked( 'exclude', isset( $opts['tax_mode'] ) ? $opts['tax_mode'] : '' ); ?> /> Exclude ( Do not post )</label><br>
                <input type="text" name="<?php echo $option_key; ?>[tax_keywords]" value="<?php echo esc_attr( isset( $opts['tax_keywords'] ) ? $opts['tax_keywords'] : '' ); ?>" placeholder="Ex: roblox, minecraft (pressione enter para aplicar)" style="width: 100%; margin-top:8px; max-width: 400px;" />
            </div>
        </div>

        <div class="clm-form-group">
            <label class="clm-label-main">URL Shortener:</label>
            <div class="clm-form-control">
                <select name="<?php echo $option_key; ?>[shortener]" class="clm-shortener-select">
                    <option value="disable" <?php selected( 'disable', isset( $opts['shortener'] ) ? $opts['shortener'] : '' ); ?>>Disable</option>
                    <option value="wordpress" <?php selected( 'wordpress', isset( $opts['shortener'] ) ? $opts['shortener'] : '' ); ?>>WordPress</option>
                    <option value="tinyurl" <?php selected( 'tinyurl', isset( $opts['shortener'] ) ? $opts['shortener'] : '' ); ?>>tinyURL</option>
                    <option value="bitly" <?php selected( 'bitly', isset( $opts['shortener'] ) ? $opts['shortener'] : '' ); ?>>bit.ly</option>
                    <option value="shortest" <?php selected( 'shortest', isset( $opts['shortener'] ) ? $opts['shortener'] : '' ); ?>>shorte.st</option>
                </select>
                
                <div class="clm-shortener-api-box box-bitly">
                    <label><strong>Bit.ly API Token:</strong></label><br>
                    <input type="text" name="<?php echo $option_key; ?>[bitly_token]" value="<?php echo esc_attr( isset( $opts['bitly_token'] ) ? $opts['bitly_token'] : '' ); ?>" style="width: 100%; max-width: 400px;" />
                </div>
                
                <div class="clm-shortener-api-box box-shortest">
                    <label><strong>Shorte.st API Token:</strong></label><br>
                    <input type="text" name="<?php echo $option_key; ?>[shortest_token]" value="<?php echo esc_attr( isset( $opts['shortest_token'] ) ? $opts['shortest_token'] : '' ); ?>" style="width: 100%; max-width: 400px;" />
                </div>
            </div>
        </div>

        <!-- BLOCO DE CONFIGURAÇÕES DE API -->
        <div class="clm-api-block">
            <div class="clm-section-title" style="border:none; margin-bottom:15px;"><?php echo esc_html( $display_name ); ?> API Settings</div>

            <div class="clm-form-group" style="align-items: center; margin-bottom: 25px;">
                <label class="clm-label-main">Authentication Type :</label>
                <div class="clm-form-control clm-inline-checkboxes">
                    <?php $auth_type = isset($opts['auth_type']) ? $opts['auth_type'] : 'app_method'; ?>
                    <label><input type="radio" name="<?php echo $option_key; ?>[auth_type]" value="app_method" <?php checked('app_method', $auth_type); ?> /> Facebook APP Method</label>
                    <label><input type="radio" name="<?php echo $option_key; ?>[auth_type]" value="graph_api" <?php checked('graph_api', $auth_type); ?> /> Facebook Graph API</label>
                </div>
            </div>

            <div class="clm-auth-app-method" style="display: <?php echo ($auth_type === 'app_method') ? 'block' : 'none'; ?>;">
                
                <?php 
                $connected_accounts = isset($opts['connected_accounts']) && is_array($opts['connected_accounts']) ? $opts['connected_accounts'] : [
                    ['userid' => '967173505067186', 'name' => 'Celso Luis Martins']
                ];
                ?>
                <table class="clm-table-accounts">
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>Account Name</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($connected_accounts)) : foreach ($connected_accounts as $acc_idx => $acc) : ?>
                        <tr>
                            <td>
                                <input type="text" name="<?php echo $option_key; ?>[connected_accounts][<?php echo $acc_idx; ?>][userid]" value="<?php echo esc_attr($acc['userid']); ?>" style="width: 100%;" />
                            </td>
                            <td>
                                <input type="text" name="<?php echo $option_key; ?>[connected_accounts][<?php echo $acc_idx; ?>][name]" value="<?php echo esc_attr($acc['name']); ?>" style="width: 100%;" />
                            </td>
                            <td>
                                <button type="button" class="clm-btn-delete" onclick="this.closest('tr').remove();">Delete Account</button>
                            </td>
                        </tr>
                        <?php endforeach; else: ?>
                        <tr><td colspan="3" style="text-align: center; color: #64748b;">Nenhuma conta vinculada.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <button type="button" class="clm-btn-add-account">
                    <span class="dashicons dashicons-plus-alt2" style="font-size: 16px; margin-top: 2px;"></span> Add Facebook Account
                </button>

            </div>

            <div class="clm-auth-graph-api" style="display: <?php echo ($auth_type === 'graph_api') ? 'block' : 'none'; ?>; margin-top: 15px;">
                
                <div class="clm-alert-warning">
                    <strong>Note:</strong> As facebook made some changes recently, graph API have some limitation. Posting will not work without <strong>app review</strong>. For more information about Graph API changes, <a href="#" target="_blank">click here</a>.
                </div>

                <div class="clm-alert-info">
                    <span class="dashicons dashicons-info" style="font-size: 18px; margin-top: 2px;"></span>
                    <div><strong>Note:</strong> Before you can start publishing your content to Facebook you need to create a Facebook Application. You can get a step by step tutorial on how to create a Facebook Application on our <a href="#" target="_blank">Documentation</a>.</div>
                </div>

                <div style="font-weight: 600; margin: 15px 0 5px 0; color: #334155;">Allowing permissions :</div>
                <p class="description" style="margin-bottom: 15px;">Posting content to your chosen Facebook Page or Group requires you to grant extended permissions. If you want to use this feature you should grant the extended permissions now.</p>

                <div class="clm-alert-info">
                    <span class="dashicons dashicons-info" style="font-size: 18px; margin-top: 2px;"></span>
                    <div><strong>Note:</strong> Please note the Facebook App, Facebook profile or page and the user who authorizes the app MUST belong to the <strong>same Facebook account</strong>. So please make sure you are logged in to Facebook as the same user who created the app.</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                    <div>
                        <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px;">App ID / API Key</label>
                        <input type="text" name="<?php echo $option_key; ?>[app_id]" value="<?php echo esc_attr( isset( $opts['app_id'] ) ? $opts['app_id'] : '' ); ?>" placeholder="Enter Facebook App ID / API Key." style="width: 100%;" />
                    </div>
                    <div>
                        <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px;">App Secret</label>
                        <input type="text" name="<?php echo $option_key; ?>[app_secret]" value="<?php echo esc_attr( isset( $opts['app_secret'] ) ? $opts['app_secret'] : '' ); ?>" placeholder="Enter Facebook App Secret." style="width: 100%;" />
                    </div>
                </div>

                <div style="margin-top: 15px;">
                    <label style="font-weight: 600; font-size: 13px; display: block; margin-bottom: 5px;">Valid OAuth redirect URIs</label>
                    <input type="text" name="<?php echo $option_key; ?>[oauth_redirect_uri]" value="<?php echo esc_attr( isset( $opts['oauth_redirect_uri'] ) ? $opts['oauth_redirect_uri'] : admin_url('admin.php?page=clm-autoposting') ); ?>" style="width: 100%; max-width: 500px;" />
                </div>

                <div style="margin-top: 15px;">
                    <button type="button" class="button" style="margin-top: 5px;"><span class="dashicons dashicons-plus" style="margin-top:3px;"></span> Add more</button>
                </div>

            </div>

        </div>

        <!-- BLOCO AVANÇADO DE COMPARTILHAMENTO -->
        <div class="clm-advanced-image3-block">
            <div class="clm-section-title" style="border:none; margin-bottom:15px;">Configurações do Compartilhamento (Autopost Location & Mensagens)</div>

            <div class="clm-form-group">
                <label class="clm-label-main">Do not allow individual posts :</label>
                <div class="clm-form-control">
                    <label class="clm-switch">
                        <input type="checkbox" name="<?php echo $option_key; ?>[no_individual_posts]" value="1" <?php checked( '1', isset( $opts['no_individual_posts'] ) ? $opts['no_individual_posts'] : '0' ); ?> />
                        <span class="clm-slider"></span>
                    </label>
                    <span class="description" style="vertical-align: middle;">If you check this box, then it will hide meta settings from individual posts.</span>
                </div>
            </div>

            <div class="clm-form-group">
                <label class="clm-label-main">Share posting type :</label>
                <div class="clm-form-control">
                    <select name="<?php echo $option_key; ?>[share_posting_type]" style="max-width: 250px;">
                        <option value="link" <?php selected( 'link', isset( $opts['share_posting_type'] ) ? $opts['share_posting_type'] : 'link' ); ?>>Link posting</option>
                        <option value="image" <?php selected( 'image', isset( $opts['share_posting_type'] ) ? $opts['share_posting_type'] : '' ); ?>>Image posting</option>
                    </select>
                    <span class="description" style="margin-left: 10px;">Select share posting method as link posting or image posting.</span>
                </div>
            </div>

            <div class="clm-form-group" style="align-items: flex-start;">
                <label class="clm-label-main">Map Autopost Location :</label>
                <div class="clm-form-control" style="display: flex; flex-direction: column; gap: 15px;">
                    
                    <?php 
                    $locais = [
                        'posts'    => 'Autopost Posts para ' . esc_html($display_name),
                        'pages'    => 'Autopost Páginas para ' . esc_html($display_name),
                        'media'    => 'Autopost Mídia para ' . esc_html($display_name),
                        'floating' => 'Autopost Elementos flutuantes para ' . esc_html($display_name),
                        'products' => 'Autopost Produtos para ' . esc_html($display_name),
                        'library'  => 'Autopost Minha biblioteca para ' . esc_html($display_name)
                    ];

                    foreach ($locais as $l_key => $l_label) :
                        $act_val = isset($opts['map_' . $l_key . '_action']) ? $opts['map_' . $l_key . '_action'] : 'As a Wall Post';
                        $acc_val = isset($opts['map_' . $l_key . '_accounts']) ? $opts['map_' . $l_key . '_accounts'] : 'Finds Deals WorldWide, Monte Sinai Online';
                    ?>
                    <div style="background: #fff; border: 1px solid #e2e8f0; padding: 12px; border-radius: 4px;">
                        <div style="font-weight: 600; margin-bottom: 6px; font-size: 13px; color: #334155;"><?php echo $l_label; ?></div>
                        <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                            <select name="<?php echo $option_key; ?>[map_<?php echo $l_key; ?>_action]" style="flex: 1; min-width: 200px;">
                                <option value="As a Wall Post" <?php selected('As a Wall Post', $act_val); ?>>As a Wall Post</option>
                                <option value="As a Story" <?php selected('As a Story', $act_val); ?>>As a Story</option>
                            </select>
                            <div style="flex: 2; min-width: 250px;">
                                <span style="font-size: 11px; color: #64748b; display: block; margin-bottom: 2px;">Select account</span>
                                <input type="text" name="<?php echo $option_key; ?>[map_<?php echo $l_key; ?>_accounts]" value="<?php echo esc_attr($acc_val); ?>" placeholder="Ex: Finds Deals WorldWide, Monte Sinai Online" style="width: 100%;" />
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                </div>
            </div>

            <div class="clm-form-group" style="margin-top: 25px;">
                <label class="clm-label-main">Posting Format Options :</label>
                <div class="clm-form-control clm-inline-checkboxes">
                    <?php $fmt_opt = isset($opts['posting_format_option']) ? $opts['posting_format_option'] : 'global'; ?>
                    <label><input type="radio" name="<?php echo $option_key; ?>[posting_format_option]" value="global" <?php checked('global', $fmt_opt); ?> /> Global</label>
                    <label><input type="radio" name="<?php echo $option_key; ?>[posting_format_option]" value="individual" <?php checked('individual', $fmt_opt); ?> /> Individual Post Type Message</label>
                </div>
            </div>

            <div class="clm-form-group">
                <label class="clm-label-main">Custom Message :</label>
                <div class="clm-form-control">
                    <textarea name="<?php echo $option_key; ?>[message_format]" placeholder="{title} - {link} - {content-300}" style="width: 100%; height: 110px;"><?php echo esc_textarea( isset( $opts['message_format'] ) ? $opts['message_format'] : '{title} - {link} - {content-300}' ); ?></textarea>
                    
                    <span class="description" style="display: block; margin-top: 5px;">Here you can enter default message which will be used for the wall post. Leave it empty to use the post level message. You can use following template tags within the message template:</span>
                    
                    <div class="clm-tags-grid">
                        <div>
                            <div class="clm-tag-item"><span class="clm-tag-badge">{first_name}</span> <span>displays the first name.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{title}</span> <span>displays the default post title.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{full_author}</span> <span>displays the full author name.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{post_type}</span> <span>displays the post type.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{excerpt}</span> <span>displays the post excerpt.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{hashcats}</span> <span>displays the post categories as hashtags.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{content-digits}</span> <span>displays the post content with define number of digits in template tag. E.g. If you add template like `{content-100}` then it will display first 100 characters from post content.</span></div>
                        </div>
                        <div>
                            <div class="clm-tag-item"><span class="clm-tag-badge">{last_name}</span> <span>displays the last name.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{link}</span> <span>displays the default post link.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{nickname_author}</span> <span>displays the nickname of author.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{sitename}</span> <span>displays the name of your site.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{hashtags}</span> <span>displays the post tags as hashtags.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{content}</span> <span>displays the post content.</span></div>
                            <div class="clm-tag-item" style="margin-top:6px;"><span class="clm-tag-badge">{CF-CustomFieldName}</span> <span>Inserts the contents of the custom field with the specified name. E.g. If your price is stored in the custom field "PRDPRICE" you will need to use (CF-PRDPRICE) tag.</span></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <hr style="margin: 30px 0; border-top: 1px solid #eee; border-bottom: none;" />
        <?php submit_button( 'Save Changes', 'clm-btn-primary', 'submit', true ); ?>
        <?php
    }
}

// Inicializa a classe administrativa
CLM_AutoPosting_Admin::get_instance();