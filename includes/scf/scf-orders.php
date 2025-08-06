<?php

add_action('acf/init', 'register_order_fields');

function register_order_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_order_fields',
            'title' => 'Campos do Pedido',
            'fields' => array(
                // Tab: Status e Informações Básicas
                array(
                    'key' => 'field_tab_status',
                    'label' => 'Status e Informações Básicas',
                    'name' => '',
                    'type' => 'tab',
                    'instructions' => '',
                    'required' => 0,
                    'placement' => 'top',
                ),
                array(
                    'key' => 'field_order_status',
                    'label' => 'Status do Pedido',
                    'name' => 'order_status',
                    'type' => 'select',
                    'instructions' => 'Status atual do pedido',
                    'required' => 1,
                    'choices' => array(
                        'created' => 'Criado',
                        'awaiting_payment' => 'Aguardando Pagamento',
                        'paid' => 'Pago',
                        'thanked' => 'Agradecido',
                        'created_assets_text' => 'Assets de Texto Criados',
                        'created_assets_illustration' => 'Assets de Ilustração Criados',
                        'created_assets_merge' => 'Assets Finais Criados',
                        'ready_for_delivery' => 'Pronto para Entrega',
                        'delivered' => 'Entregue',
                        'completed' => 'Concluído (Entregue e PDF Gerado)',
                        'error' => 'Erro'
                    ),
                    'default_value' => 'created',
                    'return_format' => 'value',
                ),
                // relacionamento 1:1 com um post do custom post type book-templates
                array(
                    'key' => 'field_book_template',
                    'label' => 'Modelo de Livro',
                    'name' => 'book_template',
                    'type' => 'post_object',
                    'instructions' => 'Modelo de livro selecionado para o pedido',
                    'post_type' => 'book_templates',
                    'post_status' => array('publish'),
                    'return_format' => 'object',
                    'required' => 1,
                ),
                
                // Tab: Dados da Criança
                array(
                    'key' => 'field_tab_child',
                    'label' => 'Dados da Criança',
                    'name' => '',
                    'type' => 'tab',
                    'instructions' => '',
                    'required' => 0,
                    'placement' => 'top',
                ),
                array(
                    'key' => 'field_child_name',
                    'label' => 'Nome da Criança',
                    'name' => 'child_name',
                    'type' => 'text',
                    'instructions' => 'Nome da criança que será a protagonista do livro',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_child_age',
                    'label' => 'Idade da Criança',
                    'name' => 'child_age',
                    'type' => 'number',
                    'instructions' => 'Idade da criança',
                    'required' => 1,
                    'min' => 0,
                    'max' => 99,
                ),
                array(
                    'key' => 'field_child_gender',
                    'label' => 'Gênero da Criança',
                    'name' => 'child_gender',
                    'type' => 'select',
                    'instructions' => 'Gênero da criança, usado para personalização de texto e seleção de ilustrações',
                    'required' => 1,
                    'choices' => array(
                        'menino' => 'Menino',
                        'menina' => 'Menina'
                    ),
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_child_skin_tone',
                    'label' => 'Tom de Pele da Criança',
                    'name' => 'child_skin_tone',
                    'type' => 'select',
                    'instructions' => 'Tom de pele da criança, usado para seleção de ilustrações base',
                    'required' => 1,
                    'choices' => array(
                        'clara' => 'Clara',
                        'media' => 'Média',
                        'escura' => 'Escura'
                    ),
                    'return_format' => 'value',
                ),
                array(
                    'key' => 'field_child_face_photo',
                    'label' => 'Foto do Rosto da Criança',
                    'name' => 'child_face_photo',
                    'type' => 'image',
                    'instructions' => 'Foto do rosto da criança, para integração com a API de Face Swap',
                    'required' => 1,
                    'return_format' => 'array',
                    'preview_size' => 'medium',
                    'library' => 'all',
                    'mime_types' => 'jpg, jpeg, png',
                ),
                
                // Tab: Dados do Comprador
                array(
                    'key' => 'field_tab_buyer',
                    'label' => 'Dados do Comprador',
                    'name' => '',
                    'type' => 'tab',
                    'instructions' => '',
                    'required' => 0,
                    'placement' => 'top',
                ),
                array(
                    'key' => 'field_buyer_email',
                    'label' => 'Email do Comprador',
                    'name' => 'buyer_email',
                    'type' => 'email',
                    'instructions' => 'E-mail do responsável para envio de comunicações e do PDF final',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_buyer_name',
                    'label' => 'Nome do Responsável',
                    'name' => 'buyer_name',
                    'type' => 'text',
                    'instructions' => 'Nome do responsável pela criança',
                    'required' => 1,
                ),
                array(
                    'key' => 'field_buyer_phone',
                    'label' => 'Telefone do Comprador',
                    'name' => 'buyer_phone',
                    'type' => 'text',
                    'instructions' => 'Telefone de contato do responsável',
                    'required' => 1,
                ),
                
                // Tab: Dados do Pagamento
                array(
                    'key' => 'field_tab_payment',
                    'label' => 'Dados do Pagamento',
                    'name' => '',
                    'type' => 'tab',
                    'instructions' => '',
                    'required' => 0,
                    'placement' => 'top',
                ),
                array(
                    'key' => 'field_payment_transaction_id',
                    'label' => 'ID da Transação',
                    'name' => 'payment_transaction_id',
                    'type' => 'text',
                    'instructions' => 'ID da transação de pagamento, recebido do webhook do Asaas',
                    'required' => 0,
                ),
                array(
                    'key' => 'field_payment_date',
                    'label' => 'Data do Pagamento',
                    'name' => 'payment_date',
                    'type' => 'date_time_picker',
                    'instructions' => 'Data e hora da confirmação do pagamento',
                    'required' => 0,
                    'display_format' => 'd/m/Y H:i',
                    'return_format' => 'Y-m-d H:i:s',
                ),
                // Valor do pagamento
                array(
                    'key' => 'field_payment_amount',
                    'label' => 'Valor do Pagamento',
                    'name' => 'payment_amount',
                    'type' => 'number',
                ),
                
                // Tab: Ativos Gerados
                array(
                    'key' => 'field_tab_assets',
                    'label' => 'Ativos Gerados',
                    'name' => '',
                    'type' => 'tab',
                    'instructions' => '',
                    'required' => 0,
                    'placement' => 'top',
                ),
                array(
                    'key' => 'field_generated_pdf_link',
                    'label' => 'Link do PDF Gerado',
                    'name' => 'generated_pdf_link',
                    'type' => 'url',
                    'instructions' => 'Link para o PDF final personalizado gerado',
                    'required' => 0,
                ),
                array(
                    'key' => 'field_generated_book_pages',
                    'label' => 'Páginas do Livro Geradas',
                    'name' => 'generated_book_pages',
                    'type' => 'repeater',
                    'instructions' => 'Detalhes de cada página do livro personalizado gerado',
                    'required' => 0,
                    'min' => 0,
                    'max' => 0,
                    'layout' => 'block',
                    'button_label' => 'Adicionar Página',
                    'sub_fields' => array(
                        array(
                            'key' => 'field_generated_text_content',
                            'label' => 'Conteúdo do Texto',
                            'name' => 'generated_text_content',
                            'type' => 'wysiwyg',
                            'instructions' => 'O conteúdo de texto final para esta página, após a personalização da IA',
                            'required' => 0,
                            'tabs' => 'all',
                            'toolbar' => 'full',
                            'media_upload' => 0,
                        ),
                        array(
                            'key' => 'field_generated_illustration',
                            'label' => 'Ilustração da Página',
                            'name' => 'generated_illustration',
                            'type' => 'image',
                            'instructions' => 'Ilustração final desta página, após o face swap',
                            'required' => 0,
                            'return_format' => 'array',
                            'preview_size' => 'medium',
                            'library' => 'all',
                            'mime_types' => 'jpg, jpeg, png',
                        ),
                    ),
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'orders',
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