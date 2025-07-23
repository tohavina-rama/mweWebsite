import { cn } from '@/functions/utils';
import { STORE_NAME } from '@/store/constants';
import { dispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/* global jQuery */

export const handleOpenSureRankDrawer = () => {
	const dispatchToSureRankStore = dispatch( STORE_NAME );
	dispatchToSureRankStore.updateModalState( true );
};

export const sureRankLogoForBuilder = ( className ) => {
	return `<svg class="${ cn(
		className
	) }" width="12" height="12" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M13.5537 1.5C17.8453 1.5 21.3251 4.97895 21.3252 9.27051C21.3252 12.347 19.5368 15.0056 16.9434 16.2646H21.3252V22.5H18.0889C14.9086 22.5 12.2861 20.1186 11.9033 17.042H11.9014L11.9033 13.7852C14.8283 13.7661 17.0342 11.3894 17.0342 8.45996V6.0293C14.137 6.02947 11.6948 7.97682 10.9443 10.6338C10.1605 9.53345 8.87383 8.8165 7.41992 8.81641H6.38086V9.85352H6.38379C6.44515 12.0356 8.23375 13.786 10.4307 13.7861H10.7061L10.6934 17.042H10.6865C10.2943 20.1082 7.67678 22.4785 4.50391 22.4785H2.6748V1.5H13.5537Z" fill="white"/>
        </svg>`;
};

// eslint-disable-next-line wrap-iife
( function ( $ ) {
	$( window ).on( 'load', function () {
		const topBar = $( '#elementor-editor-wrapper-v2 header' )
			.children()
			.children()
			.children( '.MuiGrid-root:nth-child(3)' )
			.children( '.MuiStack-root' );

		// Get the button and svg class name from the topbar last child.
		const lastChild = topBar.last();
		const buttonClassName = lastChild.find( 'button' ).attr( 'class' );
		const svgClassName = lastChild.find( 'svg' ).attr( 'class' );

		// Create the button with click handler and insert as the 2nd child.
		const $button = $(
			`<button type="button" class="${ buttonClassName }" aria-label="${ __(
				'Open SureRank SEO',
				'surerank'
			) }" tabindex="0">
				${ sureRankLogoForBuilder( svgClassName ) }
			</button>`
		).on( 'click', handleOpenSureRankDrawer );

		topBar.children().first().after( $button );
	} );
} )( jQuery );
