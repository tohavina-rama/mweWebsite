import { memo, useMemo } from '@wordpress/element';
import { Container, Switch, Label } from '@bsf/force-ui';
import { __ } from '@wordpress/i18n';

const XML = memo( ( { settings, handleSwitchChange } ) => {
	const XMLOptions = useMemo(
		() => [
			{
				id: 1,
				label: __( 'Enable XML Sitemap', 'surerank' ),
				key: 'enable_xml_sitemap',
				helpText: __(
					'Generates an XML sitemap to help search engines index your site content.',
					'surerank'
				),
			},
			{
				id: 2,
				label: __( 'Enable XML Image Sitemap', 'surerank' ),
				key: 'enable_xml_image_sitemap',
				helpText: __(
					'Add images from your posts and pages to the XML sitemap so search engines can find and index them more easily. Images are visible only in source code.',
					'surerank'
				),
			},
			{
				id: 3,
				label: __( 'Enable Author Sitemap', 'surerank' ),
				key: 'enable_author_sitemap',
				helpText: __(
					'Make sure to enable author archive from SEO, titles and metas, archives tab.',
					'surerank'
				),
			},
		],
		[]
	);

	const generateOption = ( id, label, helpText ) => (
		<div className="flex flex-col gap-0.5">
			<div>
				<Label
					htmlFor={ `XMLs${ id }` }
					className="gap-1"
					variant="neutral"
				>
					{ label }
				</Label>
			</div>
			<div className="flex flex-col gap-3">
				<Label
					htmlFor={ `XMLs${ id }` }
					size="xs"
					className="font-normal gap-1"
					variant="help"
				>
					{ helpText }
				</Label>
			</div>
		</div>
	);
	return (
		<Container direction="column" className="w-full">
			{ XMLOptions.map( ( { id, key, label, helpText } ) => (
				<Container.Item key={ id } className="md:w-full lg:w-full p-2">
					<div className="flex flex-row items-center justify-between w-full">
						<div className="flex flex-row items-center">
							<Switch
								id={ `XMLs${ id }` }
								size="sm"
								aria-label={ label }
								checked={ settings[ key ] }
								onChange={ ( value ) =>
									handleSwitchChange( key, value )
								}
								label={ generateOption( id, label, helpText ) }
							/>
						</div>
					</div>
				</Container.Item>
			) ) }
		</Container>
	);
} );

export default XML;
