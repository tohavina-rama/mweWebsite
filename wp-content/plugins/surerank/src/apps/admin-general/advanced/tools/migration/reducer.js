import { ACTIONS, MIGRATION_PROGRESS_KEY } from './constants';

// Initialize default migration state
export const initialMigrationState = {
	plugin_slug: '',
	isMigrating: false,
	isDone: false,
	error: null,
	progress: 0,
	showResume: false,
	deactivatePlugin: true,
	currentStatus: null, // { type: 'reading_terms' | 'reading_posts' | 'global_settings' | 'terms' | 'posts', taxonomy?: string, postType?: string }
	migrationData: {
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
	},
};

// Calculate progress percentage
export const calculateProgress = ( state ) => {
	const { migrationData } = state;
	if ( ! migrationData || migrationData.total_items === 0 ) {
		return 0;
	}
	return Math.round(
		( migrationData.migrated_items / migrationData.total_items ) * 100
	);
};

// Save migration progress to local storage
export const saveMigrationProgress = ( migrationData ) => {
	window.localStorage.setItem(
		MIGRATION_PROGRESS_KEY,
		JSON.stringify( migrationData )
	);
};

// Load migration progress from local storage
export const loadMigrationProgress = () => {
	const savedProgress = window.localStorage.getItem( MIGRATION_PROGRESS_KEY );
	return savedProgress ? JSON.parse( savedProgress ) : null;
};

// Clear migration progress from local storage
export const clearMigrationProgress = () => {
	window.localStorage.removeItem( MIGRATION_PROGRESS_KEY );
};

// Migration state reducer
export const migrationReducer = ( state, action ) => {
	let newState;

	switch ( action.type ) {
		case ACTIONS.SET_PLUGIN:
			return {
				...state,
				plugin_slug: action.payload,
			};

		case ACTIONS.START_MIGRATION:
			newState = {
				...state,
				isMigrating: true,
				showResume: false,
				error: null,
			};
			saveMigrationProgress( newState );
			return {
				...newState,
				progress: calculateProgress( newState ),
			};

		case ACTIONS.SET_MIGRATION_DATA:
			newState = {
				...state,
				migrationData: {
					...state.migrationData,
					...action.payload,
				},
			};
			saveMigrationProgress( newState );
			return {
				...newState,
				progress: calculateProgress( newState ),
			};

		case ACTIONS.COMPLETE_GLOBAL_SETTINGS:
			newState = {
				...state,
				migrationData: {
					...state.migrationData,
					global_settings_migrated: true,
					migrated_items: state.migrationData.migrated_items + 1,
				},
			};
			saveMigrationProgress( newState );
			return {
				...newState,
				progress: calculateProgress( newState ),
			};

		case ACTIONS.COMPLETE_TERM:
			const { taxonomy, termId } = action.payload;
			const updatedTerms = { ...state.migrationData.terms };

			updatedTerms[ taxonomy ] = {
				...updatedTerms[ taxonomy ],
				completed: [ ...updatedTerms[ taxonomy ].completed, termId ],
				remaining: updatedTerms[ taxonomy ].remaining.filter(
					( id ) => id !== termId
				),
			};

			newState = {
				...state,
				migrationData: {
					...state.migrationData,
					terms: updatedTerms,
					migrated_items: state.migrationData.migrated_items + 1,
				},
			};
			saveMigrationProgress( newState );
			return {
				...newState,
				progress: calculateProgress( newState ),
			};

		case ACTIONS.COMPLETE_POST:
			const { postType, postId } = action.payload;
			const updatedPosts = { ...state.migrationData.posts };

			updatedPosts[ postType ] = {
				...updatedPosts[ postType ],
				completed: [ ...updatedPosts[ postType ].completed, postId ],
				remaining: updatedPosts[ postType ].remaining.filter(
					( id ) => id !== postId
				),
			};

			newState = {
				...state,
				migrationData: {
					...state.migrationData,
					posts: updatedPosts,
					migrated_items: state.migrationData.migrated_items + 1,
				},
			};
			saveMigrationProgress( newState );
			return {
				...newState,
				progress: calculateProgress( newState ),
			};

		case ACTIONS.SET_ERROR:
			return {
				...state,
				error: action.payload,
				isMigrating: false,
			};

		case ACTIONS.COMPLETE_MIGRATION:
			clearMigrationProgress();
			return {
				...state,
				isDone: true,
				isMigrating: false,
				progress: 100,
			};

		case ACTIONS.RESET_MIGRATION:
			clearMigrationProgress();
			return {
				...initialMigrationState,
				plugin_slug: state.plugin_slug,
			};

		case ACTIONS.LOAD_SAVED_STATE:
			return {
				...state,
				...action.payload,
				showResume: true,
			};

		case ACTIONS.SET_MIGRATION_STATUS:
			return {
				...state,
				currentStatus: action.payload,
			};

		case ACTIONS.SET_DEACTIVATE_PLUGIN:
			return {
				...state,
				deactivatePlugin: action.payload,
			};

		default:
			return state;
	}
};
