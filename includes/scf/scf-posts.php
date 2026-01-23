<?php

add_action('acf/init', 'register_post_fields');

function register_post_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_post_automation_fields',
            'title' => 'Campos de Automação do Post',
            'fields' => array(
                array(
                    'key' => 'field_automation_video_url',
                    'label' => 'Vídeo de Automação',
                    'name' => 'automation_video_url',
                    'type' => 'file',
                    'instructions' => 'Selecione o vídeo MP4 para automação do post',
                    'required' => 0,
                    'return_format' => 'array',
                    'library' => 'all',
                    'mime_types' => 'mp4',
                ),
                array(
                    'key' => 'field_automation_video_used',
                    'label' => 'Vídeo de Automação Utilizado',
                    'name' => 'automation_video_used',
                    'type' => 'true_false',
                    'instructions' => 'Indica se o vídeo de automação já foi utilizado',
                    'required' => 0,
                    'default_value' => 0,
                    'ui' => 1,
                    'ui_on_text' => 'Sim',
                    'ui_off_text' => 'Não',
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'post',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'side',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
        ));
    }
}

?>
