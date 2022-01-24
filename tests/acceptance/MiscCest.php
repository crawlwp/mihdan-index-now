<?php

class MiscCest
{
	public function _before( AcceptanceTester $I ) {
	}

	public function checkFrontendWorks( AcceptanceTester $I ) {
		$I->amOnPage( '/' );
		$I->see( 'Welcome to WordPress' );
	}

	public function checkPluginSettingsPage( AcceptanceTester $I ) {
		$I->loginAsAdmin();
		$I->amOnPage( '/wp-admin/admin.php?page=mihdan-index-now' );
		$I->see( 'Enjoyed IndexNow?' );

		$input_id = function ( $id ) {
			return 'input[id="' . $id . '"]';
		};

		$I->seeCheckboxIsChecked( $input_id( 'wposa-mihdan_index_now_general[post_types][post]' ) );
		$I->dontSeeCheckboxIsChecked( $input_id( 'wposa-mihdan_index_now_general[post_types][page]' ) );
		$I->dontSeeCheckboxIsChecked( $input_id( 'wposa-mihdan_index_now_general[post_types][attachment]' ) );
	}
}
