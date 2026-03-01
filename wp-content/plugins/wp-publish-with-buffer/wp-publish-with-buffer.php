<?php
/**
 * Plugin Name: WP Buffer GraphQL Publisher
 * Plugin URI:  https://tuweb.com
 * Description: Publica contenido en Buffer utilizando su nueva API GraphQL.
 * Version:     1.0.0
 * Author:      Tu Nombre
 * License:     GPL-2.0+
 */

// Evitar el acceso directo al archivo por seguridad
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ==========================================
// 1. CÓDIGO DEL META BOX Y DISPARADOR
// ==========================================

add_action( 'add_meta_boxes', 'buffer_plugin_add_meta_box' );
function buffer_plugin_add_meta_box() {
    add_meta_box(
        'buffer_plugin_meta_box',      
        'Publicar en Buffer',          
        'buffer_plugin_meta_box_html', 
        'post',                        
        'side',                        
        'high'                         
    );
}

function buffer_plugin_meta_box_html( $post ) {
    wp_nonce_field( 'buffer_plugin_save_meta', 'buffer_plugin_meta_nonce' );

    $global_default_mode = get_option( 'buffer_default_mode', 'shareNow' ); 

    $is_active = get_post_meta( $post->ID, '_buffer_is_active', true );
    $mode      = get_post_meta( $post->ID, '_buffer_mode', true );
    $due_at    = get_post_meta( $post->ID, '_buffer_due_at', true );

    if ( $mode === '' ) {
        $mode = $global_default_mode;
        $is_active = 'yes'; 
    }
    ?>
    <p>
        <label>
            <input type="checkbox" name="buffer_is_active" value="yes" <?php checked( $is_active, 'yes' ); ?> />
            <strong>Enviar a Buffer al publicar</strong>
        </label>
    </p>
    
    <p>
        <label for="buffer_mode">Modo de envío:</label>
        <select name="buffer_mode" id="buffer_mode" style="width: 100%;">
            <option value="shareNow" <?php selected( $mode, 'shareNow' ); ?>>Inmediato (Share Now)</option>
            <option value="addToQueue" <?php selected( $mode, 'addToQueue' ); ?>>A la Cola (Add to Queue)</option>
            <option value="customScheduled" <?php selected( $mode, 'customScheduled' ); ?>>Programado (Custom)</option>
        </select>
    </p>

    <p id="buffer_date_wrapper" style="<?php echo ( $mode === 'customScheduled' ) ? 'display:block;' : 'display:none;'; ?>">
        <label for="buffer_due_at">Fecha y Hora (UTC):</label>
        <input type="datetime-local" name="buffer_due_at" id="buffer_due_at" value="<?php echo esc_attr( $due_at ); ?>" style="width: 100%;" />
        <small>Formato requerido para Buffer.</small>
    </p>

    <script>
        document.getElementById('buffer_mode').addEventListener('change', function() {
            var dateWrapper = document.getElementById('buffer_date_wrapper');
            if (this.value === 'customScheduled') {
                dateWrapper.style.display = 'block';
            } else {
                dateWrapper.style.display = 'none';
            }
        });
    </script>
    <?php
}

