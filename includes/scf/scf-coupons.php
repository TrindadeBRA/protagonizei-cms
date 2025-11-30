<?php

add_action('acf/init', 'register_coupon_fields');

function register_coupon_fields() {
	if (function_exists('acf_add_local_field_group')) {
		acf_add_local_field_group(array(
			'key' => 'group_coupon_fields',
			'title' => 'Configurações do Cupom',
			'fields' => array(
				array(
					'key' => 'field_discount_type',
					'label' => 'Tipo de Desconto',
					'name' => 'discount_type',
					'type' => 'select',
					'instructions' => 'Selecione o tipo de desconto aplicado por este cupom',
					'required' => 1,
					'choices' => array(
						'fixed' => 'Desconto fixo',
						'percent' => 'Desconto percentual'
					),
					'return_format' => 'value',
				),
				array(
					'key' => 'field_discount_fixed_amount',
					'label' => 'Valor do Desconto (R$)',
					'name' => 'discount_fixed_amount',
					'type' => 'number',
					'instructions' => 'Informe o valor fixo do desconto',
					'required' => 0,
					'min' => 0,
					'step' => 0.01,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_discount_type',
								'operator' => '==',
								'value' => 'fixed',
							),
						),
					),
				),
				array(
					'key' => 'field_discount_percentage',
					'label' => 'Percentual do Desconto (%)',
					'name' => 'discount_percentage',
					'type' => 'number',
					'instructions' => 'Informe o percentual do desconto',
					'required' => 0,
					'min' => 0,
					'max' => 100,
					'step' => 0.01,
					'conditional_logic' => array(
						array(
							array(
								'field' => 'field_discount_type',
								'operator' => '==',
								'value' => 'percent',
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
						'value' => 'coupons',
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


