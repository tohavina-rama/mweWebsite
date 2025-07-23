import { useEffect, useState } from '@wordpress/element';
import PageContentWrapper from '@AdminComponents/page-content-wrapper';
import { __ } from '@wordpress/i18n';
import { Button, Container, Table, Tooltip } from '@bsf/force-ui';
import withSuspense from '@AdminComponents/hoc/with-suspense';
import { useSuspenseSelect, useDispatch } from '@wordpress/data';
import { STORE_NAME } from '@AdminStore/constants';
import EditSchema from './edit';
import { Edit, Trash } from 'lucide-react';
import { generateUUID } from '@AdminComponents/schema-utils/utils';
import Modal from './modal';
import { SaveSettingsButton } from '@/apps/admin-components/global-save-button';
import { createLazyRoute } from '@tanstack/react-router';

const Schema = () => {
	const { metaSettings } = useSuspenseSelect( ( select ) => {
		const { getMetaSettings } = select( STORE_NAME );
		return {
			metaSettings: getMetaSettings(),
		};
	}, [] );

	const { setMetaSetting, invalidateResolutionForStoreSelector } =
		useDispatch( STORE_NAME );

	const schemaData = metaSettings?.schemas || {};
	const schemaArray = Object.entries( schemaData ).map(
		( [ id, schema ] ) => ( {
			id,
			...schema,
		} )
	);

	// console.log( 'schemaArray', schemaArray );

	const schemaTypeOptions = surerank_globals?.schema_type_options || {};
	const defaultSchemasObject = surerank_globals?.default_schemas || {};
	const defaultSchemas = Object.entries( defaultSchemasObject ).map(
		( [ id, schema ] ) => ( {
			id,
			...schema,
		} )
	);

	const [ isModalOpen, setIsModalOpen ] = useState( false );
	const [ showEditSchema, setShowEditSchema ] = useState( false );
	const [ selectedSchema, setSelectedSchema ] = useState( '' );
	const [ selectedType, setSelectedType ] = useState( '' );
	const [ uniqueId, setUniqueId ] = useState( '' );
	const [ confirmDelete, setConfirmDelete ] = useState( null );

	const closeModal = () => setIsModalOpen( false );

	const handleBackToSchemas = () => {
		setShowEditSchema( false );
	};

	const handleEditSchema = ( schemaId ) => {
		const schemaToEdit = schemaData[ schemaId ];

		setUniqueId( schemaId );
		setSelectedSchema( schemaToEdit.title );
		setSelectedType( schemaToEdit.type );
		setShowEditSchema( true );
	};

	const handleAddSchema = () => {
		const schemaUniqueId = generateUUID();
		const newSchema = {
			title: selectedSchema || '',
			type: selectedType || '',
			show_on: {
				rules: [],
				specific: [],
				specificText: [],
			},
			fields: {
				'@type': selectedType || '',
			},
		};

		setMetaSetting( 'schemas', {
			...schemaData,
			[ schemaUniqueId ]: newSchema,
		} );
		setUniqueId( schemaUniqueId );
		setSelectedSchema( newSchema.title );
		setShowEditSchema( true );
	};

	const handleDeleteSchema = ( schemaId ) => {
		const updatedSchemas = { ...schemaData };
		delete updatedSchemas[ schemaId ];
		setMetaSetting( 'schemas', updatedSchemas );
		setConfirmDelete( null );
	};

	useEffect( () => {
		// Invalidate the resolver to ensure fresh data on next read
		invalidateResolutionForStoreSelector( 'getMetaSettings', [] );
	}, [] );

	return showEditSchema ? (
		<EditSchema
			schema={ selectedSchema }
			type={ selectedType }
			onBack={ handleBackToSchemas }
			setMetaSetting={ setMetaSetting }
			schemaId={ uniqueId }
			metaSettings={ metaSettings }
		/>
	) : (
		<PageContentWrapper
			title={ __( 'Schema', 'surerank' ) }
			description={ __(
				'Adds structured data to your content so search engines can better understand and present it. Most fields are already filled in to make setup easier and help your site show up better in search results.',
				'surerank'
			) }
		>
			<div className="flex flex-col items-start p-4 gap-2 bg-white shadow-sm rounded-xl">
				<Table className="w-full">
					<Table.Head>
						<Table.HeadCell>
							{ __( 'Schema Title', 'surerank' ) }
						</Table.HeadCell>
						<Table.HeadCell>
							{ __( 'Schema Type', 'surerank' ) }
						</Table.HeadCell>
						<Table.HeadCell className="w-14">
							<span className="sr-only">
								{ __( 'Actions', 'surerank' ) }
							</span>
						</Table.HeadCell>
					</Table.Head>
					<Table.Body>
						{ schemaArray.map( ( schema ) => (
							<Table.Row key={ schema.id }>
								<Table.Cell className="p-3">
									<span className="text-sm">
										{ schema?.fields?.schema_name ||
											schema?.title }
									</span>
								</Table.Cell>
								<Table.Cell className="p-3">
									<span className="text-sm">
										{ schema?.fields?.[ '@type' ] ||
											schema?.type }
									</span>
								</Table.Cell>
								<Table.Cell className="p-3 leading-none">
									<div className="flex items-center justify-end gap-2">
										<Button
											variant="ghost"
											size="xs"
											icon={
												<Edit
													aria-label="icon"
													role="img"
												/>
											}
											className="text-text-secondary hover:text-icon-primary"
											onClick={ () =>
												handleEditSchema( schema.id )
											}
										/>
										<Tooltip
											open={ confirmDelete === schema.id }
											setOpen={ () =>
												setConfirmDelete( schema.id )
											}
											variant="light"
											placement="bottom"
											tooltipPortalId="surerank-root"
											className="p-2 border border-solid border-border-subtle [&>svg>path]:stroke-border-subtle z-[99999]"
											interactive
											arrow
											content={
												<div className="space-x-2">
													<Button
														size="xs"
														variant="ghost"
														className="focus:[box-shadow:none]"
														onClick={ () =>
															setConfirmDelete(
																null
															)
														}
													>
														{ __(
															'Cancel',
															'surerank'
														) }
													</Button>
													<Button
														size="xs"
														className="focus:[box-shadow:none] bg-button-danger hover:bg-button-danger-hover outline-button-danger hover:outline-button-danger-hover"
														onClick={ () =>
															handleDeleteSchema(
																schema.id
															)
														}
													>
														{ __(
															'Remove',
															'surerank'
														) }
													</Button>
												</div>
											}
										>
											<Button
												size="xs"
												variant="ghost"
												className="p-0 text-text-secondary inline-flex rounded-sm focus:[box-shadow:none]"
												icon={ <Trash /> }
												onClick={ () =>
													setConfirmDelete(
														schema.id
													)
												}
											/>
										</Tooltip>
									</div>
								</Table.Cell>
							</Table.Row>
						) ) }
					</Table.Body>
				</Table>
				<Container className="py-2 px-0" gap="sm">
					<SaveSettingsButton />
					<Modal
						selectedSchema={ selectedSchema }
						setSelectedSchema={ setSelectedSchema }
						selectedType={ selectedType }
						setSelectedType={ setSelectedType }
						schemaTypeOptions={ schemaTypeOptions }
						defaultSchemas={ defaultSchemas }
						handleAddSchema={ handleAddSchema }
						isModalOpen={ isModalOpen }
						closeModal={ closeModal }
					/>
				</Container>
			</div>
		</PageContentWrapper>
	);
};

export const LazyRoute = createLazyRoute( '/advanced/schema' )( {
	component: withSuspense( Schema ),
} );

export default withSuspense( Schema );
