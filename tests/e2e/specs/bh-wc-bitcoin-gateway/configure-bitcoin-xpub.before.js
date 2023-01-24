const {
	merchant,
	settingsPageSaveChanges,
	clearAndFillInput
} = require( '@woocommerce/e2e-utils' );

const config = require( 'config' );

const configureBitcoinXpub = async( dispatch ) => {

	await merchant.openSettings( 'checkout', 'bitcoin_gateway' );

	await expect( page )

	// TODO: read from env.secret.
	// This is the empty "wp_plugin_wallet" wallet.
	var xpub = 'zpub6n37hVDJHFyDG1hBERbMBVjEd6ws6zVhg9bMs5STo21i9DgDE9Z9KTedtGxikpbkaucTzpj79n6Xg8Zwb9kY8bd9GyPh9WVRkM55uK7w97K';

	// Check is it already filled in to save time.
	let existingXpub = await page.evaluate( 'document.querySelector("#woocommerce_bitcoin_gateway_xpub").value' );

	if ( existingXpub !== xpub ) {

		await clearAndFillInput( '#woocommerce_bitcoin_gateway_xpub', xpub )

		await settingsPageSaveChanges();
	}

	await merchant.logout();
};

module.exports = configureBitcoinXpub;
