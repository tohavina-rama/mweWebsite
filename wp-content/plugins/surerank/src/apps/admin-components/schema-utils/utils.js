import { Alert } from '@bsf/force-ui';
import { __ } from '@wordpress/i18n';

export const noFieldsAlert = (
	<Alert
		content={ __(
			'This schema does not have any configurable fields',
			'surerank'
		) }
		className="shadow-none"
		variant="info"
	/>
);

export const generateUUID = ( length = 16 ) => {
	return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
		/[xy]/g,
		function ( c ) {
			const r = Math.floor( Math.random() * length );
			const v = c === 'x' ? r : ( r % 4 ) + 8; // Replace bitwise operations
			return v.toString( 16 );
		}
	);
};
