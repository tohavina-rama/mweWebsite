/**
 * Meta Options build.
 */
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { STORE_NAME as storeName } from '@Store/constants';
import { useEffect } from '@wordpress/element';
import { SureRankMonoSmallLogo } from '@GlobalComponents/icons';

const SEOSideBar = () => {
	const modalState = useSelect( ( select ) =>
		select( storeName ).getModalState()
	);

	const { updateModalState } = useDispatch( storeName );

	useEffect( () => {
		if ( modalState ) {
			return;
		}

		// Open seo popup.
		updateModalState( true );
	}, [] );
	return null;
};

const SpectraPageSettingsPopup = () => {
	const getSidebarStore = window?.wp?.editor;
	if (
		! getSidebarStore ||
		! getSidebarStore?.PluginSidebar ||
		! getSidebarStore?.PluginSidebarMoreMenuItem
	) {
		return null;
	}

	const PluginSidebar = getSidebarStore.PluginSidebar;
	const PluginSidebarMoreMenuItem = getSidebarStore.PluginSidebarMoreMenuItem;
	// If the PluginSidebar or PluginSidebarMoreMenuItem is still not available, then return null for WP lower version.
	if (
		'function' !== typeof PluginSidebarMoreMenuItem ||
		'function' !== typeof PluginSidebar
	) {
		return null;
	}

	return (
		<>
			<PluginSidebarMoreMenuItem
				target="surerank-menu-icon"
				icon={ <SureRankMonoSmallLogo /> }
			>
				{ __( 'SureRank Meta Box', 'surerank' ) }
			</PluginSidebarMoreMenuItem>
			<PluginSidebar
				isPinnable={ true }
				icon={ <SureRankMonoSmallLogo /> }
				name="surerank-menu-icon"
				title={ __( 'SureRank Meta Box', 'surerank' ) }
				className={ 'surerank-sidebar' }
			>
				<SEOSideBar />
			</PluginSidebar>
		</>
	);
};
export default SpectraPageSettingsPopup;
