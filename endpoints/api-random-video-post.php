<?php

// Verifica se o arquivo está sendo acessado diretamente
if (!defined('ABSPATH')) {
    exit; // Sai se acessado diretamente
}

function trinitykitcms_get_random_video_post($request) {
    // Valida a API key
    $api_validation = trinitykitcms_validate_api_key($request);
    if (is_wp_error($api_validation)) {
        return $api_validation;
    }

    $reset_occurred = false;

    // Primeiro, busca posts com vídeos não utilizados
    $args_unused = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'rand',
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key' => 'automation_video_url',
                'compare' => 'EXISTS'
            ),
            array(
                'key' => 'automation_video_url',
                'value' => '',
                'compare' => '!='
            ),
            array(
                'relation' => 'OR',
                array(
                    'key' => 'automation_video_used',
                    'value' => '0',
                    'compare' => '='
                ),
                array(
                    'key' => 'automation_video_used',
                    'compare' => 'NOT EXISTS'
                )
            )
        )
    );

    $posts = get_posts($args_unused);

    // Se não encontrou posts não utilizados, verifica se todos foram utilizados
    if (empty($posts)) {
        // Busca total de posts com vídeo
        $args_all_with_video = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'automation_video_url',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'automation_video_url',
                    'value' => '',
                    'compare' => '!='
                )
            )
        );
        
        $all_posts_with_video = get_posts($args_all_with_video);
        
        // Se não existe nenhum post com vídeo, retorna erro
        if (empty($all_posts_with_video)) {
            return new WP_Error(
                'no_posts_with_video',
                'Nenhum post com vídeo encontrado no sistema',
                array('status' => 404)
            );
        }
        
        // Busca posts com vídeo utilizados
        $args_used = array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'automation_video_url',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => 'automation_video_url',
                    'value' => '',
                    'compare' => '!='
                ),
                array(
                    'key' => 'automation_video_used',
                    'value' => '1',
                    'compare' => '='
                )
            )
        );
        
        $used_posts = get_posts($args_used);
        
        // Se a quantidade de posts utilizados é igual ao total de posts com vídeo, reseta
        // OU se não encontrou posts não utilizados e existe pelo menos um post com vídeo, também reseta
        if (count($used_posts) === count($all_posts_with_video) || count($all_posts_with_video) > 0) {
            // Resetar todos os campos automation_video_used para false
            foreach ($all_posts_with_video as $post_id) {
                update_field('automation_video_used', 0, $post_id);
            }
            
            $reset_occurred = true;
            
            // Busca novamente posts não utilizados após o reset
            $posts = get_posts($args_unused);
        }
    }

    // Se ainda não tem posts após o reset, erro crítico
    if (empty($posts)) {
        return new WP_Error(
            'no_posts_after_reset',
            'Nenhum post encontrado mesmo após reset',
            array('status' => 500)
        );
    }

    // Pega o primeiro post (já está randomizado pela query)
    $post = $posts[0];
    $post_id = $post->ID;
    
    // Marca o post como utilizado ANTES de retornar (1 = true)
    update_field('automation_video_used', 1, $post_id);
    
    // Obtém a URL da imagem destacada
    $featured_image_url = get_the_post_thumbnail_url($post_id, 'full');
    
    // Obtém o vídeo de automação (retorna array)
    $video_data = get_field('automation_video_url', $post_id);
    $video_url = '';
    
    if ($video_data && is_array($video_data) && isset($video_data['url'])) {
        $video_url = $video_data['url'];
    }
    
    // Obtém as categorias do post
    $categories = get_the_category($post_id);
    $category_names = array();
    
    if (!empty($categories)) {
        foreach ($categories as $category) {
            $category_names[] = $category->name;
        }
    }
    
    // Prepara os dados do post em snake_case inglês
    $post_data = array(
        'id' => $post_id,
        'title' => $post->post_title,
        'body' => $post->post_content,
        'summary' => $post->post_excerpt ? $post->post_excerpt : wp_trim_words($post->post_content, 55, '...'),
        'video_url' => $video_url,
        'category' => !empty($category_names) ? implode(', ', $category_names) : '',
        'featured_image_url' => $featured_image_url ? $featured_image_url : '',
        'slug' => $post->post_name,
        'created_at' => $post->post_date
    );

    // Prepara resposta com status code diferente se houve reset
    $response = new WP_REST_Response(array(
        'success' => true,
        'reset_occurred' => $reset_occurred,
        'data' => $post_data
    ));
    
    // Define status code: 206 se houve reset, 200 se não houve
    $response->set_status($reset_occurred ? 206 : 200);
    
    return $response;
}

/**
 * Registra o endpoint da API para buscar post aleatório com vídeo
 */
function trinitykitcms_register_random_video_post_endpoint() {
    register_rest_route('trinitykitcms-api/v1', '/random-video-post', array(
        array(
            'methods' => 'GET',
            'callback' => 'trinitykitcms_get_random_video_post',
            'permission_callback' => '__return_true'
        )
    ));
}

add_action('rest_api_init', 'trinitykitcms_register_random_video_post_endpoint', 10);
