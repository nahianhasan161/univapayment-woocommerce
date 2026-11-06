( function () {
	var settings = window.wc.wcSettings.getSetting( 'univapayment_data', {} );
	var decode = window.wp.htmlEntities.decodeEntities;
	var el = window.wp.element.createElement;
	var title = decode( settings.title || 'bKash / Nagad' );
	var Content = function () {
		return el( 'div', null, decode( settings.description || '' ) );
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( {
		name: 'univapayment',
		label: el( 'span', null, title ),
		ariaLabel: title,
		content: el( Content ),
		edit: el( Content ),
		canMakePayment: function () {
			return true;
		},
		supports: { features: settings.supports || [ 'products' ] },
	} );
} )();
