import { Badge, Label, Button } from '@bsf/force-ui';
import { cn, isURL } from '@/functions/utils';
import FixButton from '@GlobalComponents/fix-button';
import { __ } from '@wordpress/i18n';
import { Info, X } from 'lucide-react';
import { SeoPopupTooltip } from '@/apps/admin-components/tooltip';
import { ConfirmationDialog } from '@GlobalComponents/confirmation-dialog';
import { useState } from '@wordpress/element';
import { fetchImageDataByUrl } from '@/functions/api';

const IMAGE_ID_CACHE = new Map();

export const CheckCard = ( {
	variant,
	label,
	title,
	data,
	showImages,
	showFixButton = true,
	onIgnore,
	showRestoreButton = false,
	onRestore,
	showIgnoreButton = false,
} ) => {
	const [ showIgnoreDialog, setShowIgnoreDialog ] = useState( false );
	const { data: descriptionData } = renderDescription( data );
	const handleIgnoreClick = () => {
		setShowIgnoreDialog( true );
	};

	const handleIgnoreConfirm = async () => {
		await onIgnore();
		setShowIgnoreDialog( false );
	};

	return (
		<>
			<div className="relative flex flex-col gap-3 p-3 bg-background-primary rounded-lg shadow-sm border-0.5 border-solid border-border-subtle">
				{ showIgnoreButton && (
					<Button
						variant="outline"
						type="button"
						onClick={ handleIgnoreClick }
						aria-label={ __( 'Ignore this check', 'surerank' ) }
						className="absolute -top-2 -right-2 rounded-full *:focus:outline-none focus:ring-0 focus:[box-shadow:none]"
						icon={ <X className="size-4 text-text-primary" /> }
						size="xs"
					/>
				) }
				<div className="w-full flex items-start gap-2">
					<Badge
						label={ label }
						size="sm"
						type="pill"
						variant={ variant }
						disableHover
						className={ cn(
							showRestoreButton ? 'text-badge-color-disabled' : ''
						) }
					/>
					<div className="flex items-center">
						<Label
							size="xs"
							className="space-x-1 text-sm text-text-secondary inline"
						>
							{ title }
							<SeoPopupTooltip
								content={ __(
									'Click here to discover more details about this check.',
									'surerank'
								) }
								arrow
							>
								<a
									href={ surerank_globals?.help_link }
									className="shrink-0 align-sub ml-2 focus:outline-none focus:ring-0"
									target="_blank"
									rel="noopener noreferrer"
								>
									<Info className="size-4 text-icon-secondary hidden" />
								</a>
							</SeoPopupTooltip>
						</Label>
					</div>
					{ showRestoreButton && (
						<Button
							variant="outline"
							type="button"
							onClick={ onRestore }
							aria-label={ __(
								'Restore this check',
								'surerank'
							) }
							size="xs"
							className="ml-auto min-w-fit shrink-0"
						>
							{ __( 'Restore', 'surerank' ) }
						</Button>
					) }
					{ showFixButton && (
						<FixButton
							size="xs"
							className="ml-auto min-w-fit shrink-0"
							tooltipProps={ { className: 'z-999999' } }
						>
							{ __( 'Help Me Fix', 'surerank' ) }
						</FixButton>
					) }
				</div>
				{ showImages && <ImageGrid images={ descriptionData } /> }
				{ ! showImages &&
					descriptionData &&
					descriptionData.length > 0 && (
						<ul className="list-disc list-inside ml-3 mr-0 mt-0 mb-0.5">
							{ descriptionData.map( ( item, index ) =>
								isURL( item ) ? (
									<li
										key={ `${ item }-${ index }` }
										className="m-0 text-sm"
									>
										<Button
											tag="a"
											variant="link"
											href={ item }
											target="_blank"
											rel="noopener noreferrer"
											className="font-medium focus:outline-none focus:[box-shadow:none] [&>span]:px-0 break-all"
										>
											{ item }
										</Button>
									</li>
								) : (
									<li
										key={ `${ item }-${ index }` }
										className="m-0 text-sm font-medium text-text-secondary list-none"
									>
										{ item }
									</li>
								)
							) }
						</ul>
					) }
			</div>

			<ConfirmationDialog
				open={ showIgnoreDialog }
				setOpen={ setShowIgnoreDialog }
				title={ __( 'Ignore Page Checks', 'surerank' ) }
				description={ __(
					"We'll stop flagging this check in future scans. If it's not relevant, feel free to ignore it, you can always bring it back later if needed.",
					'surerank'
				) }
				confirmLabel={ __( 'Ignore', 'surerank' ) }
				cancelLabel={ __( 'Cancel', 'surerank' ) }
				onConfirm={ handleIgnoreConfirm }
				confirmVariant="primary"
				confirmDestructive={ true }
			/>
		</>
	);
};

export const ImageGrid = ( { images } ) => {
	if ( ! images || ! images.length ) {
		return null;
	}

	const handleImageClick = async ( event, image ) => {
		event?.preventDefault();

		if ( IMAGE_ID_CACHE.has( image ) ) {
			window.open(
				`/wp-admin/upload.php?item=${ IMAGE_ID_CACHE.get( image ) }`,
				'_blank',
				'noopener noreferrer'
			);
			return;
		}

		try {
			const results = await fetchImageDataByUrl( image );

			if ( ! results ) {
				throw new Error( 'No image found' );
			}

			const imageId = results?.id;
			IMAGE_ID_CACHE.set( image, imageId );
			window.open(
				`/wp-admin/upload.php?item=${ imageId }`,
				'_blank',
				'noopener noreferrer'
			);
		} catch ( error ) {
			// If we can't find the image, open the media library
			window.open(
				'/wp-admin/upload.php',
				'_blank',
				'noopener noreferrer'
			);
		}
	};

	return (
		<div className="grid grid-cols-3 gap-2 mb-0.5">
			{ images.map( ( image, index ) =>
				isURL( image ) ? (
					<Button
						variant="link"
						className="inline-flex focus:outline-none focus:[box-shadow:none] p-0"
						onClick={ ( event ) =>
							handleImageClick( event, image )
						}
						key={ `${ image }-${ index }` }
					>
						<img
							src={ image }
							alt={ image }
							className="w-full h-36 object-cover rounded"
						/>
					</Button>
				) : null
			) }
		</div>
	);
};

export const renderDescription = ( descriptions ) => {
	if ( ! Array.isArray( descriptions ) || ! descriptions.length ) {
		return { data: [] };
	}

	// Handle text or list descriptions only
	const data = [];
	descriptions.forEach( ( item ) => {
		if ( typeof item === 'string' ) {
			data.push( item );
		} else if (
			item &&
			typeof item === 'object' &&
			Array.isArray( item.list )
		) {
			data.push( ...item.list );
		} else if (
			item &&
			typeof item === 'object' &&
			! Array.isArray( item.list )
		) {
			data.push( ...Object.values( item.list ) );
		}
	} );
	return { data };
};
