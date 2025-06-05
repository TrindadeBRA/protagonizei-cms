<?php



add_action('acf/init', 'register_product_fields');

function register_product_fields() {
    if (function_exists('acf_add_local_field_group')) {
        acf_add_local_field_group(array(
            'key' => 'group_product_parent',
            'title' => 'Campos personalizados para produtos',
            'fields' => array(
                array(
                    'key' => 'field_cas_number',
                    'label' => 'CAS NUMBER',
                    'name' => 'cas_number',
                    'type' => 'text',
                    'instructions' => 'Insira o número CAS do produto.',
                    'required' => false,
                ),
            ),
            'location' => array(
                array(
                    array(
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'products',
                    ),
                    
                ),

            ),
        ));
    }
}
?>
