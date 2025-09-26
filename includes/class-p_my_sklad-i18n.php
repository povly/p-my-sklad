<?php
/**
 * @since      1.0.0
 * @package    P_My_Sklad
 * @subpackage P_My_Sklad/includes
 * @author     Porshnyov Anatoly <povly19995@gmail.com>
 */
class P_My_Sklad_i18n {


	/**
	 * @since    1.0.0
	 */
	public function load_plugin_textdomain() {

		load_plugin_textdomain(
			'p_my_sklad',
			false,
			dirname( dirname( plugin_basename( __FILE__ ) ) ) . '/languages/'
		);

	}

}
