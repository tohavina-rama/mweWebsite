import { __ } from '@wordpress/i18n';
import { Button, Text } from '@bsf/force-ui';
import { AlertTriangleIcon } from 'lucide-react';

/**
 * MigrationError component displays an error message when migration fails
 *
 * @param {Object}   props            - Component props
 * @param {string}   props.error      - Error message to display
 * @param {Function} props.onRetry    - Function to call when retry button is clicked
 * @param {boolean}  props.isDisabled - Whether the retry button should be disabled
 * @return {JSX.Element} MigrationError component
 */
const MigrationError = ( { error, onRetry, isDisabled } ) => {
	return (
		<div className="flex flex-col gap-2">
			<div
				className="flex flex-row gap-3 items-start p-3 bg-alert-background-danger border border-solid border-alert-border-danger rounded-lg"
				role="alert"
			>
				<AlertTriangleIcon
					className="text-support-error shrink-0"
					size={ 20 }
				/>
				<div className="flex flex-col gap-0.5">
					<Text size={ 14 } weight={ 600 } color="primary">
						{ __( 'Migration Error', 'surerank' ) }
					</Text>
					<Text
						size={ 14 }
						weight={ 400 }
						color="primary"
						dangerouslySetInnerHTML={ { __html: error } }
					/>
				</div>
			</div>

			<div className="flex mt-2">
				<Button
					size="md"
					variant="primary"
					onClick={ onRetry }
					disabled={ isDisabled }
				>
					{ __( 'Try Again', 'surerank' ) }
				</Button>
			</div>
		</div>
	);
};

export default MigrationError;
