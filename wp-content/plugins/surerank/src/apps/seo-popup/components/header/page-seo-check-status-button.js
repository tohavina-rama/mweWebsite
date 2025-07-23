import { usePageChecks } from '../../hooks';
import { Button, Skeleton, Text } from '@bsf/force-ui';
import { ChartNoAxesColumnIncreasingIcon } from 'lucide-react';
import { cn } from '@/functions/utils';
import { _n, sprintf, __ } from '@wordpress/i18n';
import { isPageBuilderActive } from '../page-seo-checks/analyzer/utils/page-builder';
import { SeoPopupTooltip } from '@/apps/admin-components/tooltip';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME } from '@/store/constants';

const PageSeoCheckStatusButton = () => {
	const { updateAppSettings } = useDispatch( STORE_NAME );
	const appSettings = useSelect( ( select ) =>
		select( STORE_NAME ).getAppSettings()
	);
	const { status, initializing, counts } = usePageChecks();
	const { setPageSeoCheck } = useDispatch( STORE_NAME );
	const isPageBuilderEditor = isPageBuilderActive();

	const handleNavigateToChecks = () => {
		if ( appSettings.currentScreen === 'checks' ) {
			return;
		}
		updateAppSettings( {
			currentScreen: 'checks',
			previousScreen: appSettings?.currentScreen,
		} );
	};

	const handleOpenChecks = () => {
		const isTaxonomy = window?.surerank_seo_popup?.is_taxonomy === '1';
		setPageSeoCheck( 'checkType', isTaxonomy ? 'taxonomy' : 'post' );
		if ( isTaxonomy && window?.surerank_seo_popup?.term_id ) {
			setPageSeoCheck( 'postId', window?.surerank_seo_popup?.term_id );
		}
		handleNavigateToChecks();
	};

	if ( ! isPageBuilderEditor && initializing ) {
		return <Skeleton className="size-10 shrink-0" />;
	}

	return (
		<SeoPopupTooltip
			content={
				<Text size={ 12 } weight={ 600 } color="inverse">
					{ counts.errorAndWarnings > 0
						? sprintf(
								// translators: %1$s is the number of issues detected, %2$s is the word "Issue".
								__(
									'%1$s %2$s need attention. Click to see',
									'surerank'
								),
								counts.errorAndWarnings,
								_n(
									'issue',
									'issues',
									counts.errorAndWarnings,
									'surerank'
								)
						  )
						: __(
								'All SEO checks passed. Click to see',
								'surerank'
						  ) }
				</Text>
			}
			offset={ {
				crossAxis: -100,
				mainAxis: 8,
			} }
			arrow
		>
			<Button
				variant="ghost"
				size="sm"
				onClick={ handleOpenChecks }
				icon={
					<ChartNoAxesColumnIncreasingIcon className="shrink-0" />
				}
				className={ cn(
					'p-2 border-0.5 border-solid focus:[box-shadow:none] focus:outline-none [&_svg]:size-6 size-10',
					status === 'error' &&
						'bg-badge-background-red hover:bg-badge-background-red border-badge-border-red text-badge-color-red',
					status === 'warning' &&
						'bg-badge-background-yellow hover:bg-badge-background-yellow border-badge-border-yellow text-badge-color-yellow',
					status === 'success' &&
						'bg-badge-background-green hover:bg-badge-background-green border-badge-border-green text-badge-color-green'
				) }
			/>
		</SeoPopupTooltip>
	);
};

export default PageSeoCheckStatusButton;
