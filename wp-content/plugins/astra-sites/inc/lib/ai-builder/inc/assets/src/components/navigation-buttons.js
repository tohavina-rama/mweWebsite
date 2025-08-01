import { ArrowRightIcon } from '@heroicons/react/24/outline';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_KEY } from '../store';
import LoadingSpinner from './loading-spinner';
import { classNames } from '../utils/helpers';
import Button from './button';

const NavigationButtons = ( {
	continueButtonText = __( 'Next', 'ai-builder' ),
	previousButtonText = __( 'Back', 'ai-builder' ),
	onClickContinue,
	onClickPrevious,
	onClickSkip,
	disableContinue,
	loading = false,
	hideContinue = false,
	className,
	skipButtonText = __( 'Skip Step', 'ai-builder' ),
} ) => {
	const { setLoadingNextStep } = useDispatch( STORE_KEY );
	const { loadingNextStep } = useSelect( ( select ) => {
		const { getLoadingNextStep } = select( STORE_KEY );

		return {
			loadingNextStep: getLoadingNextStep(),
		};
	}, [] );

	const handleOnClick = async ( event, onClickFunction ) => {
		if ( loadingNextStep ) {
			return;
		}
		setLoadingNextStep( true );
		if ( typeof onClickFunction === 'function' ) {
			await onClickFunction( event );
		}
		setLoadingNextStep( false );
	};

	const handleOnClickContinue = ( event ) =>
		handleOnClick( event, onClickContinue );
	const handleOnClickPrevious = ( event ) =>
		handleOnClick( event, onClickPrevious );
	const handleOnClickSkip = ( event ) => handleOnClick( event, onClickSkip );

	useEffect( () => {
		if ( loadingNextStep === loading ) {
			return;
		}
		setLoadingNextStep( loading );
	}, [ loading ] );

	return (
		<div
			className={ classNames(
				'w-full flex flex-col sm:flex-row items-start sm:items-center gap-4 flex-wrap md:flex-nowrap',
				className
			) }
		>
			<div className="flex gap-4">
				{ ! hideContinue && (
					<Button
						type="submit"
						className="relative !pl-4 !pr-4 py-2.5 h-[40px] font-normal gap-1.5"
						onClick={ handleOnClickContinue }
						variant="primary"
						disabled={ disableContinue }
						hasSuffixIcon
					>
						<span
							className={ classNames(
								'!leading-4 text-sm',
								( loadingNextStep || loading ) && 'invisible'
							) }
						>
							{ continueButtonText }
						</span>
						<ArrowRightIcon
							className={ classNames(
								'w-4 h-4',
								( loadingNextStep || loading ) && 'invisible'
							) }
						/>
						{ ( loadingNextStep || loading ) && (
							<span className="absolute inset-0 flex items-center justify-center">
								<LoadingSpinner />
							</span>
						) }
					</Button>
				) }
				{ typeof onClickPrevious === 'function' && (
					<Button
						type="button"
						className="!pl-3 !pr-3 py-2.5"
						onClick={ handleOnClickPrevious }
						variant="white"
					>
						<span className="!leading-4 text-sm">
							{ previousButtonText }
						</span>
					</Button>
				) }
			</div>
			{ typeof onClickSkip === 'function' && (
				<Button
					type="button"
					className="ml-0 md:mr-0 sm:ml-auto text-secondary-text !px-0 py-0 sm:py-3"
					onClick={ handleOnClickSkip }
					variant="blank"
				>
					<span className="!leading-4 text-sm">
						{ skipButtonText }
					</span>
				</Button>
			) }
		</div>
	);
};

export default NavigationButtons;