add_action( 'save_post', 'buffer_plugin_save_meta_box_data' );
function buffer_plugin_save_meta_box_data( $post_id ) {
    if ( ! isset( $_POST['buffer_plugin_meta_nonce'] ) || ! wp_verify_nonce( $_POST['buffer_plugin_meta_nonce'], 'buffer_plugin_save_meta' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $is_active = isset( $_POST['buffer_is_active'] ) ? 'yes' : 'no';
    update_post_meta( $post_id, '_buffer_is_active', $is_active );

    if ( isset( $_POST['buffer_mode'] ) ) {
        update_post_meta( $post_id, '_buffer_mode', sanitize_text_field( $_POST['buffer_mode'] ) );
    }

    if ( isset( $_POST['buffer_due_at'] ) && ! empty( $_POST['buffer_due_at'] ) ) {
        $date = new DateTime( sanitize_text_field( $_POST['buffer_due_at'] ) );
        update_post_meta( $post_id, '_buffer_due_at', $date->format('Y-m-d\TH:i:s\Z') );
    }

    // Lógica de disparo a Buffer
    if ( 'publish' === get_post_status( $post_id ) ) {
        $already_sent = get_post_meta( $post_id, '_buffer_sent_flag', true );
        
        if ( 'yes' === $is_active && 'yes' !== $already_sent ) {
            update_post_meta( $post_id, '_buffer_sent_flag', 'yes' );
            buffer_plugin_send_to_buffer( $post_id );
        }
    }
}

// ==========================================
// 2. CÓDIGO DE LA LLAMADA A LA API
// ==========================================

function buffer_plugin_send_to_buffer( $post_id ) {
    $post = get_post( $post_id );
    
    error_log("Buffer API: Iniciando envío para el post ID " . $post_id);

    $api_token   = get_option( 'buffer_api_token' );
    $channels    = get_option( 'buffer_channels', [] ); 
    $template    = get_option( 'buffer_template', '{title} - {url}' );
    $allowed_cat = get_option( 'buffer_allowed_category', '' ); 

    if ( empty( $api_token ) || empty( $channels ) ) {
        error_log("Buffer API Error: Faltan credenciales o canales.");
        return;
    }

    if ( ! empty( $allowed_cat ) ) {
        $post_categories = wp_get_post_categories( $post_id );
        if ( ! in_array( $allowed_cat, $post_categories ) ) {
            error_log("Buffer API: El post no coincide con la categoría permitida.");
            return; 
        }
    }

    $excerpt = has_excerpt( $post_id ) ? get_the_excerpt( $post_id ) : wp_trim_words( $post->post_content, 20 );
    $message = str_replace(
        ['{title}', '{url}', '{excerpt}'],
        [$post->post_title, get_permalink( $post_id ), $excerpt],
        $template
    );

    // IMAGEN FIJA PARA PRUEBAS LOCALES
    $image_url = 'https://images.unsplash.com/photo-1742850541164-8eb59ecb3282?q=80&w=3388&auto=format&fit=crop&ixlib=rb-4.0.3';

    $mode   = get_post_meta( $post_id, '_buffer_mode', true );
    $due_at = get_post_meta( $post_id, '_buffer_due_at', true );

    $query = '
    mutation CreatePost($input: CreatePostInput!) {
      createPost(input: $input) {
        ... on PostActionSuccess {
          post { id text }
        }
        ... on MutationError {
          message
        }
      }
    }';

    foreach ( $channels as $channel_id ) {
        $input_vars = [
            'text'           => $message,
            'channelId'      => $channel_id,
            'schedulingType' => 'automatic',
            'mode'           => $mode
        ];

        if ( $image_url ) {
            $input_vars['assets'] = [ 'images' => [ ['url' => $image_url] ] ];
        }

        if ( 'customScheduled' === $mode && ! empty( $due_at ) ) {
            $input_vars['dueAt'] = $due_at;
        }

        $payload = [
            'query'     => $query,
            'variables' => [ 'input' => $input_vars ]
        ];

        $response = wp_remote_post( 'https://api.buffer.com', [
            'headers'     => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_token,
            ],
            'body'        => wp_json_encode( $payload ),
            'data_format' => 'body',
            'timeout'     => 15,
            'blocking'    => true 
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( "Buffer API HTTP Error: " . $response->get_error_message() );
        } else {
            $response_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body( $response );
            error_log( "Buffer API Status Code: $response_code" );
            error_log( "Buffer API Response: $response_body" );
        }
    }
}

// ==========================================
// 3. PÁGINA DE AJUSTES (Panel de Control)
// ==========================================

add_action( 'admin_menu', 'buffer_plugin_add_admin_menu' );
function buffer_plugin_add_admin_menu() {
    add_menu_page(
        'Ajustes Buffer GraphQL',         
        'Buffer GraphQL',                 
        'manage_options',                 
        'buffer-graphql-settings',        
        'buffer_plugin_settings_page_html', 
        'dashicons-share',                
        80                                
    );
}

add_action( 'admin_init', 'buffer_plugin_settings_init' );
function buffer_plugin_settings_init() {
    register_setting( 'buffer_plugin_settings_group', 'buffer_api_token' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_channels', 'buffer_plugin_sanitize_channels' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_template' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_allowed_category', 'absint' );
    register_setting( 'buffer_plugin_settings_group', 'buffer_default_mode' );

    add_settings_section(
        'buffer_plugin_main_section',
        'Configuración de la API y Publicación',
        '__return_empty_string', 
        'buffer-graphql-settings'
    );

    add_settings_field( 'buffer_api_token', 'API Token de Buffer', 'buffer_plugin_api_token_cb', 'buffer-graphql-settings', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_channels', 'IDs de Canales', 'buffer_plugin_channels_cb', 'buffer-graphql-settings', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_template', 'Plantilla del Mensaje', 'buffer_plugin_template_cb', 'buffer-graphql-settings', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_default_mode', 'Modo de Envío por Defecto', 'buffer_plugin_mode_cb', 'buffer-graphql-settings', 'buffer_plugin_main_section' );
    add_settings_field( 'buffer_allowed_category', 'Filtrar por Categoría', 'buffer_plugin_category_cb', 'buffer-graphql-settings', 'buffer_plugin_main_section' );
}

function buffer_plugin_sanitize_channels( $input ) {
    if ( is_array( $input ) ) return $input;
    $channels = explode( ',', $input );
    $channels = array_map( 'trim', $channels );
    $channels = array_map( 'sanitize_text_field', $channels );
    return array_filter( $channels ); 
}

function buffer_plugin_api_token_cb() {
    $val = get_option( 'buffer_api_token' );
    echo '<input type="password" name="buffer_api_token" value="' . esc_attr( $val ) . '" class="regular-text" />';
}

function buffer_plugin_channels_cb() {
    $val = get_option( 'buffer_channels', [] );
    $val_string = is_array( $val ) ? implode( ', ', $val ) : '';
    echo '<input type="text" name="buffer_channels" value="' . esc_attr( $val_string ) . '" class="regular-text" />';
    echo '<p class="description">Ejemplo: <code>id_1, id_2</code></p>';
}

function buffer_plugin_template_cb() {
    $val = get_option( 'buffer_template', '{title} - {url}' );
    echo '<textarea name="buffer_template" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
}

function buffer_plugin_mode_cb() {
    $val = get_option( 'buffer_default_mode', 'shareNow' );
    ?>
    <select name="buffer_default_mode">
        <option value="shareNow" <?php selected( $val, 'shareNow' ); ?>>Inmediato (Share Now)</option>
        <option value="addToQueue" <?php selected( $val, 'addToQueue' ); ?>>A la Cola (Add to Queue)</option>
    </select>
    <?php
}

function buffer_plugin_category_cb() {
    $val = get_option( 'buffer_allowed_category', '' );
    $args = [
        'show_option_all'    => 'Todas las categorías',
        'name'               => 'buffer_allowed_category',
        'selected'           => $val,
        'value_field'        => 'term_id',
    ];
    wp_dropdown_categories( $args );
}

function buffer_plugin_settings_page_html() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1>Ajustes de Buffer GraphQL</h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'buffer_plugin_settings_group' );
            do_settings_sections( 'buffer-graphql-settings' );
            submit_button( 'Guardar Configuración' );
            ?>
        </form>
    </div>
    <?php
}