<?php

add_action('acf/init', 'register_contact_fields');

function register_contact_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_contact_form',
            'title' => 'Campos do Formulário de Contato',
            'fields' => array(
                array(
                    'key' => 'field_phone',
                    'label' => 'Telefone',
                    'name' => 'phone',
                    'type' => 'text',
                    'instructions' => 'Digite o número de telefone',
                    'placeholder' => '(00) 00000-0000',
                ),
                array(
                    'key' => 'field_name',
                    'label' => 'Nome',
                    'name' => 'name',
                    'type' => 'text',
                    'instructions' => 'Digite o nome do contato',
                ),
                array(
                    'key' => 'field_linkedin',
                    'label' => 'LinkedIn',
                    'name' => 'linkedin',
                    'type' => 'text',
                    'instructions' => 'Digite o LinkedIn do contato',
                ),
                array(
                    'key' => 'field_email',
                    'label' => 'Email',
                    'name' => 'email',
                    'type' => 'email',
                    'instructions' => 'Digite o email do contato',
                ),
                array(
                    'key' => 'field_attachment',
                    'label' => 'Anexo',
                    'name' => 'attachment',
                    'type' => 'file',
                    'instructions' => 'Anexe algum arquivo',
                    'required' => 0,
                    'return_format' => 'array',
                    'preview_size' => 'medium',
                    'library' => 'all',
                    'mime_types' => 'pdf, xls, xlsx, csv, doc, docx, jpg, jpeg, png, gif, svg, webp',
                    'max_size' => 10, // 10MB
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'contact_form',
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

?>
