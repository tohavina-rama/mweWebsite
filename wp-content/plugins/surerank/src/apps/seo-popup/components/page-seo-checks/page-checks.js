import {
	useLayoutEffect,
	useRef,
	useState,
	useMemo,
	memo,
} from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Loader, Text } from '@bsf/force-ui';
import { AnimatePresence, motion } from 'framer-motion';
import { ChevronDownIcon } from 'lucide-react';
import { cn } from '@/functions/utils';
import { CheckCard } from '@GlobalComponents/check-card';

const PageChecks = ( { pageSeoChecks = {}, onIgnore, onRestore } ) => {
	const {
		badChecks = [],
		fairChecks = [],
		passedChecks = [],
		ignoredChecks = [],
		suggestionChecks = [],
		isCheckingLinks = false,
		linkCheckProgress = { current: 0, total: 0 },
	} = pageSeoChecks;

	const passedChecksContainerRef = useRef( null );
	const [ showPassedChecks, setShowPassedChecks ] = useState( false );

	const handleTogglePassedChecks = () => {
		setShowPassedChecks( ( prev ) => ! prev );
	};

	// Show passed checks by default if no visible bad or fair checks
	useLayoutEffect( () => {
		if ( ! badChecks.length && ! fairChecks.length && ! showPassedChecks ) {
			setShowPassedChecks( true );
		}
	}, [ badChecks.length, fairChecks.length, showPassedChecks ] );

	const hasBadOrFairChecks = useMemo(
		() =>
			badChecks.length > 0 ||
			fairChecks.length > 0 ||
			suggestionChecks.length > 0,
		[ badChecks.length, fairChecks.length, suggestionChecks.length ]
	);

	return (
		<motion.div
			className="space-y-6 p-1"
			initial={ { opacity: 0 } }
			animate={ { opacity: 1 } }
			exit={ { opacity: 0 } }
			transition={ { duration: 0.3 } }
		>
			{ /* Critical and Warning Checks Container */ }
			{ hasBadOrFairChecks && (
				<div className="space-y-3">
					{ badChecks.map( ( check ) => (
						<CheckCard
							key={ check.id }
							id={ check?.id }
							variant="red"
							label={ __( 'Critical', 'surerank' ) }
							title={ check.title }
							data={ check?.data }
							showImages={ check?.showImages }
							onIgnore={ () => onIgnore( check.id ) }
							showIgnoreButton={ true }
						/>
					) ) }
					{ fairChecks.map( ( check ) => (
						<CheckCard
							key={ check.id }
							id={ check.id }
							variant="yellow"
							label={ __( 'Warning', 'surerank' ) }
							title={ check.title }
							data={ check?.data }
							showImages={ check?.showImages }
							onIgnore={ () => onIgnore( check.id ) }
							showIgnoreButton={ true }
						/>
					) ) }
					{ suggestionChecks.map( ( check ) => (
						<CheckCard
							key={ check.id }
							id={ check.id }
							variant="blue"
							label={ __( 'Suggestion', 'surerank' ) }
							title={ check.title }
							data={ check?.data }
							showImages={ check?.showImages }
							onIgnore={ () => onIgnore( check.id ) }
						/>
					) ) }
					{ /* Broken links check progress will render here */ }
					{ isCheckingLinks && (
						<div className="flex items-center gap-2 p-2 bg-white rounded-lg shadow-sm border-0.5 border-solid border-border-subtle">
							<Loader size="sm" />
							<Text size={ 14 } weight={ 500 } color="tertiary">
								{ sprintf(
									/* translators: %1$d: number of links */
									__(
										'%1$d out of %2$d checks are done.',
										'surerank'
									),
									linkCheckProgress.current,
									linkCheckProgress.total
								) }
							</Text>
						</div>
					) }
				</div>
			) }
			{ /* Ignored Checks Container */ }
			{ ignoredChecks.length > 0 && (
				<div className="space-y-3 mt-4">
					{ ignoredChecks.map( ( check ) => (
						<CheckCard
							key={ check.id }
							variant="neutral"
							label={ __( 'Ignore', 'surerank' ) }
							title={ check.title }
							showFixButton={ false }
							showRestoreButton={ true }
							onRestore={ () => onRestore( check.id ) }
						/>
					) ) }
				</div>
			) }

			{ hasBadOrFairChecks && (
				<div className="flex items-center justify-center w-full">
					<Button
						variant="outline"
						size="xs"
						className="w-fit"
						icon={
							<ChevronDownIcon
								className={ cn(
									showPassedChecks && 'rotate-180'
								) }
							/>
						}
						iconPosition="right"
						onClick={ handleTogglePassedChecks }
					>
						{ __( 'Passed Checks', 'surerank' ) }
					</Button>
				</div>
			) }

			{ /* Passed Checks Container */ }
			<AnimatePresence>
				<motion.div
					ref={ passedChecksContainerRef }
					className={ cn( hasBadOrFairChecks && 'mt-4' ) }
					initial={ { opacity: 0 } }
					animate={ { opacity: showPassedChecks ? 1 : 0 } }
					exit={ {
						opacity: 0,
						transition: { duration: 0.1 },
					} }
					transition={ {
						duration: 0.2,
					} }
					onAnimationComplete={ () => {
						if ( showPassedChecks ) {
							passedChecksContainerRef.current.scrollIntoView( {
								behavior: 'smooth',
							} );
						}
					} }
				>
					{ showPassedChecks && passedChecks.length > 0 && (
						<div className="space-y-3">
							{ passedChecks.map( ( check ) => (
								<CheckCard
									key={ check.id }
									variant="green"
									label={ __( 'Passed', 'surerank' ) }
									title={ check.title }
									showFixButton={ false }
									onIgnore={ () => onIgnore( check.id ) }
								/>
							) ) }
						</div>
					) }
				</motion.div>
			</AnimatePresence>
		</motion.div>
	);
};

export default memo( PageChecks );
