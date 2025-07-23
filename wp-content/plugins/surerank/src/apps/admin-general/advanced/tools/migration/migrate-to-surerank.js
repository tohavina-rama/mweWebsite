import { useBlocker, createLazyRoute } from '@tanstack/react-router';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useReducer, useCallback } from '@wordpress/element';
import PageContentWrapper from '@AdminComponents/page-content-wrapper';
import { Button, Select, Checkbox, Text } from '@bsf/force-ui';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import {
	PAGE_TITLE,
	PAGE_DESCRIPTION,
	PLUGIN_OPTIONS,
	ACTIONS,
} from './constants';
import {
	migrationReducer,
	initialMigrationState,
	loadMigrationProgress,
	clearMigrationProgress,
} from './reducer';
import { ResumeMigration, MigrateDone } from './components';
import { MigrationProgressStatus } from './progress-components';
import MigrationError from './migration-error';

const MigrateToSureRank = () => {
	const [ state, dispatch ] = useReducer(
		migrationReducer,
		initialMigrationState
	);
	const {
		plugin_slug,
		isMigrating,
		isDone,
		error,
		showResume,
		deactivatePlugin,
	} = state;

	// Handle beforeunload event when migration is in progress
	const handleBeforeUnload = useCallback( () => {
		if ( isMigrating ) {
			const shouldLeave = confirm(
				__(
					'Migration is in progress. Are you sure you want to leave? Unsaved changes may be lost.',
					'surerank'
				)
			);
			return ! shouldLeave;
		}

		return false;
	}, [ isMigrating ] );

	// Block navigation when migration is in progress
	useBlocker( {
		shouldBlockFn: handleBeforeUnload,
		enableBeforeUnload: isMigrating,
	} );

	// Check if there's a migration in progress on component mount
	useEffect( () => {
		const savedProgress = loadMigrationProgress();
		if ( savedProgress ) {
			dispatch( {
				type: ACTIONS.LOAD_SAVED_STATE,
				payload: savedProgress,
			} );
		}
	}, [] );

	// API helper functions
	const apiRequest = async (
		endpoint,
		method,
		data = null,
		skipError = false
	) => {
		try {
			const response = await apiFetch( {
				path: endpoint,
				method,
				data,
			} );

			if ( ! response.success && ! skipError ) {
				throw new Error(
					response.message || __( 'API request failed', 'surerank' )
				);
			}

			return response;
		} catch ( err ) {
			throw new Error(
				err.message || __( 'API request failed', 'surerank' )
			);
		}
	};

	// Fetch terms data with pagination
	const fetchTermsData = async ( page = 1 ) => {
		dispatch( {
			type: ACTIONS.SET_MIGRATION_STATUS,
			payload: {
				type: 'reading_terms',
				page,
			},
		} );

		return apiRequest(
			addQueryArgs( '/surerank/v1/migrate/terms', { page, plugin_slug } ),
			'GET'
		);
	};

	// Fetch posts data with pagination
	const fetchPostsData = async ( page = 1 ) => {
		dispatch( {
			type: ACTIONS.SET_MIGRATION_STATUS,
			payload: {
				type: 'reading_posts',
				page,
			},
		} );

		return apiRequest(
			addQueryArgs( '/surerank/v1/migrate/posts', { page, plugin_slug } ),
			'GET'
		);
	};

	// Initialize migration with data fetching
	const initializeMigration = async () => {
		let totalItems = 0;
		const newState = {
			global_settings_migrated: false,
			terms: {},
			posts: {},
			pagination: {
				terms: {
					current_page: 1,
					total_pages: 1,
				},
				posts: {
					current_page: 1,
					total_pages: 1,
				},
			},
			total_items: 0,
			migrated_items: 0,
		};

		// Fetch terms data for all pages
		let termsCurrentPage = 1;
		let termsTotalPages = 1;

		do {
			const termsResponse = await fetchTermsData( termsCurrentPage );
			termsTotalPages = termsResponse.pagination.total_pages;

			// Process each taxonomy
			Object.entries( termsResponse.data ).forEach(
				( [ taxonomy, taxData ] ) => {
					if ( ! newState.terms[ taxonomy ] ) {
						newState.terms[ taxonomy ] = {
							total: taxData.count,
							title: taxData.title,
							completed: [],
							remaining: [ ...taxData.term_ids ],
						};
					} else {
						newState.terms[ taxonomy ].total += taxData.count;
						newState.terms[ taxonomy ].remaining.push(
							...taxData.term_ids
						);
					}
					totalItems += taxData.count;
				}
			);

			termsCurrentPage++;
		} while ( termsCurrentPage <= termsTotalPages );

		// Update pagination info
		newState.pagination.terms.current_page = 1;
		newState.pagination.terms.total_pages = termsTotalPages;

		// Fetch posts data for all pages
		let postsCurrentPage = 1;
		let postsTotalPages = 1;

		do {
			const postsResponse = await fetchPostsData( postsCurrentPage );
			postsTotalPages = postsResponse.pagination.total_pages;

			// Process each post type
			Object.entries( postsResponse.data ).forEach(
				( [ postType, postData ] ) => {
					if ( ! newState.posts[ postType ] ) {
						newState.posts[ postType ] = {
							total: postData.count,
							title: postData.title,
							completed: [],
							remaining: [ ...postData.post_ids ],
						};
					} else {
						newState.posts[ postType ].total += postData.count;
						newState.posts[ postType ].remaining.push(
							...postData.post_ids
						);
					}
					totalItems += postData.count;
				}
			);

			postsCurrentPage++;
		} while ( postsCurrentPage <= postsTotalPages );

		// Update pagination info
		newState.pagination.posts.current_page = 1;
		newState.pagination.posts.total_pages = postsTotalPages;

		// Add global settings to the total count
		totalItems += 1; // +1 for global settings

		// Update total items count
		newState.total_items = totalItems;

		return newState;
	};

	// Migration process functions
	const migrateGlobalSettings = async () => {
		try {
			dispatch( {
				type: ACTIONS.SET_MIGRATION_STATUS,
				payload: {
					type: 'global_settings',
				},
			} );

			await apiRequest( '/surerank/v1/migrate/global-settings', 'POST', {
				plugin_slug,
				cleanup: false,
			} );

			dispatch( { type: ACTIONS.COMPLETE_GLOBAL_SETTINGS } );
		} catch ( err ) {
			dispatch( { type: ACTIONS.SET_ERROR, payload: err.message } );
			throw err;
		}
	};

	const migrateTerm = async ( taxonomy, termId ) => {
		try {
			// Set the current migration status with taxonomy information
			dispatch( {
				type: ACTIONS.SET_MIGRATION_STATUS,
				payload: {
					type: 'terms',
					taxonomy,
				},
			} );

			await apiRequest(
				'/surerank/v1/migrate/terms',
				'POST',
				{
					plugin_slug,
					term_ids: [ termId ],
					cleanup: false,
				},
				true
			);

			dispatch( {
				type: ACTIONS.COMPLETE_TERM,
				payload: { taxonomy, termId },
			} );
		} catch ( err ) {
			dispatch( { type: ACTIONS.SET_ERROR, payload: err.message } );
			throw err;
		}
	};

	const migratePost = async ( postType, postId ) => {
		try {
			// Set the current migration status with postType information
			dispatch( {
				type: ACTIONS.SET_MIGRATION_STATUS,
				payload: {
					type: 'posts',
					postType,
				},
			} );

			await apiRequest(
				'/surerank/v1/migrate/posts',
				'POST',
				{
					plugin_slug,
					post_ids: [ postId ],
					cleanup: false,
				},
				true
			);

			dispatch( {
				type: ACTIONS.COMPLETE_POST,
				payload: { postType, postId },
			} );
		} catch ( err ) {
			dispatch( { type: ACTIONS.SET_ERROR, payload: err.message } );
			throw err;
		}
	};

	const deactivateSourcePlugin = async () => {
		try {
			await apiRequest(
				'/surerank/v1/migrate/deactivate-plugin',
				'POST',
				{
					plugin_slug,
				}
			);
		} catch ( err ) {}
	};

	// Start or continue migration process
	const processMigration = async ( initialData = {}, deactivate = false ) => {
		try {
			const { global_settings_migrated, terms, posts } = initialData;

			// Migrate global settings if not already migrated
			if ( ! global_settings_migrated ) {
				await migrateGlobalSettings();
			}

			// Migrate remaining terms one by one
			for ( const taxonomy in terms ) {
				const termsData = terms[ taxonomy ];
				// Set the current taxonomy being processed
				dispatch( {
					type: ACTIONS.SET_MIGRATION_STATUS,
					payload: {
						type: 'terms',
						taxonomy,
					},
				} );

				for ( const termId of [ ...termsData.remaining ] ) {
					await migrateTerm( taxonomy, termId );
				}
			}

			// Migrate remaining posts one by one
			for ( const postType in posts ) {
				const postsData = posts[ postType ];
				// Set the current post type being processed
				dispatch( {
					type: ACTIONS.SET_MIGRATION_STATUS,
					payload: {
						type: 'posts',
						postType,
					},
				} );

				for ( const postId of [ ...postsData.remaining ] ) {
					await migratePost( postType, postId );
				}
			}

			// After migration is complete, deactivate plugin if checked
			if ( deactivate ) {
				await deactivateSourcePlugin();
			}

			// Migration complete
			dispatch( { type: ACTIONS.COMPLETE_MIGRATION } );
		} catch ( err ) {
			// Error handling is done in individual migration functions
			dispatch( {
				type: ACTIONS.SET_ERROR,
				payload: err.message || __( 'Migration failed', 'surerank' ),
			} );
		}
	};

	// Handler functions
	const handleSelectPlugin = ( value ) => {
		dispatch( { type: ACTIONS.SET_PLUGIN, payload: value.slug } );
	};

	const handleDeactivateChange = ( checked ) => {
		dispatch( {
			type: ACTIONS.SET_DEACTIVATE_PLUGIN,
			payload: checked,
		} );
	};

	const handleMigrate = async () => {
		try {
			// Start migration with initial data
			dispatch( { type: ACTIONS.START_MIGRATION } );
			// Initialize migration data
			const initialData = await initializeMigration();
			// Set the initial data
			dispatch( {
				type: ACTIONS.SET_MIGRATION_DATA,
				payload: initialData,
			} );

			// Process the migration
			await processMigration( initialData, deactivatePlugin );
		} catch ( err ) {
			dispatch( {
				type: ACTIONS.SET_ERROR,
				payload:
					err.message ||
					__( 'Migration failed. Please try again.', 'surerank' ),
			} );
		}
	};

	const handleResumeMigration = async () => {
		const savedProgress = loadMigrationProgress();
		if ( savedProgress ) {
			dispatch( { type: ACTIONS.START_MIGRATION } );
			await processMigration(
				savedProgress?.migrationData,
				savedProgress?.deactivatePlugin
			);
		}
	};

	const handleStartOver = () => {
		dispatch( { type: ACTIONS.RESET_MIGRATION } );
		handleMigrate();
	};

	const handleCancelMigration = () => {
		clearMigrationProgress();
		dispatch( { type: ACTIONS.RESET_MIGRATION } );
	};

	// Show resume UI if there's a migration in progress
	if ( showResume ) {
		return (
			<PageContentWrapper
				title={ PAGE_TITLE }
				description={ PAGE_DESCRIPTION }
			>
				<div className="flex flex-col items-start p-6 gap-2 bg-white shadow-sm rounded-xl order-1 flex-none flex-grow-0">
					<ResumeMigration
						onResume={ handleResumeMigration }
						onStartOver={ handleStartOver }
						onCancel={ handleCancelMigration }
					/>
				</div>
			</PageContentWrapper>
		);
	}

	let content = (
		<>
			{ /* Migration Form */ }
			<div className="flex flex-col gap-4 w-full">
				<div className="flex items-center gap-2 w-full">
					<div className="flex-grow">
						<Select
							onChange={ handleSelectPlugin }
							size="md"
							value={ plugin_slug }
						>
							<Select.Button
								label={ __(
									'Import SEO data from',
									'surerank'
								) }
								placeholder={ __(
									'Select an option',
									'surerank'
								) }
								render={ () => (
									<span>
										{
											PLUGIN_OPTIONS.find(
												( plugin ) =>
													plugin.slug === plugin_slug
											)?.name
										}
									</span>
								) }
							/>
							<Select.Options>
								{ PLUGIN_OPTIONS.map( ( plugin ) => (
									<Select.Option
										key={ plugin.slug }
										value={ plugin }
									>
										{ plugin.name }
									</Select.Option>
								) ) }
							</Select.Options>
						</Select>
					</div>
					<Button
						variant="primary"
						onClick={ handleMigrate }
						disabled={ isMigrating || ! plugin_slug }
						className="mt-6"
					>
						{ __( 'Migrate', 'surerank' ) }
					</Button>
				</div>

				{ /* Checkbox for deactivation */ }
				{ plugin_slug && ! isMigrating && ! isDone && (
					<div className="flex items-start mt-4 bg-background-secondary p-2 rounded-md border border-solid border-border-subtle">
						<div className="mr-1.5">
							<Checkbox
								checked={ deactivatePlugin }
								size="sm"
								onChange={ handleDeactivateChange }
							/>
						</div>
						<Text size={ 14 } weight={ 500 } color="primary">
							{ sprintf(
								// translators: %s is the plugin name.
								__(
									'%s will be deactivated after migration. Uncheck to keep it active.',
									'surerank'
								),
								PLUGIN_OPTIONS.find(
									( plugin ) => plugin.slug === plugin_slug
								)?.name
							) }
						</Text>
					</div>
				) }

				{ error && (
					<MigrationError
						error={ error }
						onRetry={ handleMigrate }
						isDisabled={ isMigrating || ! plugin_slug }
					/>
				) }

				{ /* Migration Progress */ }
				{ isMigrating && (
					<div className="flex flex-col gap-2 mt-2 w-full">
						<MigrationProgressStatus
							migrationData={ state.migrationData }
							pluginSlug={ plugin_slug }
							currentStatus={ state.currentStatus }
							progress={ state.progress }
						/>
					</div>
				) }
			</div>
		</>
	);

	if ( isDone ) {
		content = <MigrateDone />;
	}

	return (
		<PageContentWrapper
			title={ PAGE_TITLE }
			description={ PAGE_DESCRIPTION }
		>
			<div className="flex flex-col items-start p-6 gap-2 bg-white shadow-sm rounded-xl order-1 flex-none flex-grow-0">
				{ content }
			</div>
		</PageContentWrapper>
	);
};

const LazyRoute = createLazyRoute( '/tools/migrate' )( {
	component: MigrateToSureRank,
} );

export { LazyRoute };

export default MigrateToSureRank;
