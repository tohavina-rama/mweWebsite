import { memo } from '@wordpress/element';
import { Container, Switch, Label, Select } from '@bsf/force-ui';
import { __ } from '@wordpress/i18n';

const HTML = memo( ( { settings, handleSwitchChange } ) => {
	const handleInputChange = ( key, value ) =>
		handleSwitchChange( key, value );

	const blockTypes = [
		{ id: 'shortcode', label: __( 'Shortcode', 'surerank' ) },
		{ id: 'pages', label: __( 'Pages', 'surerank' ) },
	];

	const renderRadioButtons = blockTypes.map( ( { id, label } ) => (
		<Label
			key={ id }
			className="flex items-center cursor-pointer gap-2 relative"
		>
			<span className="relative p-0.5">
				<input
					type="radio"
					id={ id }
					name="blockType"
					value={ id }
					checked={ settings.sitemap_block_type === id }
					onChange={ ( e ) =>
						handleInputChange(
							'sitemap_block_type',
							e.target.value
						)
					}
					className="peer flex relative cursor-pointer appearance-none transition-all m-0 !border-1.5 border-solid rounded-full before:content-[''] checked:before:content-[''] checked:before:hidden before:hidden border-border-strong hover:border-border-interactive checked:border-border-interactive bg-white checked:bg-toggle-on checked:hover:bg-toggle-on-hover checked:hover:border-toggle-on-hover focus:ring-2 focus:ring-offset-4 focus:ring-focus size-4"
				/>
				<span className="inline-flex items-center absolute top-2/4 left-2/4 -translate-y-2/4 -translate-x-2/4 opacity-0 transition-opacity peer-checked:opacity-100 text-white">
					<div className="rounded-full bg-current size-1.5"></div>
				</span>
			</span>
			{ label }
		</Label>
	) );

	return (
		<Container direction="column" className="w-full" gap="xs">
			<Container.Item className="md:w-full lg:w-full p-2">
				<div className="flex flex-row items-center justify-between w-full">
					<Switch
						id="enable_html_sitemap"
						size="sm"
						aria-label="Enable HTML Sitemap"
						checked={ settings.enable_html_sitemap }
						onChange={ ( value ) =>
							handleInputChange( 'enable_html_sitemap', value )
						}
						label={
							<div className="flex flex-col gap-0.5">
								<Label
									htmlFor="enable_html_sitemap"
									className="gap-1"
									variant="neutral"
								>
									{ __( 'Enable HTML Sitemap', 'surerank' ) }
								</Label>
								<Label
									htmlFor="enable_html_sitemap"
									size="xs"
									className="font-normal gap-1"
									variant="help"
								>
									{ __(
										'Enable HTML sitemap to display the HTML sitemap on your website.',
										'surerank'
									) }
								</Label>
							</div>
						}
					/>
				</div>
			</Container.Item>

			{ /* Custom Radio Buttons for Shortcode and Pages */ }
			<Container.Item className="md:w-full lg:w-full p-2 gap-2">
				<Container direction="column" className="w-full" gap="xs">
					<Container.Item className="md:w-full lg:w-full">
						<Label size="sm" variant="help">
							{ __( 'Display Format', 'surerank' ) }
						</Label>
					</Container.Item>
					<Container.Item className="md:w-full lg:w-full">
						<div className="flex flex-row items-center gap-4">
							{ renderRadioButtons }
						</div>
					</Container.Item>
				</Container>
			</Container.Item>

			{ /* Shortcode Selection */ }
			{ settings.sitemap_block_type === 'shortcode' && (
				<Container.Item className="md:w-full lg:w-full p-2">
					<Select
						value={ settings.sitemap_display_shortcode }
						size="sm"
						multiple={ true }
						onChange={ ( value ) =>
							handleInputChange(
								'sitemap_display_shortcode',
								value
							)
						}
					>
						<Select.Button
							label={ __( 'Shortcode', 'surerank' ) }
						/>
						<Select.Options dropdownPortalId="surerank-root">
							<Select.Option value="sure_rank_html_sitemap">
								[sure_rank_html_sitemap]
							</Select.Option>
							<Select.Option value="sure_rank_html_sitemap_2">
								[sure_rank_html_sitemap_2]
							</Select.Option>
						</Select.Options>
					</Select>
					<Label size="sm" variant="help" className="pt-1.5">
						{ __(
							'Use this shortcode to display the HTML sitemap.',
							'surerank'
						) }
					</Label>
				</Container.Item>
			) }
		</Container>
	);
} );

export default HTML;
