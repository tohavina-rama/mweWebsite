import { memo, useMemo } from '@wordpress/element';
import { Container, Switch, Label } from '@bsf/force-ui';
import { __ } from '@wordpress/i18n';

const Other = memo( ( { settings, handleSwitchChange } ) => {
	const OtherOptions = useMemo(
		() => [
			{
				id: 1,
				label: __( 'Enable Video Sitemap', 'surerank' ),
				key: 'enable_xml_video_sitemap',
			},
			{
				id: 2,
				label: __( 'Enable News Sitemap', 'surerank' ),
				key: 'enable_xml_news_sitemap',
			},
		],
		[]
	);

	const generateOption = ( id, label, helpText ) => (
		<div className="flex flex-col gap-0.5">
			<div>
				<Label
					htmlFor={ `Others${ id }` }
					className="gap-1"
					variant="neutral"
				>
					{ label }
				</Label>
			</div>
			{ helpText && (
				<div className="flex flex-col gap-3">
					<Label
						htmlFor={ `Others${ id }` }
						size="xs"
						className="font-normal gap-1"
						variant="help"
					>
						{ helpText }
					</Label>
				</div>
			) }
		</div>
	);
	return (
		<Container direction="column" className="w-full">
			{ OtherOptions.map( ( { id, key, label, helpText } ) => (
				<Container.Item key={ id } className="md:w-full lg:w-full p-2">
					<div className="flex flex-row items-center justify-between w-full">
						<div className="flex flex-row items-center">
							<Switch
								id={ `Others${ id }` }
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

export default Other;
