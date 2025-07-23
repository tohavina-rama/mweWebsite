import PageSeoCheckStatusButton from '@SeoPopup/components/header/page-seo-check-status-button';
import KeywordInput from '@SeoPopup/components/keyword-input';
import MetaSettingsScreen from './meta-settings';
import { ENABLE_PAGE_LEVEL_SEO } from '@Global/constants';
import { Skeleton } from '@bsf/force-ui';
import { Suspense } from '@wordpress/element';
import { isBricksBuilder } from '../page-seo-checks/analyzer/utils/page-builder';

const MetaSettings = () => {
	//we will show settings here
	return (
		<>
			<div className="flex items-center gap-2">
				<KeywordInput />
				{ ENABLE_PAGE_LEVEL_SEO && ! isBricksBuilder() && (
					<Suspense
						fallback={ <Skeleton className="size-10 shrink-0" /> }
					>
						<PageSeoCheckStatusButton />
					</Suspense>
				) }
			</div>
			<MetaSettingsScreen />
		</>
	);
};

export default MetaSettings;
