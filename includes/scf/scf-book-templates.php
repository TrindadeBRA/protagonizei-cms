<?php

add_action('acf/init', 'register_book_template_fields');

function register_book_template_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_book_template_fields',
            'title' => 'Campos do Modelo de Livro',
            'fields' => array(
                array(
                    'key' => 'field_template_book_pages',
                    'label' => 'Páginas do Livro',
                    'name' => 'template_book_pages',
                    'type' => 'repeater',
                    'instructions' => 'Adicione as páginas do livro com seus textos e ilustrações base',
                    'required' => 1,
                    'min' => 1,
                    'max' => 0,
                    'layout' => 'block',
                    'button_label' => 'Adicionar Página',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_base_text_content_boy',
                            'label' => 'Texto Base para Menino',
                            'name' => 'base_text_content_boy',
                            'type' => 'wysiwyg',
                            'instructions' => 'Conteúdo de texto original para menino com marcadores para personalização (ex: {nome}, {pronome}, etc)',
                            'required' => 1,
                            'tabs' => 'all',
                            'toolbar' => 'full',
                            'media_upload' => 0,
                        ),
                        array(
                            'key' => 'field_base_text_content_girl',
                            'label' => 'Texto Base para Menina',
                            'name' => 'base_text_content_girl',
                            'type' => 'wysiwyg',
                            'instructions' => 'Conteúdo de texto original para menina com marcadores para personalização (ex: {nome}, {pronome}, etc)',
                            'required' => 1,
                            'tabs' => 'all',
                            'toolbar' => 'full',
                            'media_upload' => 0,
                        ),
                        array(
                            'key' => 'field_text_position',
                            'label' => 'Posição do Texto',
                            'name' => 'text_position',
                            'type' => 'select',
                            'instructions' => 'Selecione a posição do texto na página',
                            'required' => 1,
                            'choices' => array(
                                'top_right' => 'Direito Superior',
                                'center_right' => 'Direito Centralizado', 
                                'bottom_right' => 'Direito Inferior',
                                'top_left' => 'Esquerda Superior',
                                'center_left' => 'Esquerda Centralizado',
                                'bottom_left' => 'Esquerda Inferior',
                            ),
                            'default_value' => 'center_right',
                            'return_format' => 'value',
                        ),
                        array(
                            'key' => 'field_base_illustrations',
                            'label' => 'Ilustrações Base',
                            'name' => 'base_illustrations',
                            'type' => 'repeater',
                            'instructions' => 'Ilustrações base para cada combinação de gênero e tom de pele',
                            'required' => 1,
                            'min' => 4,
                            'max' => 4,
                            'layout' => 'table',
                            'button_label' => '',
                            'collapsed' => 'field_gender',
                            'sub_fields' => array(
                                array(
                                    'key' => 'field_gender',
                                    'label' => 'Gênero',
                                    'name' => 'gender',
                                    'type' => 'select',
                                    'instructions' => 'Gênero da ilustração base',
                                    'required' => 1,
                                    'choices' => array(
                                        'menino' => 'Menino',
                                        'menina' => 'Menina'
                                    ),
                                    'default_value' => 'menino',
                                    'return_format' => 'value',
                                ),
                                array(
                                    'key' => 'field_skin_tone',
                                    'label' => 'Tom de Pele',
                                    'name' => 'skin_tone',
                                    'type' => 'select',
                                    'instructions' => 'Tom de pele da ilustração base',
                                    'required' => 1,
                                    'choices' => array(
                                        'clara' => 'Clara',
                                        // 'media' => 'Média', // Temporariamente desabilitado
                                        'escura' => 'Escura'
                                    ),
                                    'default_value' => 'clara',
                                    'return_format' => 'value',
                                ),
                                array(
                                    'key' => 'field_illustration_asset',
                                    'label' => 'Ilustração Base',
                                    'name' => 'illustration_asset',
                                    'type' => 'image',
                                    'instructions' => 'Ilustração base para esta combinação de gênero e tom de pele',
                                    'required' => 1,
                                    'return_format' => 'array',
                                    'preview_size' => 'full',
                                    'library' => 'all',
                                    'mime_types' => 'jpg, jpeg, png',
                                ),
                            ),
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'book_templates',
                    ),
                ),
            ),
            'menu_order' => 0,
            'position' => 'normal',
            'style' => 'default',
            'label_placement' => 'top',
            'instruction_placement' => 'label',
            'hide_on_screen' => '',
        ));
    }
}

/**
 * Pré-preenche o repeater de ilustrações base com todas as combinações possíveis
 */
function prefill_base_illustrations($field) {
    if ($field['name'] === 'base_illustrations') {
        $field['default_value'] = array(
            array(
                'gender' => 'menino',
                'skin_tone' => 'clara'
            ),
            // array(
            //     'gender' => 'menino',
            //     'skin_tone' => 'media'
            // ),
            array(
                'gender' => 'menino',
                'skin_tone' => 'escura'
            ),
            array(
                'gender' => 'menina',
                'skin_tone' => 'clara'
            ),
            // array(
            //     'gender' => 'menina',
            //     'skin_tone' => 'media'
            // ),
            array(
                'gender' => 'menina',
                'skin_tone' => 'escura'
            )
        );
    }
    return $field;
}
add_filter('acf/load_field/name=base_illustrations', 'prefill_base_illustrations'); 