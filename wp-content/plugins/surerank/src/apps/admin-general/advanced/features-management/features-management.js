import PageContentWrapper from '@AdminComponents/page-content-wrapper';
import { __ } from '@wordpress/i18n';
import withSuspense from '@AdminComponents/hoc/with-suspense';
import GeneratePageContent from '@Functions/page-content-generator';
import { createLazyRoute } from '@tanstack/react-router';

export const PAGE_CONTENT = [
	// This is the very first depth of the form. And it represents the section container of the form.
	{
		container: {
			id: 'disable-features-container',
			direction: 'column',
			gap: 6,
		},
		content: [
			{
				container: null,
				content: [
					{
						type: 'switch',
						id: 'enable_page_level_seo',
						storeKey: 'enable_page_level_seo',
						dataType: 'boolean',
						label: __( 'Page Level Checks', 'surerank' ),
						description: __(
							'Check individual pages to improve on-page SEO performance.',
							'surerank'
						),
					},
					{
						type: 'switch',
						id: 'enable_google_console',
						storeKey: 'enable_google_console',
						shouldReload: true,
						dataType: 'boolean',
						label: __( 'Google Search Console', 'surerank' ),
						description: __(
							'Connect with Google to track clicks and search rankings.',
							'surerank'
						),
					},
					{
						type: 'switch',
						id: 'enable_schemas',
						storeKey: 'enable_schemas',
						shouldReload: true,
						dataType: 'boolean',
						label: __( 'Schema', 'surerank' ),
						description: __(
							'Add structured data to improve how your site appears in search.',
							'surerank'
						),
					},
				],
			},
		],
	},
];

const DisableFeatures = () => {
	return (
		<PageContentWrapper
			title={ __( 'Feature Management', 'surerank' ) }
			description={ __(
				'Control which features need to be enabled or disabled completely.',
				'surerank'
			) }
		>
			<GeneratePageContent json={ PAGE_CONTENT } />
		</PageContentWrapper>
	);
};

export const LazyRoute = createLazyRoute( '/advanced/features_management' )( {
	component: withSuspense( DisableFeatures ),
} );

export default withSuspense( DisableFeatures );
